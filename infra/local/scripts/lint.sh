#!/bin/sh
# Run php -l on all PHP files in the theme, mu-plugins, and editor suite.
# Run: ./sync.sh && docker compose run --rm wpcli /var/www/html/.pgds-scripts/lint.sh
set -e

ERRORS=0
echo "==> Running php -l on the pgds theme, mu-plugins, and CMS editor regression suite..."
for f in $(find /var/www/html/wp-content/themes/pgds /var/www/html/wp-content/mu-plugins /var/www/html/.pgds-tools/tests -name '*.php'); do
  if ! php -l "$f" >/dev/null 2>&1; then
    echo "ERROR: $f"
    php -l "$f" || true
    ERRORS=$((ERRORS+1))
  fi
done

if [ "$ERRORS" -gt 0 ]; then
  echo "==> $ERRORS FILE(S) HAVE SYNTAX ERRORS."
  exit 1
fi
echo "==> All PHP files passed php -l."
