# Khach Tot CRM Deployment Guide V2

## 1. Dependency policy

This project does not include `composer.json` at the repository root.

Runtime dependencies are bundled inside:

- `application/vendor`
- `modules/backup/vendor`
- `modules/einvoice/vendor`
- `modules/openai/vendor`
- `modules/surveys/vendor`

If these bundled vendor folders are missing, the app can return HTTP 500.

For this baseline, deployment policy is:

```text
COMMIT_VENDOR
```

Do not run `composer install` from the web root.

## 2. Live path

```text
/home/khachtotcom/khachtot.com/public_html
```

## 3. Git repo

```text
https://github.com/xemthach/khachtot-crm
```

## 4. Clone fresh

```bash
cd /home/khachtotcom/khachtot.com
mv public_html public_html_old_$(date +%Y%m%d_%H%M%S)
git clone https://github.com/xemthach/khachtot-crm public_html
cd public_html
git checkout v0.9.2-live-deploy-v2
bash scripts/setup-live.sh
```

## 5. Config

```bash
nano application/config/app-config.php
nano application/config/database.php
nano application/config/config.php
```

Set:

```text
APP_BASE_URL=https://khachtot.com/
APP_DB_HOSTNAME=localhost
APP_DB_USERNAME=LIVE_DB_USER
APP_DB_PASSWORD=LIVE_DB_PASSWORD
APP_DB_NAME=LIVE_DB_NAME
```

Use the correct encryption key for the imported data:

```text
APP_ENC_KEY=<the same key used by the source environment>
```

Without the correct encryption key, encrypted data and some auth flows can break.

## 6. Import DB

Upload the landlord seed file to the server, for example:

```text
/home/khachtotcom/khachtot.com/khachtot_live_seed.sql
```

Import:

```bash
mysql -u LIVE_DB_USER -p --default-character-set=utf8mb4 LIVE_DB_NAME < /home/khachtotcom/khachtot.com/khachtot_live_seed.sql
```

Verify:

```bash
mysql -u LIVE_DB_USER -p LIVE_DB_NAME -e "SHOW TABLES LIKE 'tbloptions';"
```

## 7. Update local URLs

Check local URLs imported from staging/local:

```sql
SELECT name, value
FROM tbloptions
WHERE value LIKE '%khachtot.test%'
   OR value LIKE '%localhost%'
   OR value LIKE '%127.0.0.1%';
```

Update:

```sql
UPDATE tbloptions
SET value = REPLACE(value, 'https://khachtot.test/', 'https://khachtot.com/')
WHERE value LIKE '%https://khachtot.test/%';

UPDATE tbloptions
SET value = REPLACE(value, 'http://khachtot.test/', 'https://khachtot.com/')
WHERE value LIKE '%http://khachtot.test/%';
```

## 8. Nginx

Document root:

```text
/home/khachtotcom/khachtot.com/public_html
```

Server names:

```text
khachtot.com www.khachtot.com *.khachtot.com
```

Critical block:

```nginx
location ~* ^/(uploads|temp|storage|backups)/.*\.(php|phtml|php3|php4|php5|php7|phar|cgi|pl|py|sh)$ {
    return 403;
}
```

If the server uses a hosting panel, make sure the panel does not overwrite the custom vhost.

## 9. Cloudflare

DNS:

```text
A      @      SERVER_IP
CNAME  www    khachtot.com
A      *      SERVER_IP
```

SSL:

```text
Full Strict
```

Cache bypass:

```text
/admin*
/authentication*
/clients*
/kt_saas*
/kt_sepay*
/kt_matbao_invoice*
/api*
/webhook*
/cron*
```

## 10. Diagnose 500

```bash
cd /home/khachtotcom/khachtot.com/public_html
bash scripts/live-diagnose.sh
tail -n 100 /var/log/nginx/error.log
```

Common blockers:

- missing bundled vendor folders
- wrong DB credentials
- wrong PHP version
- missing PHP extensions
- runtime folders not writable
- local URL still stored in DB
- wrong document root
- wildcard DNS or vhost not configured

## 11. Smoke test

```bash
curl -I https://khachtot.com/
curl -I https://khachtot.com/signup
curl -I https://khachtot.com/pricing
curl -I https://khachtot.com/admin
curl -I https://sme-mini.khachtot.com/
```

Expected:

- no HTTP 500
- app logs writable
- tenant status page for not-ready tenant
- landlord homepage works
