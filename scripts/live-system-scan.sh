#!/usr/bin/env bash
set -u

ROOT="${1:-$(pwd)}"
OUTPUT="${2:-}"

if [[ ! -d "$ROOT" ]]; then
  echo "ERROR: project path does not exist: $ROOT" >&2
  exit 2
fi

if [[ -n "$OUTPUT" ]]; then
  mkdir -p "$(dirname "$OUTPUT")"
  exec > >(tee "$OUTPUT") 2>&1
fi

redact() {
  sed -E 's/((PASSWORD|PASS|SECRET|TOKEN|API_KEY|APP_KEY|ENC_KEY|PRIVATE_KEY)[[:space:]]*[=:][[:space:]]*).*/\1[REDACTED]/I'
}

section() { printf '\n== %s ==\n' "$1"; }

echo "Khach Tot CRM live system scan"
echo "Timestamp: $(date -Is)"
echo "Project path: $ROOT"
echo "This scan is read-only and omits secret values."

section "Operating system"
cat /etc/os-release 2>/dev/null || true
uname -a || true
id || true

section "Runtime binaries"
for command_name in git php nginx mysql mariadb curl openssl; do
  if command -v "$command_name" >/dev/null 2>&1; then
    printf '%s: %s\n' "$command_name" "$(command -v "$command_name")"
    case "$command_name" in
      php) php -v 2>/dev/null | head -n 1 ;;
      nginx) nginx -v 2>&1 | head -n 1 ;;
      mysql|mariadb) "$command_name" --version 2>/dev/null | head -n 1 ;;
    esac
  else
    printf '%s: MISSING\n' "$command_name"
  fi
done

section "PHP extensions"
php -m 2>/dev/null | sort | grep -Ei 'mysqli|pdo|mbstring|curl|openssl|zip|gd|fileinfo|xml|dom|simplexml|json|imap|bcmath|ctype|filter|hash|xmlwriter|iconv' || true

section "Services"
for service in nginx php8.1-fpm php8.2-fpm php8.3-fpm php8.4-fpm mysql mariadb; do
  if command -v systemctl >/dev/null 2>&1; then
    printf '%s: ' "$service"
    systemctl is-active "$service" 2>/dev/null || true
  fi
done

section "PHP-FPM sockets"
find /run/php /var/run/php -maxdepth 1 -type s -name 'php*-fpm.sock' -print 2>/dev/null || true

section "Nginx relevant configuration"
if command -v nginx >/dev/null 2>&1; then
  nginx -T 2>/dev/null | grep -E '^[[:space:]]*(listen|server_name|root|fastcgi_pass)[[:space:]]' | redact || true
fi

section "Git"
if [[ -d "$ROOT/.git" ]]; then
  git -C "$ROOT" status --short --branch || true
  git -C "$ROOT" remote -v || true
  git -C "$ROOT" rev-parse HEAD 2>/dev/null || true
  git -C "$ROOT" rev-parse origin/main 2>/dev/null || true
else
  echo "Git repository: MISSING"
fi

section "Project identity"
for file in VERSION README.md package.json application/config/app-config.php application/config/database.php application/config/config.php; do
  if [[ -f "$ROOT/$file" ]]; then
    printf '%s: PRESENT\n' "$file"
  else
    printf '%s: MISSING\n' "$file"
  fi
done
if [[ -f "$ROOT/VERSION" ]]; then
  printf 'VERSION: %s\n' "$(head -n 1 "$ROOT/VERSION")"
fi

section "Dependency directories"
for path in application/vendor modules/backup/vendor modules/einvoice/vendor modules/openai/vendor modules/surveys/vendor; do
  [[ -d "$ROOT/$path" ]] && echo "PRESENT $path" || echo "MISSING $path"
done

section "Runtime directories and permissions"
for path in uploads media temp application/cache application/logs modules/kt_saas/storage modules/kt_saas/tenant_bootstrap; do
  if [[ -d "$ROOT/$path" ]]; then
    stat -c '%A %U:%G %n' "$ROOT/$path" 2>/dev/null || ls -ld "$ROOT/$path"
  else
    echo "MISSING $ROOT/$path"
  fi
done

section "Config presence only"
for file in .env application/config/app-config.php application/config/database.php application/config/config.php; do
  [[ -f "$ROOT/$file" ]] && echo "PRESENT $file (contents omitted)" || echo "MISSING $file"
done

section "Disk and network"
df -h "$ROOT" 2>/dev/null || true
getent hosts "${APP_DOMAIN:-localhost}" 2>/dev/null || true

echo
echo "Scan completed. Review privately; do not paste secret values or customer data into tickets or GitHub."
