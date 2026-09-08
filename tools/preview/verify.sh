#!/bin/sh
# =============================================================================
# Assert the development-only golden preview dataset after seed.sh.
# =============================================================================
set -eu

PREVIEW_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WP_PATH="$(CDPATH= cd -- "$PREVIEW_DIR/../.." && pwd)"
WP="wp --path=$WP_PATH --allow-root"
VERIFY="$PREVIEW_DIR/verify.php"

if [ ! -f "$VERIFY" ]; then
  echo "ERROR: preview verifier is missing from the synchronized tools package. Re-run ./sync.sh." >&2
  exit 1
fi

$WP eval-file "$VERIFY"

echo "==> Preview dataset verification passed."
