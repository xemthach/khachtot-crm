#!/usr/bin/env bash
set -e

ROOT="${1:-/var/www/khachtot}"

echo "Checking runtime folders in $ROOT"

for dir in \
  "$ROOT/uploads" \
  "$ROOT/media" \
  "$ROOT/temp" \
  "$ROOT/application/cache" \
  "$ROOT/application/logs" \
  "$ROOT/modules/kt_saas/storage" \
  "$ROOT/modules/kt_saas/tenant_bootstrap"
do
  if [ ! -d "$dir" ]; then
    echo "MISSING: $dir"
  elif [ ! -w "$dir" ]; then
    echo "NOT WRITABLE: $dir"
  else
    echo "OK: $dir"
  fi
done
