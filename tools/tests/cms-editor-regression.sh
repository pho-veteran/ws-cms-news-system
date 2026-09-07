#!/bin/sh
# =============================================================================
# Run the isolated PGDS article-editor regression suite through WP-CLI.
# =============================================================================
set -eu

TEST_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WP_PATH="$(CDPATH= cd -- "$TEST_DIR/../.." && pwd)"
VERIFY="$TEST_DIR/cms-editor-regression.php"
WP="wp --path=$WP_PATH --allow-root"

if [ ! -f "$VERIFY" ]; then
  echo "ERROR: CMS editor regression verifier is missing from the synchronized tools package. Re-run ./sync.sh." >&2
  exit 1
fi

$WP core is-installed >/dev/null
$WP eval-file "$VERIFY"

echo "==> CMS editor regression suite passed."
