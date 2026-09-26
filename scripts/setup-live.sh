#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FRESH=0
case "${1:-}" in
  '') ;;
  --fresh-install) FRESH=1 ;;
  *) echo 'Usage: bash scripts/setup-live.sh [--fresh-install]' >&2; exit 2 ;;
esac
if [ "$#" -gt 1 ]; then echo 'Unexpected arguments.' >&2; exit 2; fi
if [ "$FRESH" -eq 1 ] && [ -e "$ROOT/application/config/app-config.php" ]; then
  echo 'Fresh installation refused: configuration already exists. Use the upgrade/restore procedure.' >&2
  exit 2
fi

echo "== Khach Tot CRM live setup =="
echo "Root: $ROOT"

# Fail before creating configuration or changing permissions.
php "$ROOT/scripts/deploy-preflight.php"
umask 027

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

if [ "$FRESH" -eq 0 ] && [ ! -f "$ROOT/application/config/app-config.php" ] && [ -f "$ROOT/application/config/app-config.sample.php" ]; then
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
for dir in uploads media temp application/cache application/logs modules/kt_saas/storage \
  modules/kt_saas/tenant_bootstrap/manifests modules/kt_saas/tenant_bootstrap/runtime modules/kt_saas/tenant_bootstrap/cache; do
  find "$ROOT/$dir" -type d -exec chmod 750 {} +
  find "$ROOT/$dir" -type f -exec chmod 640 {} +
done
# Configure ownership explicitly for PHP-FPM before invoking this script.
# Source, tenant bootstrap PHP and vendor trees must remain non-writable by the web user.
if [ -f "$ROOT/application/config/app-config.php" ]; then
  chmod 640 "$ROOT/application/config/app-config.php"
fi

echo "PHP version:"
php -v | head -n 1 || true

echo "Checking PHP extensions..."
php -m | egrep -i "mysqli|mbstring|curl|openssl|zip|gd|intl|fileinfo|xml|dom|simplexml|json" || true

echo "Done."
echo "Next steps:"
if [ "$FRESH" -eq 1 ]; then
  echo '1. Configuration intentionally absent; obtain the verified installer schema before running the private installer.'
else
  echo '1. Restore/review application/config/app-config.php; this does not certify a fresh installation.'
fi
echo "2. Verify application/config/database.php and config.php"
echo "3. Follow the separate fresh-install or backed-up upgrade procedure; do not import an unverified dump"
echo "4. Configure web server and Cloudflare"
