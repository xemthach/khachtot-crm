# Khach Tot CRM Deployment Guide

## 1. Server Requirements

- Ubuntu 22.04/24.04 or compatible Linux
- Nginx
- MySQL/MariaDB with utf8mb4
- PHP compatible with the current Perfex/CodeIgniter runtime
- PHP extensions: mysqli, mbstring, curl, json, openssl, zip, gd, intl, fileinfo, xml, dom, simplexml, tokenizer, bcmath
- Git
- Cron enabled
- Composer only if deployment policy changes to install dependencies on server

## 2. Domains

Production domains:

- `khachtot.com`
- `*.khachtot.com`

Cloudflare:

- `A khachtot.com -> SERVER_IP`
- `CNAME www -> khachtot.com`
- `A * -> SERVER_IP` or `CNAME * -> khachtot.com`
- SSL/TLS mode: Full or Full Strict after origin SSL is ready

## 3. Clone Code

```bash
cd /var/www
git clone https://github.com/<github_user>/khachtot-crm.git khachtot
cd /var/www/khachtot
git checkout v0.9.0-pre-integration
```

## 4. Configure Application

Create production config from the sample:

```bash
cp application/config/app-config.sample.php application/config/app-config.php
```

Edit:

```text
application/config/app-config.php
```

Set:

```text
APP_BASE_URL=https://khachtot.com/
APP_ENC_KEY=<production encryption key>
APP_DB_HOSTNAME=localhost
APP_DB_USERNAME=khachtot_user
APP_DB_PASSWORD=<strong password>
APP_DB_NAME=khachtot
```

Never commit production credentials.

## 5. Install Dependencies

Skip Composer for this baseline.

Reason:

- There is no root `composer.json` in this repository.
- Perfex runtime dependencies are already committed under `application/vendor` and module vendor folders.
- Running `composer install` from the web root will fail with `Composer could not find a composer.json file`.

Do not run Composer as `root` on production for this baseline.

If the dependency policy changes later, first confirm a root `composer.json` exists:

```bash
test -f composer.json && composer install --no-dev --optimize-autoloader
```

If `composer.json` does not exist, continue with database/config/permissions setup.

## 6. Configure Database

Create landlord DB:

```sql
CREATE DATABASE khachtot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'khachtot_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON khachtot.* TO 'khachtot_user'@'localhost';
FLUSH PRIVILEGES;
```

Import landlord DB:

```bash
mysql -u khachtot_user -p --default-character-set=utf8mb4 khachtot < khachtot_landlord_backup.sql
```

Tenant databases are runtime data and should be imported/restored separately when migrating real tenants.

## 7. File Permissions

```bash
chown -R www-data:www-data /var/www/khachtot
find /var/www/khachtot -type d -exec chmod 755 {} \;
find /var/www/khachtot -type f -exec chmod 644 {} \;

chmod -R 775 /var/www/khachtot/uploads
chmod -R 775 /var/www/khachtot/media
chmod -R 775 /var/www/khachtot/temp
chmod -R 775 /var/www/khachtot/application/cache
chmod -R 775 /var/www/khachtot/application/logs
chmod -R 775 /var/www/khachtot/modules/kt_saas/storage
chmod -R 775 /var/www/khachtot/modules/kt_saas/tenant_bootstrap
```

## 8. Nginx Config

```nginx
server {
    listen 80;
    server_name khachtot.com www.khachtot.com *.khachtot.com;
    root /var/www/khachtot;
    index index.php index.html;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* /(application|system|vendor|modules/.*/vendor)/ {
        deny all;
    }

    location ~ /\. {
        deny all;
    }
}
```

Adjust PHP-FPM socket/version for the server.

Enable:

```bash
ln -s /etc/nginx/sites-available/khachtot.com /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## 9. SSL

For wildcard SSL, use DNS challenge or a Cloudflare Origin Certificate.

Let's Encrypt wildcard example:

```bash
certbot certonly --manual --preferred-challenges dns -d khachtot.com -d "*.khachtot.com"
```

Cloudflare Origin Certificate is acceptable with SSL/TLS mode Full Strict.

## 10. Cron

Audit the live cron route before enabling. Common Perfex-style example:

```bash
* * * * * php /var/www/khachtot/index.php cron/index >/dev/null 2>&1
```

Do not add cron blindly if the project uses a different route.

## 11. Cloudflare Cache Bypass

Bypass cache for dynamic paths:

```text
/admin/*
/kt_saas/*
/kt_sepay/*
/api/*
/webhook/*
```

Add these rules after origin SSL and core routes are verified.

## 12. Post-deploy Checks

Landlord:

- `https://khachtot.com/`
- `https://khachtot.com/signup`
- `https://khachtot.com/pricing`
- `https://khachtot.com/admin`

Tenant:

- `https://testtenant.khachtot.com/`
- `https://testtenant.khachtot.com/admin`
- `https://testtenant.khachtot.com/authentication/login`

Billing/integrations:

- signup -> invoice -> checkout -> QR/payment page
- SePay webhook
- MatBao Invoice API credentials
- tenant provisioning
- email onboarding
- SaaS billing period and renewal

Logs:

```bash
tail -f /var/www/khachtot/application/logs/log-*.php
tail -f /var/log/nginx/error.log
```

## 13. Rollback

Code rollback:

```bash
cd /var/www/khachtot
git fetch --tags
git checkout v0.9.0-pre-integration
systemctl reload php*-fpm
systemctl reload nginx
```

DB rollback if needed:

```bash
mysql -u khachtot_user -p --default-character-set=utf8mb4 khachtot < khachtot_landlord_backup.sql
```

Cloudflare rollback:

- set records to DNS only if proxy causes issues
- revert DNS records
- disable cache rules that affect dynamic routes
