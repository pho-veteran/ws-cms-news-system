#!/bin/sh
# php -l toan bo file PHP cua theme + mu-plugin. Fail neu co loi cu phap.
# Chay: docker compose run --rm wpcli /scripts/lint.sh
set -e

ERRORS=0
echo "==> php -l theme pgds + mu-plugins..."
for f in $(find /var/www/html/wp-content/themes/pgds /var/www/html/wp-content/mu-plugins -name '*.php'); do
  if ! php -l "$f" >/dev/null 2>&1; then
    echo "LOI: $f"
    php -l "$f" || true
    ERRORS=$((ERRORS+1))
  fi
done

if [ "$ERRORS" -gt 0 ]; then
  echo "==> CO $ERRORS FILE LOI CU PHAP."
  exit 1
fi
echo "==> Tat ca file PHP pass php -l."
