#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Setting up Khach Tot CRM runtime folders in: $ROOT"

mkdir -p \
  "$ROOT/uploads" \
  "$ROOT/media" \
  "$ROOT/temp" \
  "$ROOT/application/cache" \
  "$ROOT/application/logs" \
  "$ROOT/modules/kt_saas/storage" \
  "$ROOT/modules/kt_saas/storage/backups" \
  "$ROOT/modules/kt_saas/tenant_bootstrap/manifests" \
  "$ROOT/modules/kt_saas/tenant_bootstrap/runtime" \
  "$ROOT/modules/kt_saas/tenant_bootstrap/cache"

touch \
  "$ROOT/uploads/index.html" \
  "$ROOT/media/index.html" \
  "$ROOT/application/cache/index.html" \
  "$ROOT/application/logs/index.html" \
  "$ROOT/modules/kt_saas/storage/index.html"

if [ ! -f "$ROOT/application/config/app-config.php" ] && [ -f "$ROOT/application/config/app-config.sample.php" ]; then
  cp "$ROOT/application/config/app-config.sample.php" "$ROOT/application/config/app-config.php"
  echo "Created application/config/app-config.php from sample"
else
  echo "Kept existing application/config/app-config.php"
fi

if [ ! -f "$ROOT/application/config/database.php" ] && [ -f "$ROOT/application/config/database.example.php" ]; then
  cp "$ROOT/application/config/database.example.php" "$ROOT/application/config/database.php"
  echo "Created application/config/database.php from example"
else
  echo "Kept existing application/config/database.php"
fi

if [ ! -f "$ROOT/application/config/config.php" ] && [ -f "$ROOT/application/config/config.example.php" ]; then
  cp "$ROOT/application/config/config.example.php" "$ROOT/application/config/config.php"
  echo "Created application/config/config.php from example"
else
  echo "Kept existing application/config/config.php"
fi

chmod -R 775 \
  "$ROOT/uploads" \
  "$ROOT/media" \
  "$ROOT/temp" \
  "$ROOT/application/cache" \
  "$ROOT/application/logs" \
  "$ROOT/modules/kt_saas/storage" \
  "$ROOT/modules/kt_saas/tenant_bootstrap"

echo
echo "Runtime setup complete."
echo "Next steps:"
echo "1. Edit application/config/app-config.php, database.php, and config.php."
echo "2. Import the landlord database."
echo "3. Configure Nginx/Cloudflare and run scripts/live-smoke-check.sh."
