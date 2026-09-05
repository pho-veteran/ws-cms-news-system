# GitHub Actions CI/CD

`ci.yml` runs on every push and pull request. It lints PHP and JavaScript, fetches the
self-hosted fonts, builds the content-hashed assets, validates the generated manifest,
and uploads the build output as artifacts.

`deploy.yml` runs for pushes to `main` and for a manual `workflow_dispatch`. Its lint and
build jobs are hard gates. As of 2026-09-05, the deployment job packages and promotes a
restricted, complete first-party runtime release rather than copying into WordPress
directly.

## Release boundary

A release contains only:

- the `pgds` theme runtime, including generated `assets/dist/`;
- `wp-content/plugins/pgds-lunar-calendar/`; and
- exactly `wp-content/mu-plugins/pgds-cache-flush.php` and
  `wp-content/mu-plugins/pgds-lunar-loader.php`.

The workflow writes a SHA-256 `MANIFEST.sha256` for **every** payload file, archives the
release, and uploads it as the fixed `pgds-deploy` account. That unprivileged account
extracts the archive into a private temporary directory below
`/var/lib/pgds-deploy/incoming`, then atomically renames it to the release ID. It cannot
write WordPress or the root-owned history.

Its sole passwordless privilege is the root-owned, fixed-purpose
`/usr/local/sbin/pgds-deploy-release <release-id>`. The helper validates the manifest,
PHP, and hashed-asset references; serializes promotion; copies only to the fixed runtime
destinations `/var/www/pgds/wp-content/themes/pgds`,
`/var/www/pgds/wp-content/plugins/pgds-lunar-calendar`, and the two named files in
`/var/www/pgds/wp-content/mu-plugins`; reloads `php8.3-fpm`; and flushes only
`/var/cache/nginx/fcgi`. It automatically restores the previous runtime if promotion
fails. Successful payloads move into root-owned immutable history; the five newest
releases are retained for rollback.

The lunar loader makes the shipped plugin available without writing `active_plugins` or
using WP-CLI. The helper and workflow never touch WordPress core, the database, uploads,
content, options, terms, unrelated plugins, or other mu-plugins; they never run setup,
seed, import, or WP-CLI commands.

After the origin promotion, the workflow purges Cloudflare only when the full pushed range
contains an asset input change (or for a manual rerun). It requires the API response's
`success` flag. The order is always **origin first, optional edge second**. Public smoke
tests then require a working homepage, usable lunar REST payload, and the freshly built
hashed CSS and JavaScript URLs.

## Existing-host migration

`DEPLOY_USER` is retained only as the **legacy bootstrap account** for hosts that predate
the restricted release boundary. The workflow derives the CI public key from
`DEPLOY_SSH_KEY`, first probes the fixed `pgds-deploy` account, and, when needed, uses the
legacy account's existing sudo access once to install and validate the restricted account
and helper. It then removes only that exact CI public key from the legacy account's
`authorized_keys`. The process is idempotent: ready hosts are verified without migration,
and every release operation always uses `pgds-deploy`.

Replacement hosts receive the account, public key, helper, fixed staging directories, and
sudo policy at first boot through Terraform. `DEPLOY_USER` must not be changed to
`pgds-deploy` and is not a runtime deployment target.

## Required repository secrets

| Name | What it is | How to obtain it |
| --- | --- | --- |
| `DEPLOY_SSH_KEY` | Private half of the dedicated Ed25519 CI deployment key. | Generate a dedicated key pair and store only its private half as the repository secret. The workflow derives the public half during migration. |
| `DEPLOY_SSH_KNOWN_HOSTS` | Pinned SSH host-key entry for the production server. | Obtain it from a trusted administrator network and independently verify its fingerprint before storing it. |
| `DEPLOY_HOST` | Production server hostname or IP address reachable by SSH. | Obtain it from the instance networking configuration or the administrator-managed DNS record. |
| `DEPLOY_USER` | Existing legacy bootstrap account, used only to migrate an existing host once. | Retain the old account name until the workflow has completed its idempotent migration; it is not `pgds-deploy`. |
| `CLOUDFLARE_API_TOKEN` | Token permitted to purge the production zone cache. | Create a token scoped to **Zone / Cache Purge / Purge** for this zone only. Do not use an account-wide key. |
| `CLOUDFLARE_ZONE_ID` | Production Cloudflare zone identifier. | Copy it from the production zone's Overview page. |
| `AWS_DEPLOY_ROLE_ARN` | Short-lived OIDC role used to manage one temporary SSH rule. | Apply `infra/terraform/main` and use the `github_deploy_role_arn` output. |
| `AWS_SECURITY_GROUP_ID` | Production origin security group where the workflow opens the runner's IPv4 `/32`. | Apply `infra/terraform/main` and use the `app_security_group_id` output. |
| `AWS_REGION` | Region containing the production security group. | Use the main stack region. |

`DEPLOY_THEME_PATH`, `DEPLOY_PHP_FPM_SERVICE`, and `DEPLOY_FASTCGI_CACHE_DIR` are no
longer repository secrets. Their fixed values are enforced inside the root-owned helper,
not supplied by CI.

The IAM trust policy uses GitHub's immutable OIDC subject form for repositories created on
or after 2026-07-15: `repo:<owner>@<owner-id>/<repo>@<repo-id>:ref:refs/heads/main`. Keep
`github_repository`, `github_repository_owner_id`, and `github_repository_id` in
`infra/terraform/main/variables.tf` aligned with the repository's GitHub settings. A
legacy name-only subject makes AWS reject `AssumeRoleWithWebIdentity` even when
`id-token: write` is present.

## Server access requirements

The server must accept key-based authentication only, with `PasswordAuthentication no` and
fail2ban enabled. `pgds-deploy` has no interactive root shell or general filesystem,
service-control, cache-delete, rsync, or WP-CLI sudo grant. Do not allowlist GitHub Actions
IP ranges: they are broad and change continuously.

The deploy workflow uses `StrictHostKeyChecking=yes` with the pinned
`DEPLOY_SSH_KNOWN_HOSTS` secret. Do not replace this with a runtime `ssh-keyscan` without
an independently verified host key, because that would accept a man-in-the-middle key.

The job obtains short-lived AWS credentials through OIDC, clears stale temporary deploy
rules, opens port 22 only to its detected public IPv4 `/32`, records the exact security-group
rule ID, and attempts to revoke that ID under `if: always()`. The final audit reports any
remaining temporary rule. A hard runner failure or cleanup API failure can still leave a
bounded `/32` rule until the next stale-rule sweep or an administrator removes it.
