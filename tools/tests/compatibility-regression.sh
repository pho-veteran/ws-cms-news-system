#!/bin/sh
# Run the EPIC #3 category/SEO/importer/cron/cache compatibility checks in WordPress.
set -eu

TEST_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WP_PATH="$(CDPATH= cd -- "$TEST_DIR/../.." && pwd)"
WP="wp --path=$WP_PATH --allow-root"

$WP core is-installed >/dev/null
$WP eval-file "$TEST_DIR/compatibility-regression.php"

echo "==> EPIC #3 compatibility regression suite passed."
