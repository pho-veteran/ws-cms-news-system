#!/usr/bin/env bash
#
# RAM / disk / swap threshold alerting (Proposal 02 §7).
#
# Why a cron script instead of CloudWatch: RAM and disk are NOT built-in Lightsail
# metrics, so they would need the CloudWatch agent plus custom metrics. §7 costs that
# out at ~$24 over six months — 28% of the budget, more than the entire backup spend —
# to watch two numbers. This does the same job for ~$0 via SES.
#
# What CloudWatch/Lightsail alarms still own (free, already configured): CPU
# utilisation, burst capacity, and the status check. This script covers only the gaps.
#
# Install (as root on the origin):
#   install -m 0750 pgds-health-alert.sh /usr/local/sbin/
#   # reuses /root/.pgds-backup.env for credentials, plus PGDS_ALERT_TO / PGDS_ALERT_FROM
#   */10 * * * * /usr/local/sbin/pgds-health-alert.sh
#
# Thresholds are derived from §4.1's budget: the stack should sit at ~1.17GB of 2GB,
# so sustained memory above 85% means something is leaking or pm.max_children is too
# high. §4.1 is explicit that regular swap use means "the configuration is wrong",
# hence a low swap threshold rather than a generous one.

set -uo pipefail

ENV_FILE=/root/.pgds-backup.env
# §10.2 requires the SES credentials be SEPARATE from the backup credentials, and they are:
# pgds-backup is PutObject-only, pgds-ses is send-only. This script previously read only
# the backup env file, so `aws sesv2 send-email` ran as pgds-backup and failed with
#   AccessDeniedException: User .../pgds-backup is not authorized to perform ses:SendEmail
# while the alert itself was detected correctly. Sourced AFTER the backup file so the SES
# keys win for this process, leaving the backup keys untouched for pgds-db-backup.sh.
SES_ENV_FILE=/root/.pgds-ses.env
STATE_DIR=/var/lib/pgds
LOG_TAG=pgds-health

MEM_PCT_MAX="${PGDS_MEM_PCT_MAX:-85}"
DISK_PCT_MAX="${PGDS_DISK_PCT_MAX:-80}"
SWAP_MB_MAX="${PGDS_SWAP_MB_MAX:-256}"
# Do not re-send the same alert more often than this. Without it a sustained problem
# emails every 10 minutes, the mailbox gets muted, and the alert stops working —
# the same alert-fatigue failure §8.4 describes for the zero-spend budget.
COOLDOWN_SECONDS="${PGDS_ALERT_COOLDOWN:-10800}"

log() { logger -t "$LOG_TAG" -- "$*"; }

mkdir -p "$STATE_DIR"

if [ -r "$ENV_FILE" ]; then
  # shellcheck disable=SC1090
  set -a; . "$ENV_FILE"; set +a
fi
# Sourced SECOND so the send-only SES keys override the PutObject-only backup keys for
# this process. Without it the send ran as pgds-backup and AWS refused it — see the
# comment on SES_ENV_FILE. The backup script keeps reading ENV_FILE alone, so the two
# credentials stay separate on disk as §10.2 requires.
if [ -r "$SES_ENV_FILE" ]; then
  # shellcheck disable=SC1090
  set -a; . "$SES_ENV_FILE"; set +a
fi
export AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION:-ap-southeast-1}"

ALERT_TO="${PGDS_ALERT_TO:-}"
ALERT_FROM="${PGDS_ALERT_FROM:-}"

# --- Collect -----------------------------------------------------------------
# "available" rather than "used": used counts page cache, which the kernel gives back
# on demand. Alerting on used would fire constantly on a healthy box (§4.1 explicitly
# budgets ~850MB FOR the page cache).
read -r mem_total mem_avail <<<"$(awk '/^MemTotal:/{t=$2} /^MemAvailable:/{a=$2} END{print t, a}' /proc/meminfo)"
mem_used_pct=$(( (mem_total - mem_avail) * 100 / mem_total ))

swap_used_mb=$(awk '/^SwapTotal:/{t=$2} /^SwapFree:/{f=$2} END{print int((t-f)/1024)}' /proc/meminfo)
disk_used_pct=$(df --output=pcent / | tail -1 | tr -dc '0-9')

problems=()
[ "$mem_used_pct" -ge "$MEM_PCT_MAX" ] && problems+=("memory ${mem_used_pct}% >= ${MEM_PCT_MAX}%")
[ "$disk_used_pct" -ge "$DISK_PCT_MAX" ] && problems+=("disk / ${disk_used_pct}% >= ${DISK_PCT_MAX}%")
[ "$swap_used_mb" -ge "$SWAP_MB_MAX" ] && problems+=("swap ${swap_used_mb}MB >= ${SWAP_MB_MAX}MB (§4.1: regular swap use means the config is wrong)")

# Service liveness. A stopped php-fpm is a hard outage that no resource metric shows.
for svc in nginx php8.3-fpm mariadb redis-server; do
  systemctl is-active --quiet "$svc" || problems+=("service $svc is NOT active")
done

if [ "${#problems[@]}" -eq 0 ]; then
  log "ok mem=${mem_used_pct}% disk=${disk_used_pct}% swap=${swap_used_mb}MB"
  rm -f "$STATE_DIR/alerted"
  exit 0
fi

SUMMARY="$(IFS='; '; echo "${problems[*]}")"
log "PROBLEM $SUMMARY"

# --- Cooldown ----------------------------------------------------------------
now=$(date +%s)
if [ -f "$STATE_DIR/alerted" ]; then
  last=$(cat "$STATE_DIR/alerted" 2>/dev/null || echo 0)
  if [ $((now - last)) -lt "$COOLDOWN_SECONDS" ]; then
    log "within cooldown, not re-sending"
    exit 0
  fi
fi

# --- Notify ------------------------------------------------------------------
if [ -z "$ALERT_TO" ] || [ -z "$ALERT_FROM" ]; then
  # Not a failure: the alert still reaches syslog, which is where it is useful during
  # a hands-on incident. Email needs a verified SES identity, which needs a domain.
  log "PGDS_ALERT_TO/FROM unset — logged to syslog only, no email sent"
  exit 0
fi

BODY="$(cat <<EOF
pgds origin health alert

Host      : $(hostname)
Time      : $(date -u +%FT%TZ)
Problems  : $SUMMARY

Memory    : ${mem_used_pct}% used (threshold ${MEM_PCT_MAX}%)
Disk /    : ${disk_used_pct}% used (threshold ${DISK_PCT_MAX}%)
Swap      : ${swap_used_mb}MB used (threshold ${SWAP_MB_MAX}MB)

Top memory consumers:
$(ps -eo pmem,rss,comm --sort=-rss | head -6)

Runbook: RUNBOOK.md §7 (monitoring), §4.1 (RAM budget).
EOF
)"

# --content as JSON, not shorthand.
#
# The shorthand form (Simple={Subject={Data=...},Body={...}}) CANNOT carry this body. Its
# parser treats both commas and newlines as structural, and the body contains both — the
# `ps` table alone supplies several of each. The previous version tried to paper over that
# by escaping commas with sed, which mistook a parser limitation for an escaping problem;
# the CLI still rejected it and printed a caret pointing mid-body:
#
#   Simple={Subject={Data=[pgds] origin health alert,Charset=UTF-8},Body={Text={Data=Line one
#                               ^
#
# The visible symptom was "WARNING: SES send failed" on every real alert while a hand-typed
# test send worked, which pointed at credentials rather than at the argument syntax.
#
# JSON has no such ambiguity. Built with python3 -c so the body is encoded properly whatever
# it contains, and passed via a temp file so a long body cannot hit ARG_MAX either.
CONTENT_JSON="$(mktemp)"
trap 'rm -f "$CONTENT_JSON"' EXIT
python3 -c 'import json,sys
body = sys.stdin.read()
json.dump({"Simple": {
    "Subject": {"Data": "[pgds] origin health alert", "Charset": "UTF-8"},
    "Body": {"Text": {"Data": body, "Charset": "UTF-8"}},
}}, sys.stdout)' <<<"$BODY" > "$CONTENT_JSON"

if aws sesv2 send-email \
    --from-email-address "$ALERT_FROM" \
    --destination "ToAddresses=$ALERT_TO" \
    --content "file://$CONTENT_JSON" \
    >/dev/null 2>&1; then
  echo "$now" > "$STATE_DIR/alerted"
  log "alert emailed to $ALERT_TO"
else
  # Deliberately does not exit non-zero: a failed email must not mask the fact that
  # the underlying problem was detected and logged.
  log "WARNING: SES send failed; alert is in syslog only"
fi
