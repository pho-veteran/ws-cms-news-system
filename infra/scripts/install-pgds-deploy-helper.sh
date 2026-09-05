#!/usr/bin/env bash
#
# Install or update the restricted PGDS release-promotion boundary.
#
# Run this as root from a trusted checkout or copy both this installer and
# pgds-deploy-release to the origin. It is idempotent and intentionally does not
# install WordPress, activate plugins, modify the database, or touch uploads.
# Set PGDS_AUTHORIZED_KEYS_SOURCE to a file containing only the dedicated CI
# public key when creating the account on an existing host. Replacement hosts
# receive the same dedicated key through Terraform; never copy an administrator key.

set -Eeuo pipefail
umask 022
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

readonly HELPER_SOURCE="${1:-$(dirname "$0")/pgds-deploy-release}"
readonly HELPER_DEST=/usr/local/sbin/pgds-deploy-release
readonly SUDOERS_DEST=/etc/sudoers.d/pgds-deploy-release
readonly DEPLOY_USER="${PGDS_DEPLOY_USER:-pgds-deploy}"
readonly DEPLOY_GROUP="${PGDS_DEPLOY_GROUP:-$DEPLOY_USER}"
readonly AUTHORIZED_KEYS_SOURCE="${PGDS_AUTHORIZED_KEYS_SOURCE:-}"
readonly RETIRE_KEY_FROM_USER="${PGDS_RETIRE_KEY_FROM_USER:-}"
readonly DEPLOY_HOME="${PGDS_DEPLOY_HOME:-/home/$DEPLOY_USER}"
readonly DEPLOY_ROOT=/var/lib/pgds-deploy
readonly INCOMING_ROOT="$DEPLOY_ROOT/incoming"
readonly HISTORY_ROOT="$DEPLOY_ROOT/history"

retire_tmp=''
sudoers_tmp=''

cleanup() {
  [ -n "$retire_tmp" ] && rm -f -- "$retire_tmp"
  [ -n "$sudoers_tmp" ] && rm -f -- "$sudoers_tmp"
}
trap cleanup EXIT

log() { printf '%s %s\n' "$(date -u +%FT%TZ)" "$*"; }
die() { log "ERROR: $*" >&2; exit 1; }

[ "${EUID:-$(id -u)}" -eq 0 ] || die 'must run as root'
[ "$#" -le 1 ] || die 'usage: PGDS_AUTHORIZED_KEYS_SOURCE=<path> [PGDS_RETIRE_KEY_FROM_USER=<user>] install-pgds-deploy-helper.sh [helper-source]'
if [ -n "$RETIRE_KEY_FROM_USER" ]; then
  [[ "$RETIRE_KEY_FROM_USER" =~ ^[a-z_][a-z0-9_-]*$ ]] \
    || die 'PGDS_RETIRE_KEY_FROM_USER is not a valid POSIX user name'
  [ "$RETIRE_KEY_FROM_USER" != "$DEPLOY_USER" ] \
    || die 'PGDS_RETIRE_KEY_FROM_USER must not be the deployment user'
  [ -n "$AUTHORIZED_KEYS_SOURCE" ] \
    || die 'PGDS_RETIRE_KEY_FROM_USER requires PGDS_AUTHORIZED_KEYS_SOURCE'
fi
[[ "$DEPLOY_USER" =~ ^[a-z_][a-z0-9_-]*$ ]] || die 'PGDS_DEPLOY_USER is not a valid POSIX user name'
[[ "$DEPLOY_GROUP" =~ ^[a-z_][a-z0-9_-]*$ ]] || die 'PGDS_DEPLOY_GROUP is not a valid POSIX group name'
if ! getent group "$DEPLOY_GROUP" >/dev/null; then
  groupadd --system "$DEPLOY_GROUP"
fi
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
  useradd --create-home --home-dir "$DEPLOY_HOME" --shell /bin/bash \
    --gid "$DEPLOY_GROUP" "$DEPLOY_USER"
fi
[ "$(id -g -n "$DEPLOY_USER")" = "$DEPLOY_GROUP" ] \
  || die "deployment user's primary group is not $DEPLOY_GROUP"
[ -d "$DEPLOY_HOME" ] && [ ! -L "$DEPLOY_HOME" ] \
  || die "deployment home must be a regular directory: $DEPLOY_HOME"
[ "$(getent passwd "$DEPLOY_USER" | cut -d: -f6)" = "$DEPLOY_HOME" ] \
  || die "deployment user has an unexpected home directory"

if [ -n "$AUTHORIZED_KEYS_SOURCE" ]; then
  [ -f "$AUTHORIZED_KEYS_SOURCE" ] && [ ! -L "$AUTHORIZED_KEYS_SOURCE" ] \
    || die 'PGDS_AUTHORIZED_KEYS_SOURCE must be a regular, non-symlink file'
  [ "$(awk 'END { print NR }' "$AUTHORIZED_KEYS_SOURCE")" -eq 1 ] \
    && grep -Eq '^ssh-ed25519 [A-Za-z0-9+/]+={0,3}( [^[:cntrl:]]+)?$' "$AUTHORIZED_KEYS_SOURCE" \
    || die 'PGDS_AUTHORIZED_KEYS_SOURCE must contain exactly one Ed25519 public key'
  install -d -m 0700 -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" "$DEPLOY_HOME/.ssh"
  install -m 0600 -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" \
    "$AUTHORIZED_KEYS_SOURCE" "$DEPLOY_HOME/.ssh/authorized_keys"
elif [ ! -s "$DEPLOY_HOME/.ssh/authorized_keys" ]; then
  die 'set PGDS_AUTHORIZED_KEYS_SOURCE when the deployment account has no authorized key'
fi

# Existing hosts may have used the CI key through Ubuntu's broad sudo account.
# Remove only the exact key just installed for pgds-deploy; preserve every other
# administrator key and fail before changing anything if the source is ambiguous.
[ -f "$HELPER_SOURCE" ] && [ ! -L "$HELPER_SOURCE" ] || die 'helper source must be a regular, non-symlink file'
command -v visudo >/dev/null || die 'visudo is required'

# The upload account owns only incoming/. Promoted history and the helper itself remain
# root-owned, so a later compromised CI run cannot rewrite previously validated payloads
# or expand what its sudo command can do.
install -d -m 0755 -o root -g root "$DEPLOY_ROOT"
install -d -m 0750 -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" "$INCOMING_ROOT"
install -d -m 0750 -o root -g root "$HISTORY_ROOT"
install -m 0755 -o root -g root "$HELPER_SOURCE" "$HELPER_DEST"

sudoers_tmp="$(mktemp /etc/sudoers.d/.pgds-deploy-release.XXXXXX)"
cat > "$sudoers_tmp" <<EOF
# Managed by infra/scripts/install-pgds-deploy-helper.sh.
# No arguments are wildcarded here: sudo permits this executable with any release ID,
# and the root-owned helper constrains that ID to one child of its fixed incoming root.
$DEPLOY_USER ALL=(root) NOPASSWD: $HELPER_DEST
EOF
chmod 0440 "$sudoers_tmp"
visudo -cf "$sudoers_tmp" >/dev/null || die 'generated sudoers policy is invalid'
install -m 0440 -o root -g root "$sudoers_tmp" "$SUDOERS_DEST"
visudo -cf /etc/sudoers >/dev/null || die 'installed sudoers policy failed full validation'

# Existing hosts may have used the CI key through Ubuntu's broad sudo account.
# Retire only the exact key just installed for pgds-deploy after the helper and
# sudo policy are valid; preserve every unrelated administrator key.
if [ -n "$RETIRE_KEY_FROM_USER" ]; then
  [ -n "$AUTHORIZED_KEYS_SOURCE" ] \
    || die 'PGDS_RETIRE_KEY_FROM_USER requires PGDS_AUTHORIZED_KEYS_SOURCE'
  retire_home="$(getent passwd "$RETIRE_KEY_FROM_USER" | cut -d: -f6)"
  [ -n "$retire_home" ] || die "cannot resolve home for $RETIRE_KEY_FROM_USER"
  retire_keys="$retire_home/.ssh/authorized_keys"
  if [ -f "$retire_keys" ]; then
    source_blob="$(awk '{ print $2 }' "$AUTHORIZED_KEYS_SOURCE")"
    retire_tmp="$(mktemp "$retire_home/.ssh/.authorized_keys.XXXXXX")"
    awk -v blob="$source_blob" '
      {
        for (field = 1; field < NF; field++) {
          if ($field == "ssh-ed25519" && $(field + 1) == blob) {
            next
          }
        }
        print
      }
    ' "$retire_keys" > "$retire_tmp"
    chown --reference="$retire_keys" "$retire_tmp"
    chmod --reference="$retire_keys" "$retire_tmp"
    mv -- "$retire_tmp" "$retire_keys"
    retire_tmp=''
  fi
fi

log "installed $HELPER_DEST for restricted use by $DEPLOY_USER"
