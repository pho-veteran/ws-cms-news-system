#!/usr/bin/env bash
#
# Database backup -> S3 (Proposal 02 §6.1: mysqldump gzip, 2x/day, 7-day retention).
#
# Retention is NOT enforced here. The backup IAM user is deliberately PutObject-only
# with no DeleteObject (§6.2), so this script cannot prune what it writes even if it
# wanted to — that is the point: a compromised instance cannot erase its own backups.
# Expiry is the bucket's job; see the lifecycle note in infra/terraform/README.md.
#
# Install (as root on the origin):
#   install -m 0750 pgds-db-backup.sh /usr/local/sbin/
#   printf 'PGDS_BUCKET=...\nAWS_ACCESS_KEY_ID=...\nAWS_SECRET_ACCESS_KEY=...\nAWS_DEFAULT_REGION=ap-southeast-1\n' \
#     > /root/.pgds-backup.env && chmod 600 /root/.pgds-backup.env
#   Cron (§6.1, twice daily, offset off the hour so it never collides with the
#   snapshot job or a cache stampede):
#     17 3,15 * * * /usr/local/sbin/pgds-db-backup.sh
#
# The credentials live in a root-owned mode-600 env file rather than in the crontab
# or the script, because §10.2 accepts static keys on the instance only under those
# conditions. EC2 instance roles would be better, but the Lightsail path (the intended
# target) has no equivalent, so both backends use the same mechanism.

set -euo pipefail

# The AWS CLI is installed from snap, which puts it in /snap/bin — a directory absent
# from cron's default PATH. Without this line the dump succeeds, the upload dies with
# "aws: command not found", and the only symptom is a nightly "upload failed" in syslog
# while manual runs (login shells, which source /etc/profile) work fine. Prepending the
# system dirs keeps the resolution order predictable regardless of the caller's PATH.
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/snap/bin

ENV_FILE=/root/.pgds-backup.env
LOG_TAG=pgds-backup

log() { logger -t "$LOG_TAG" -- "$*"; echo "$(date -u +%FT%TZ) $*"; }
die() { log "ERROR: $*"; exit 1; }

[ -r "$ENV_FILE" ] || die "$ENV_FILE is missing or unreadable"
# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a

: "${PGDS_BUCKET:?PGDS_BUCKET must be set in $ENV_FILE}"
: "${AWS_ACCESS_KEY_ID:?AWS_ACCESS_KEY_ID must be set in $ENV_FILE}"
: "${AWS_SECRET_ACCESS_KEY:?AWS_SECRET_ACCESS_KEY must be set in $ENV_FILE}"
export AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION:-ap-southeast-1}"

DB_NAME="${PGDS_DB_NAME:-wordpress}"
STAMP="$(date -u +%Y%m%d-%H%M%S)"
WORK="$(mktemp -d /tmp/pgds-backup.XXXXXX)"
DUMP="$WORK/${DB_NAME}-${STAMP}.sql.gz"
trap 'rm -rf "$WORK"' EXIT

log "starting dump of $DB_NAME"

# --single-transaction gives a consistent snapshot of InnoDB tables without locking
# writers, so an editor saving a post mid-backup neither blocks nor corrupts the dump.
# --quick streams row by row instead of buffering the result set, which matters on a
# 2GB box (§4.1 leaves ~850MB of headroom, and a buffered dump can eat it).
# Credentials come from the socket-auth root user, so no password is on the cmdline.
if ! mysqldump --defaults-file=/etc/mysql/debian.cnf \
      --single-transaction --quick --routines --triggers --events \
      --default-character-set=utf8mb4 \
      "$DB_NAME" 2>"$WORK/err" | gzip -9 > "$DUMP"; then
  die "mysqldump failed: $(head -3 "$WORK/err" | tr '\n' ' ')"
fi

SIZE=$(stat -c %s "$DUMP")
# A gzipped dump of a populated WordPress database is never this small; a few hundred
# bytes means mysqldump wrote an error page or an empty database. Uploading that would
# quietly replace a good backup set with garbage.
[ "$SIZE" -gt 1024 ] || die "dump is only ${SIZE} bytes — refusing to upload a probably-empty backup"

# Verify the gzip stream before trusting it. A truncated dump (disk full mid-write)
# still exits 0 through the pipe, and would only be discovered during a restore.
gzip -t "$DUMP" || die "dump failed gzip integrity check"

KEY="db-dumps/${DB_NAME}-${STAMP}.sql.gz"
log "uploading $KEY (${SIZE} bytes)"

# --only-show-errors keeps cron mail quiet on success. SSE is enforced by the bucket,
# but requesting it explicitly means a misconfigured bucket fails loudly rather than
# silently storing plaintext.
aws s3api put-object \
  --bucket "$PGDS_BUCKET" \
  --key "$KEY" \
  --body "$DUMP" \
  --server-side-encryption AES256 \
  --output text --query ETag >/dev/null \
  || die "upload failed for $KEY"

log "uploaded $KEY OK"

# Local copy for a fast restore that does not need a round trip to S3. Only the two
# most recent are kept; the instance disk is 60GB and also holds 25-40GB of media.
LOCAL_DIR=/var/backups/pgds
mkdir -p "$LOCAL_DIR"
cp "$DUMP" "$LOCAL_DIR/"
find "$LOCAL_DIR" -name "${DB_NAME}-*.sql.gz" -printf '%T@ %p\n' \
  | sort -rn | tail -n +3 | cut -d' ' -f2- | xargs -r rm -f

log "done"
