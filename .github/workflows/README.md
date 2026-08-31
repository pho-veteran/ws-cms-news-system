# GitHub Actions CI/CD

`ci.yml` runs on every push and pull request. It lints PHP and JavaScript, fetches the
self-hosted fonts, builds the content-hashed assets, validates the generated manifest,
and uploads the build output as artifacts.

`deploy.yml` runs only for pushes to `main`. Its lint and build jobs are hard gates: the
deployment job cannot start unless they succeed. It deploys only the runtime theme files,
then reloads PHP-FPM, flushes FastCGI, and purges Cloudflare only when theme assets changed.
The origin is always purged before the edge.

## Required repository secrets

| Name                       | What it is                                                                                                 | How to obtain it                                                                                                                                                                                              |
| -------------------------- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DEPLOY_SSH_KEY`           | Private half of the dedicated Ed25519 deployment key used by GitHub Actions.                               | Generate a dedicated key pair with `ssh-keygen -t ed25519`; add its public half to the deployment user's `~/.ssh/authorized_keys` on the production server; store the private half as this repository secret. |
| `DEPLOY_SSH_KNOWN_HOSTS`   | Pinned SSH host key entry for the production server.                                                       | From a trusted administrator network, run `ssh-keyscan -H <production-host>` and independently verify the fingerprint against the server console or administrator record before storing the resulting line.   |
| `DEPLOY_HOST`              | Production server hostname or IP address reachable by SSH.                                                 | Obtain it from the Lightsail instance networking configuration or the administrator-managed DNS record.                                                                                                       |
| `DEPLOY_USER`              | Dedicated, key-only SSH account that can deploy the theme and run the required restricted `sudo` commands. | Create the deployment account on the production server; grant only the required `systemctl reload php8.3-fpm` and FastCGI-cache flush permissions in `sudoers`.                                               |
| `DEPLOY_THEME_PATH`        | Absolute production path for the `pgds` theme directory.                                                   | Confirm the WordPress document root on the server; the expected value is the full path ending in `wp-content/themes/pgds`.                                                                                    |
| `DEPLOY_PHP_FPM_SERVICE`   | Exact systemd service unit used by PHP-FPM.                                                                | Run `systemctl list-unit-files` or ask the server administrator; the planned PHP 8.3 installation normally uses `php8.3-fpm`.                                                                                 |
| `DEPLOY_FASTCGI_CACHE_DIR` | Absolute directory containing the Nginx FastCGI cache files.                                               | Confirm the `fastcgi_cache_path` configured in Nginx; the project configuration uses `/var/cache/nginx/fcgi`.                                                                                                 |
| `CLOUDFLARE_API_TOKEN`     | Cloudflare API token permitted to purge the production zone cache.                                         | In Cloudflare, create an API token scoped to **Zone / Cache Purge / Purge** for this zone only. Do not use the account-wide Global API Key.                                                                   |
| `CLOUDFLARE_ZONE_ID`       | Cloudflare zone identifier for the production domain.                                                      | In the Cloudflare dashboard, open the production zone and copy its Zone ID from the Overview page.                                                                                                            |

## Server access requirements

The server must accept only key-based authentication for the dedicated deployment user,
with `PasswordAuthentication no` and fail2ban enabled. Do not allowlist GitHub Actions IP
ranges: they are broad and change continuously. The deployment account must use least
privilege and must not receive an interactive passwordless root shell.

The deploy workflow uses `StrictHostKeyChecking=yes` with the pinned
`DEPLOY_SSH_KNOWN_HOSTS` secret. Do not replace this with a runtime `ssh-keyscan` without
an independently verified host key, because that would accept a man-in-the-middle key.
