#!/usr/bin/env bash
set -Eeuo pipefail

[[ "$(id -u)" -eq 0 ]] || { echo 'Run as root.' >&2; exit 2; }
command -v php >/dev/null || { echo 'PHP CLI is missing.' >&2; exit 2; }

HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8090}"
TOKEN="${KT_INSTALLER_TOKEN:-$(openssl rand -hex 24)}"
export KT_INSTALLER_TOKEN="$TOKEN"

echo "Web installer: http://${HOST}:${PORT}/"
echo "SSH tunnel from your workstation:"
echo "ssh -L ${PORT}:127.0.0.1:${PORT} <user>@<server>"
echo "Installer token (store privately): ${TOKEN}"
echo "Stop with Ctrl-C. The installer locks itself after apply."

exec php -S "${HOST}:${PORT}" -t "$(cd "$(dirname "$0")/../tools/live-installer-web" && pwd)"
