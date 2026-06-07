#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "== Khach Tot CRM live setup =="
echo "Root: $ROOT"

echo "Creating runtime folders..."
mkdir -p "$ROOT/uploads"
mkdir -p "$ROOT/media"
mkdir -p "$ROOT/temp"
mkdir -p "$ROOT/application/cache"
mkdir -p "$ROOT/application/logs"
mkdir -p "$ROOT/modules/kt_saas/storage"
mkdir -p "$ROOT/modules/kt_saas/storage/backups"
mkdir -p "$ROOT/modules/kt_saas/tenant_bootstrap/manifests"
mkdir -p "$ROOT/modules/kt_saas/tenant_bootstrap/runtime"
mkdir -p "$ROOT/modules/kt_saas/tenant_bootstrap/cache"

touch "$ROOT/uploads/index.html"
touch "$ROOT/media/index.html"
touch "$ROOT/application/cache/index.html"
touch "$ROOT/application/logs/index.html"
touch "$ROOT/modules/kt_saas/storage/index.html"
touch "$ROOT/modules/kt_saas/tenant_bootstrap/index.html"

if [ ! -f "$ROOT/application/config/app-config.php" ] && [ -f "$ROOT/application/config/app-config.sample.php" ]; then
  cp "$ROOT/application/config/app-config.sample.php" "$ROOT/application/config/app-config.php"
  echo "Created application/config/app-config.php"
fi

if [ ! -f "$ROOT/application/config/database.php" ] && [ -f "$ROOT/application/config/database.example.php" ]; then
  cp "$ROOT/application/config/database.example.php" "$ROOT/application/config/database.php"
  echo "Created application/config/database.php"
fi

if [ ! -f "$ROOT/application/config/config.php" ] && [ -f "$ROOT/application/config/config.example.php" ]; then
  cp "$ROOT/application/config/config.example.php" "$ROOT/application/config/config.php"
  echo "Created application/config/config.php"
fi

echo "Checking dependency policy..."
if [ -f "$ROOT/composer.json" ]; then
  echo "Root composer.json found. Review deployment policy before running composer install."
else
  echo "Root composer.json not found."
fi

required_vendors=(
  "$ROOT/application/vendor"
  "$ROOT/modules/backup/vendor"
  "$ROOT/modules/einvoice/vendor"
  "$ROOT/modules/openai/vendor"
  "$ROOT/modules/surveys/vendor"
)

for dir in "${required_vendors[@]}"; do
  if [ ! -d "$dir" ]; then
    echo "ERROR: required bundled dependency directory is missing: $dir"
    exit 2
  fi
done

echo "Setting permissions..."
chmod -R 775 "$ROOT/uploads" || true
chmod -R 775 "$ROOT/media" || true
chmod -R 775 "$ROOT/temp" || true
chmod -R 775 "$ROOT/application/cache" || true
chmod -R 775 "$ROOT/application/logs" || true
chmod -R 775 "$ROOT/modules/kt_saas/storage" || true
chmod -R 775 "$ROOT/modules/kt_saas/tenant_bootstrap" || true

chmod -R 755 "$ROOT/application/vendor" || true
chmod -R 755 "$ROOT/modules/backup/vendor" || true
chmod -R 755 "$ROOT/modules/einvoice/vendor" || true
chmod -R 755 "$ROOT/modules/openai/vendor" || true
chmod -R 755 "$ROOT/modules/surveys/vendor" || true

echo "PHP version:"
php -v | head -n 1 || true

echo "Checking PHP extensions..."
php -m | egrep -i "mysqli|mbstring|curl|openssl|zip|gd|intl|fileinfo|xml|dom|simplexml|json" || true

echo "Done."
echo "Next steps:"
echo "1. Edit application/config/app-config.php"
echo "2. Verify application/config/database.php and config.php"
echo "3. Import landlord database"
echo "4. Configure web server and Cloudflare"
