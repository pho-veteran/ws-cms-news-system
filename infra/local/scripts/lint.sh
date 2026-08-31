#!/bin/sh
# Run php -l on all PHP files in the theme and mu-plugin. Fail on syntax errors.
# Run: docker compose run --rm wpcli /scripts/lint.sh
set -e

ERRORS=0
echo "==> Running php -l on the pgds theme and mu-plugins..."
for f in $(find /var/www/html/wp-content/themes/pgds /var/www/html/wp-content/mu-plugins -name '*.php'); do
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
