#!/usr/bin/env bash
set -Eeuo pipefail

MODE=plan
CONFIG=
usage() { echo "Usage: sudo bash scripts/install-live.sh --config /root/khachtot-install.env [--plan|--apply]"; }
while [[ $# -gt 0 ]]; do
  case "$1" in
    --config) CONFIG="${2:?Missing config path}"; shift 2 ;;
    --plan) MODE=plan; shift ;;
    --apply) MODE=apply; shift ;;
    --help|-h) usage; exit 0 ;;
    *) usage >&2; exit 2 ;;
  esac
done

[[ "$(id -u)" -eq 0 ]] || { echo 'Run as root or with sudo.' >&2; exit 2; }
[[ -n "$CONFIG" && -f "$CONFIG" ]] || { echo '--config file is required.' >&2; exit 2; }
umask 077
# shellcheck disable=SC1090
source "$CONFIG"

: "${APP_DOMAIN:?APP_DOMAIN is required}"
: "${PROJECT_PATH:?PROJECT_PATH is required}"
: "${DEPLOY_USER:?DEPLOY_USER is required}"
: "${DEPLOY_GROUP:?DEPLOY_GROUP is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${APP_ENC_KEY:?APP_ENC_KEY is required}"
: "${SYNC_COMMIT:?SYNC_COMMIT is required}"

REPO_URL="${REPO_URL:-https://github.com/xemthach/khachtot-crm.git}"
APP_SUBDOMAINS="${APP_SUBDOMAINS:-}"
PHP_FPM_SOCKET="${PHP_FPM_SOCKET:-}"
NGINX_SITE="${NGINX_SITE:-/etc/nginx/sites-available/$APP_DOMAIN.conf}"
NGINX_LINK="${NGINX_LINK:-/etc/nginx/sites-enabled/$APP_DOMAIN.conf}"
CRON_LOG_PATH="${CRON_LOG_PATH:-/var/log/khachtot}"
CREATE_DATABASE="${CREATE_DATABASE:-false}"

die() { echo "ERROR: $1" >&2; exit 2; }
run() { printf '+ %s\n' "$*"; [[ "$MODE" == apply ]] && "$@"; }
valid_domain() { [[ "$1" =~ ^[A-Za-z0-9.-]+$ ]]; }
valid_name() { [[ "$1" =~ ^[A-Za-z0-9._/@:+-]+$ ]]; }

valid_domain "$APP_DOMAIN" || die 'APP_DOMAIN is invalid'
valid_name "$PROJECT_PATH" || die 'PROJECT_PATH is invalid'
valid_name "$DB_NAME" || die 'DB_NAME is invalid'
valid_name "$DB_USER" || die 'DB_USER is invalid'
[[ ${#APP_ENC_KEY} -ge 32 ]] || die 'APP_ENC_KEY must be at least 32 characters'
[[ ! -e "$PROJECT_PATH" ]] || die 'PROJECT_PATH already exists; installer refuses to overwrite it'

command -v git >/dev/null || die 'git is missing'
command -v php >/dev/null || die 'php is missing'
command -v nginx >/dev/null || die 'nginx is missing'
command -v install >/dev/null || die 'install is missing'

echo "Mode: $MODE"
echo "Domain: $APP_DOMAIN"
echo "Project: $PROJECT_PATH"
echo "Commit: $SYNC_COMMIT"
echo 'Secrets are not printed.'

run git clone --branch main --single-branch "$REPO_URL" "$PROJECT_PATH"
run git -C "$PROJECT_PATH" fetch --no-tags origin main
run git -C "$PROJECT_PATH" checkout --detach "$SYNC_COMMIT"

if [[ "$MODE" == apply ]]; then
  install -d -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" -m 0750 "$PROJECT_PATH/uploads" "$PROJECT_PATH/media" "$PROJECT_PATH/temp" "$PROJECT_PATH/application/cache" "$PROJECT_PATH/application/logs" "$PROJECT_PATH/modules/kt_saas/storage"
  install -m 0640 -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" "$PROJECT_PATH/application/config/app-config.sample.php" "$PROJECT_PATH/application/config/app-config.php"
  install -m 0640 -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" "$PROJECT_PATH/application/config/database.example.php" "$PROJECT_PATH/application/config/database.php"
  install -m 0640 -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" "$PROJECT_PATH/application/config/config.example.php" "$PROJECT_PATH/application/config/config.php"
fi

if [[ "$MODE" == apply ]]; then
  php -r '$p=$argv[1]; $v=$argv[2]; $s=file_get_contents($p); $s=str_replace("https://khachtot.com/", "https://".$v."/", $s); file_put_contents($p,$s);' "$PROJECT_PATH/application/config/config.php" "$APP_DOMAIN"
  APP_DB_PASSWORD="$DB_PASSWORD" APP_ENC_KEY_VALUE="$APP_ENC_KEY" php -r '$p=$argv[1]; $h=$argv[2]; $u=$argv[3]; $pw=getenv("APP_DB_PASSWORD"); $n=$argv[4]; $s=file_get_contents($p); $s=preg_replace("/\x27hostname\x27\s*=>\s*\x27[^\x27]*\x27/", "\x27hostname\x27 => \x27".$h."\x27", $s); $s=preg_replace("/\x27username\x27\s*=>\s*\x27[^\x27]*\x27/", "\x27username\x27 => \x27".$u."\x27", $s); $s=preg_replace("/\x27password\x27\s*=>\s*\x27[^\x27]*\x27/", "\x27password\x27 => \x27".$pw."\x27", $s); $s=preg_replace("/\x27database\x27\s*=>\s*\x27[^\x27]*\x27/", "\x27database\x27 => \x27".$n."\x27", $s); file_put_contents($p,$s);' "$PROJECT_PATH/application/config/database.php" "$DB_HOST" "$DB_USER" "$DB_NAME"
  APP_DB_PASSWORD="$DB_PASSWORD" APP_ENC_KEY_VALUE="$APP_ENC_KEY" php -r '$p=$argv[1]; $h=$argv[2]; $u=$argv[3]; $pw=getenv("APP_DB_PASSWORD"); $n=$argv[4]; $k=getenv("APP_ENC_KEY_VALUE"); $s=file_get_contents($p); foreach (["APP_DB_HOSTNAME"=>$h,"APP_DB_USERNAME"=>$u,"APP_DB_PASSWORD"=>$pw,"APP_DB_NAME"=>$n,"APP_ENC_KEY"=>$k] as $key=>$value) { $s=preg_replace("/(define\(\x27".$key."\x27,\s*\x27)[^\x27]*(\x27\);)/", "$1".addslashes($value)."$2", $s); } file_put_contents($p,$s);' "$PROJECT_PATH/application/config/app-config.php" "$DB_HOST" "$DB_USER" "$DB_NAME"
  chown -R "$DEPLOY_USER:$DEPLOY_GROUP" "$PROJECT_PATH"
fi

if [[ "$CREATE_DATABASE" == true ]]; then
  : "${DB_ADMIN_USER:?DB_ADMIN_USER is required when CREATE_DATABASE=true}"
  : "${DB_ADMIN_PASSWORD:?DB_ADMIN_PASSWORD is required when CREATE_DATABASE=true}"
  command -v mysql >/dev/null || die 'mysql client is missing'
  if [[ "$MODE" == apply ]]; then
    MYSQL_TMP=$(mktemp)
    trap 'rm -f "$MYSQL_TMP"' EXIT
    chmod 600 "$MYSQL_TMP"
    printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DB_ADMIN_USER" "$DB_ADMIN_PASSWORD" "$DB_HOST" > "$MYSQL_TMP"
    mysql --defaults-extra-file="$MYSQL_TMP" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"
  else
    echo 'PLAN: database creation requested; credentials will not be printed.'
  fi
fi

if [[ -z "$PHP_FPM_SOCKET" ]]; then
  PHP_FPM_SOCKET=$(find /run/php /var/run/php -maxdepth 1 -type s -name 'php*-fpm.sock' -print -quit 2>/dev/null || true)
fi
[[ -n "$PHP_FPM_SOCKET" ]] || die 'PHP_FPM_SOCKET is required or no socket was found'

if [[ "$MODE" == apply ]]; then
  NGINX_TMP=$(mktemp)
  trap 'rm -f "$NGINX_TMP"' EXIT
  cat > "$NGINX_TMP" <<EOF
server {
    listen 80;
    server_name $APP_DOMAIN $APP_SUBDOMAINS;
    root $PROJECT_PATH;
    index index.php;
    location / { try_files \$uri \$uri/ /index.php\$is_args\$args; }
    location ~* ^/(uploads|temp|storage|backups)/.*\.(php|phtml|phar|cgi|pl|py|sh)\$ { return 403; }
    location ~ \.php\$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCKET; }
    location ~ /\.ht { deny all; }
}
EOF
  install -o root -g root -m 0644 "$NGINX_TMP" "$NGINX_SITE"
  [[ ! -e "$NGINX_LINK" ]] && ln -s "$NGINX_SITE" "$NGINX_LINK"
  nginx -t
  systemctl reload nginx
  install -d -o "$DEPLOY_USER" -g "$DEPLOY_GROUP" -m 0750 "$CRON_LOG_PATH"
fi

echo 'Installer completed. Configure TLS/Cloudflare and review database schema/import before traffic.'
