#!/usr/bin/env bash
#
# Exercise restricted-account installation and exact legacy-key retirement in a
# disposable Linux filesystem. Run through Docker so the host remains unchanged.

set -Eeuo pipefail

readonly REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
readonly IMAGE=debian:bookworm-slim

exec docker run --rm --interactive \
  --mount "type=bind,source=$REPO_ROOT,target=/repo,readonly" \
  "$IMAGE" \
  bash -s <<'CONTAINER'
set -Eeuo pipefail
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
if ! command -v visudo >/dev/null; then
  apt-get update -qq
  apt-get install -y -qq sudo >/dev/null
fi

fail() {
  printf 'FAIL: %s\n' "$*" >&2
  exit 1
}

readonly INSTALLER=/repo/infra/scripts/install-pgds-deploy-helper.sh
readonly HELPER=/repo/infra/scripts/pgds-deploy-release
readonly CI_KEY='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPGDSCIReleaseKeyOnly00000000000000000 github-actions-pgds'
readonly ADMIN_KEY='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPGDSAdministrator00000000000000000 administrator'
readonly OPTIONS_KEY='from="192.0.2.0/24",no-port-forwarding ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPGDSCIReleaseKeyOnly00000000000000000 legacy-options'
readonly LEGACY_HOME=/tmp/pgds-legacy-home
readonly DEPLOY_HOME=/tmp/pgds-deploy-home
readonly KEY_SOURCE=/tmp/pgds-deploy.pub

id legacy-admin >/dev/null 2>&1 \
  || useradd --create-home --home-dir "$LEGACY_HOME" --shell /bin/bash legacy-admin
install -d -m 0700 -o legacy-admin -g legacy-admin "$LEGACY_HOME/.ssh"
printf '%s\n%s\n%s\n' "$ADMIN_KEY" "$CI_KEY" "$OPTIONS_KEY" \
  > "$LEGACY_HOME/.ssh/authorized_keys"
chown legacy-admin:legacy-admin "$LEGACY_HOME/.ssh/authorized_keys"
chmod 0600 "$LEGACY_HOME/.ssh/authorized_keys"
printf '%s\n' "$CI_KEY" > "$KEY_SOURCE"

PGDS_DEPLOY_HOME="$DEPLOY_HOME" \
PGDS_AUTHORIZED_KEYS_SOURCE="$KEY_SOURCE" \
PGDS_RETIRE_KEY_FROM_USER=legacy-admin \
bash "$INSTALLER" "$HELPER"

id pgds-deploy >/dev/null 2>&1 || fail 'deployment account was not created'
[ "$(getent passwd pgds-deploy | cut -d: -f6)" = "$DEPLOY_HOME" ] \
  || fail 'deployment account has an unexpected home'
[ "$(stat -c '%U:%G:%a' "$DEPLOY_HOME/.ssh/authorized_keys")" = 'pgds-deploy:pgds-deploy:600' ] \
  || fail 'deployment authorized_keys ownership or mode is wrong'
[ "$(< "$DEPLOY_HOME/.ssh/authorized_keys")" = "$CI_KEY" ] \
  || fail 'deployment account does not contain exactly the CI key'
[ "$(stat -c '%U:%G:%a' /usr/local/sbin/pgds-deploy-release)" = 'root:root:755' ] \
  || fail 'promotion helper ownership or mode is wrong'
[ "$(stat -c '%U:%G:%a' /var/lib/pgds-deploy/incoming)" = 'pgds-deploy:pgds-deploy:750' ] \
  || fail 'incoming directory ownership or mode is wrong'
[ "$(stat -c '%U:%G:%a' /var/lib/pgds-deploy/history)" = 'root:root:750' ] \
  || fail 'history directory ownership or mode is wrong'
[ "$(stat -c '%U:%G:%a' /etc/sudoers.d/pgds-deploy-release)" = 'root:root:440' ] \
  || fail 'sudoers ownership or mode is wrong'
visudo -cf /etc/sudoers >/dev/null || fail 'installed sudoers policy is invalid'
sudo -l -U pgds-deploy | grep -Fq '/usr/local/sbin/pgds-deploy-release' \
  || fail 'deployment account lacks the fixed helper grant'

grep -Fqx "$ADMIN_KEY" "$LEGACY_HOME/.ssh/authorized_keys" \
  || fail 'unrelated administrator key was removed'
if grep -Fq 'AAAAC3NzaC1lZDI1NTE5AAAAIPGDSCIReleaseKeyOnly00000000000000000' \
  "$LEGACY_HOME/.ssh/authorized_keys"; then
  fail 'CI key was not fully retired from the legacy account'
fi

# A second migration run must keep the same state and remain successful.
PGDS_DEPLOY_HOME="$DEPLOY_HOME" \
PGDS_AUTHORIZED_KEYS_SOURCE="$KEY_SOURCE" \
PGDS_RETIRE_KEY_FROM_USER=legacy-admin \
bash "$INSTALLER" "$HELPER"
[ "$(< "$LEGACY_HOME/.ssh/authorized_keys")" = "$ADMIN_KEY" ] \
  || fail 'idempotent run changed the remaining administrator key'

printf '%s\n%s\n' "$CI_KEY" "$ADMIN_KEY" > /tmp/two-keys.pub
if PGDS_DEPLOY_HOME="$DEPLOY_HOME" \
  PGDS_AUTHORIZED_KEYS_SOURCE=/tmp/two-keys.pub \
  bash "$INSTALLER" "$HELPER" >/tmp/invalid-key.log 2>&1; then
  fail 'installer accepted multiple deployment keys'
fi
grep -Fq 'must contain exactly one Ed25519 public key' /tmp/invalid-key.log \
  || fail 'multiple-key rejection did not report the expected error'

if PGDS_DEPLOY_HOME="$DEPLOY_HOME" \
  PGDS_RETIRE_KEY_FROM_USER=legacy-admin \
  bash "$INSTALLER" "$HELPER" >/tmp/missing-key.log 2>&1; then
  fail 'installer allowed key retirement without a source key'
fi
grep -Fq 'requires PGDS_AUTHORIZED_KEYS_SOURCE' /tmp/missing-key.log \
  || fail 'missing-key rejection did not report the expected error'

printf 'install-pgds-deploy-helper disposable tests passed\n'
CONTAINER
