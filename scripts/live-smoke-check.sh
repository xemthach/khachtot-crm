#!/usr/bin/env bash
set -euo pipefail

BASE="${1:?Usage: bash scripts/live-smoke-check.sh https://<DOMAIN>}"
BASE="${BASE%/}"
case "$BASE" in
  https://*|http://127.0.0.1:*|http://localhost:*) ;;
  *) echo 'Use HTTPS, or an explicit loopback HTTP test endpoint.' >&2; exit 2 ;;
esac

echo "Smoke checking $BASE"

for route in / /signup /pricing /admin; do
  # GET follows the application request path. Do not follow redirects to another host.
  status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
    --connect-timeout 5 --max-time 20 "$BASE$route")"
  case "$status" in
    2??|301|302|303|307|308) echo "$route HTTP $status" ;;
    *) echo "FAIL: $route HTTP $status" >&2; exit 1 ;;
  esac
done

echo 'HTTP smoke passed; login, tenant scope and worker checks are separate gates.'
