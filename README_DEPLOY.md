# Khach Tot CRM Quick Deploy

```bash
cd /home/khachtotcom/khachtot.com
mv public_html public_html_old_$(date +%Y%m%d_%H%M%S)
git clone https://github.com/xemthach/khachtot-crm public_html
cd public_html
git checkout v0.9.2-live-deploy-v2
bash scripts/setup-live.sh
```

Edit config:

```bash
nano application/config/app-config.php
nano application/config/database.php
nano application/config/config.php
```

Import DB:

```bash
mysql -u LIVE_DB_USER -p --default-character-set=utf8mb4 LIVE_DB_NAME < khachtot_live_seed.sql
```

Diagnose:

```bash
bash scripts/live-diagnose.sh
```

Important:

- This project requires bundled vendor folders because there is no root `composer.json`.
- Required runtime dependencies live in `application/vendor` and some `modules/*/vendor` directories.
- If bundled vendor folders are missing, live can return HTTP 500.
