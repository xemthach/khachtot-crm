#!/usr/bin/env bash
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "== Khach Tot CRM live diagnose =="
echo "Root: $ROOT"

echo "-- PHP --"
php -v || true

echo "-- PHP extensions --"
php -m | egrep -i "mysqli|mbstring|curl|openssl|zip|gd|intl|fileinfo|xml|dom|simplexml|json" || true

echo "-- Dependency policy --"
[ -f "$ROOT/composer.json" ] && echo "root composer.json: YES" || echo "root composer.json: NO"
[ -f "$ROOT/application/composer.json" ] && echo "application composer.json: YES" || echo "application composer.json: NO"

for d in \
  "$ROOT/application/vendor" \
  "$ROOT/modules/backup/vendor" \
  "$ROOT/modules/einvoice/vendor" \
  "$ROOT/modules/openai/vendor" \
  "$ROOT/modules/surveys/vendor"
do
  if [ -d "$d" ]; then
    echo "vendor OK: $d"
  else
    echo "vendor MISSING: $d"
  fi
done

echo "-- Runtime writable --"
for d in \
  "$ROOT/uploads" \
  "$ROOT/application/cache" \
  "$ROOT/application/logs" \
  "$ROOT/modules/kt_saas/storage" \
  "$ROOT/modules/kt_saas/tenant_bootstrap"
do
  if [ ! -d "$d" ]; then
    echo "MISSING: $d"
  elif [ ! -w "$d" ]; then
    echo "NOT_WRITABLE: $d"
  else
    echo "OK: $d"
  fi
done

echo "-- Config files --"
[ -f "$ROOT/application/config/app-config.php" ] && echo "app-config.php: YES" || echo "app-config.php: NO"
[ -f "$ROOT/application/config/database.php" ] && echo "database.php: YES" || echo "database.php: NO"
[ -f "$ROOT/application/config/config.php" ] && echo "config.php: YES" || echo "config.php: NO"

echo "-- App logs --"
ls -lh "$ROOT/application/logs" || true
tail -n 80 "$ROOT"/application/logs/*.php 2>/dev/null || true

echo "-- Lint index.php --"
php -l "$ROOT/index.php" || true

echo "Done."
