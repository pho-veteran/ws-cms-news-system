# GitHub Actions CI/CD

`ci.yml` runs on every push and pull request. It lints PHP and JavaScript, fetches the
self-hosted fonts, builds the content-hashed assets, validates the generated manifest,
and uploads the build output as artifacts.

`deploy.yml` runs for pushes to `main` and for a manual `workflow_dispatch`. Its lint and
build jobs are hard gates: the deployment job cannot start unless they succeed. It deploys
the first-party runtime code — the theme, `wp-content/plugins/pgds-lunar-calendar/`, and
`wp-content/mu-plugins/` — then verifies the synced files, reloads PHP-FPM, flushes
FastCGI, purges Cloudflare when asset inputs changed, and finally smoke-tests the public
site. The origin is always purged before the edge.

## What the deploy does and does not touch

Deployed: the theme's runtime files, the first-party plugin directory, and the mu-plugins
directory. `--delete` is scoped to the theme directory and to the plugin's own directory,
so plugins installed through wp-admin survive. mu-plugins sync without `--delete` because
that directory is shared with host drop-ins this repository does not own.

Never touched by a deploy: WordPress core, the database, uploads, options, terms, and
content. The workflow runs no `wp theme activate`, no `pgds_seed_categories()`, no
`wp option update`, no `wp rewrite flush`, and no `wp pgds import`. The local scripts under
`infra/local/scripts/` are development-only — `setup.sh` hardcodes `http://localhost:8080`
and a default admin password, and must never run against production. Provisioning
production content or media is a separate, reviewed operation.

## Plugin activation

The lunar calendar is loaded by `wp-content/mu-plugins/pgds-lunar-loader.php`, so copying
the files *is* the activation — no `active_plugins` write and no WP-CLI on the server. This
keeps the deployment account on its two sudo grants below rather than widening sudo to run
WP-CLI as `www-data`. The loader skips itself when the plugin is activated normally in
wp-admin, so both paths work without a double load.

Before this loader existed, the deploy shipped only the theme: production answered 404 on
`/wp-json/pgds-lunar/v1/today` while the same route worked locally, and the sidebar
silently used the manual `pgds_lunar_note` CPT fallback. The homepage still returned 200,
which is why the gap went unnoticed — hence the smoke test now asserts that route.

## Post-deploy verification

The workflow validates that `DEPLOY_*` secrets are plain hostnames, user names, unit names,
and absolute paths before interpolating them into a remote `sudo` command, then:

1. Confirms the synced theme, plugin, and mu-plugin files are readable on the server.
2. Requires Cloudflare's purge response to carry `"success": true` — a rejected purge
   returns HTTP 200 with `"success": false` and would otherwise pass.
3. Smoke-tests `https://vihn.id.vn`: the homepage, the lunar REST route and its payload
   shape, and both hashed asset URLs from the freshly built manifest.

## Required repository secrets

| Name                       | What it is                                                                                                 | How to obtain it                                                                                                                                                                                              |
| -------------------------- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DEPLOY_SSH_KEY`           | Private half of the dedicated Ed25519 deployment key used by GitHub Actions.                               | Generate a dedicated key pair with `ssh-keygen -t ed25519`; add its public half to the deployment user's `~/.ssh/authorized_keys` on the production server; store the private half as this repository secret. |
| `DEPLOY_SSH_KNOWN_HOSTS`   | Pinned SSH host key entry for the production server.                                                       | From a trusted administrator network, run `ssh-keyscan -H <production-host>` and independently verify the fingerprint against the server console or administrator record before storing the resulting line.   |
| `DEPLOY_HOST`              | Production server hostname or IP address reachable by SSH.                                                 | Obtain it from the Lightsail instance networking configuration or the administrator-managed DNS record.                                                                                                       |
| `DEPLOY_USER`              | Dedicated, key-only SSH account that can deploy the theme and run the required restricted `sudo` commands. | Create the deployment account on the production server; grant only the required `systemctl reload php8.3-fpm` and FastCGI-cache flush permissions in `sudoers`.                                               |
| `DEPLOY_THEME_PATH`        | Absolute production path for the `pgds` theme directory. The plugin and mu-plugin paths are derived from it. | Confirm the WordPress document root on the server; the expected value is the full path ending in `wp-content/themes/pgds`. The workflow rejects a value that does not sit under `wp-content/themes/`.        |
| `DEPLOY_PHP_FPM_SERVICE`   | Exact systemd service unit used by PHP-FPM.                                                                | Run `systemctl list-unit-files` or ask the server administrator; the planned PHP 8.3 installation normally uses `php8.3-fpm`.                                                                                 |
| `DEPLOY_FASTCGI_CACHE_DIR` | Absolute directory containing the Nginx FastCGI cache files.                                               | Confirm the `fastcgi_cache_path` configured in Nginx; the project configuration uses `/var/cache/nginx/fcgi`.                                                                                                 |
| `CLOUDFLARE_API_TOKEN`     | Cloudflare API token permitted to purge the production zone cache.                                         | In Cloudflare, create an API token scoped to **Zone / Cache Purge / Purge** for this zone only. Do not use the account-wide Global API Key.                                                                   |
| `CLOUDFLARE_ZONE_ID`       | Cloudflare zone identifier for the production domain.                                                      | In the Cloudflare dashboard, open the production zone and copy its Zone ID from the Overview page.                                                                                                            |
| `AWS_DEPLOY_ROLE_ARN`      | ARN of the short-lived OIDC role used to manage one temporary SSH rule.                                    | Apply `infra/terraform/main`; use the `github_deploy_role_arn` output. Do not replace OIDC with access-key secrets.                                                                                            |
| `AWS_SECURITY_GROUP_ID`    | Production origin security group where the workflow opens the runner's IPv4 `/32`.                         | Apply `infra/terraform/main`; use the `app_security_group_id` output.                                                                                                                                           |
| `AWS_REGION`               | AWS region containing the production security group.                                                       | Use the main stack region; production currently uses `ap-southeast-1`.                                                                                                                                         |

The IAM trust policy uses GitHub's immutable OIDC subject form for repositories created on or
after 2026-07-15: `repo:<owner>@<owner-id>/<repo>@<repo-id>:ref:refs/heads/main`. Keep
`github_repository`, `github_repository_owner_id`, and `github_repository_id` in
`infra/terraform/main/variables.tf` aligned with the values returned by
`gh api repos/<owner>/<repo>` and the repository OIDC settings endpoint. A legacy name-only
subject makes AWS reject `AssumeRoleWithWebIdentity` even when `id-token: write` is present.

## Server access requirements

The server must accept only key-based authentication for the dedicated deployment user,
with `PasswordAuthentication no` and fail2ban enabled. Do not allowlist GitHub Actions IP
ranges: they are broad and change continuously. The deployment account must use least
privilege and must not receive an interactive passwordless root shell.

The deploy workflow uses `StrictHostKeyChecking=yes` with the pinned
`DEPLOY_SSH_KNOWN_HOSTS` secret. Do not replace this with a runtime `ssh-keyscan` without
an independently verified host key, because that would accept a man-in-the-middle key.

The job obtains short-lived AWS credentials through OIDC, removes any leaked rule from an
interrupted prior deploy, and then opens port 22 only to its detected public IPv4 `/32`. It
records the returned security-group rule ID and revokes that exact rule under `if: always()`.
After each deploy, the security group should contain no rule whose description starts with
`github-actions deploy`; the permanent administrator `/32` remains managed by Terraform.
