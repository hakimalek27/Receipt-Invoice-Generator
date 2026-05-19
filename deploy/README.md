# Tencent Ubuntu Deployment Runbook

Target server: Tencent Ubuntu, `43.133.34.55`.

This runbook is a production checklist. Replace every `REPLACE_WITH_*` value before deployment. Do not run a production cutover until the dry-run, backup, restore drill, queue, scheduler, and SSL checks pass.

## 1. Server Hardening Gate

- SSH key authentication only; disable password login and root login.
- `ufw default deny incoming`; allow only SSH, HTTP, and HTTPS.
- `fail2ban` enabled for `sshd`.
- `unattended-upgrades` enabled for security updates.
- PostgreSQL bound to `127.0.0.1` or private network only, never public `0.0.0.0`.
- Redis bound to local/private network only.
- Server timezone set to `Asia/Kuala_Lumpur`.
- A non-root deploy user can read/write the app path through group permissions.

Recommended firewall commands:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

## 2. Application Deploy

```bash
sudo mkdir -p /var/www/receipt-invoice-generator
sudo chown -R "$USER":www-data /var/www/receipt-invoice-generator
cd /var/www/receipt-invoice-generator
git clone https://github.com/hakimalek27/Receipt-Invoice-Generator.git .
git checkout REPLACE_WITH_RELEASE_BRANCH_OR_TAG
composer install --no-dev --optimize-autoloader
npm ci
npx playwright install --with-deps chromium
npm run build
cp .env.example .env
php artisan key:generate
```

Edit `.env` before migrations:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://REPLACE_WITH_DOMAIN
APP_TIMEZONE=Asia/Kuala_Lumpur
SANCTUM_STATEFUL_DOMAINS=REPLACE_WITH_DOMAIN
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=receipt_invoice_generator
DB_USERNAME=receipt_invoice_generator
DB_PASSWORD=REPLACE_WITH_STRONG_DB_PASSWORD
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
FILESYSTEM_DISK=local
PDF_RENDERER=playwright
PDF_LEGACY_FALLBACK=false
PDF_NODE_BINARY=node
TELEGRAM_WEBHOOK_SECRET=REPLACE_WITH_LONG_RANDOM_SECRET
TELEGRAM_BOT_TOKEN=REPLACE_WITH_TELEGRAM_BOT_TOKEN
TELEGRAM_ALLOWED_CHAT_IDS=REPLACE_WITH_CHAT_ID
TELEGRAM_CHAT_USER_MAP=REPLACE_WITH_CHAT_ID:REPLACE_WITH_APP_USER_ID
DEEPSEEK_API_KEY=REPLACE_WITH_DEEPSEEK_KEY
DEEPSEEK_MODEL=deepseek-chat
```

Then run:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

## 3. Nginx and SSL

Use `deploy/nginx/receipt-invoice-generator.conf` as a sample and replace:

- `REPLACE_WITH_DOMAIN`
- PHP-FPM socket path if the server uses PHP 8.4 instead of PHP 8.3.

Install:

```bash
sudo cp deploy/nginx/receipt-invoice-generator.conf /etc/nginx/sites-available/receipt-invoice-generator
sudo ln -s /etc/nginx/sites-available/receipt-invoice-generator /etc/nginx/sites-enabled/receipt-invoice-generator
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d REPLACE_WITH_DOMAIN
sudo certbot renew --dry-run
```

Set an SSL renewal alert through your monitoring channel. At minimum, add a weekly cron or external uptime monitor that checks certificate expiry.

## 4. Queue and Scheduler

Install the supervisor config:

```bash
sudo cp deploy/supervisor/queue-worker.conf /etc/supervisor/conf.d/rig-queue.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status rig-queue:*
```

Add scheduler cron:

```cron
* * * * * www-data cd /var/www/receipt-invoice-generator && php artisan schedule:run | logger -t rig-scheduler
```

Queue health checks:

```bash
php artisan queue:failed
tail -n 100 storage/logs/queue-worker.log
sudo supervisorctl status rig-queue:*
```

## 4.1 PDF Renderer Gate

Production PDF rendering is Playwright/Chromium. DomPDF is a legacy fallback only and should stay disabled in production unless a rollback note explicitly allows it.

```bash
npx playwright install --with-deps chromium
php artisan rig:pdf-smoke --paper=A4
php artisan rig:pdf-smoke --paper=60mm
```

Pass criteria:

- The command writes a private PDF under `storage/app/private/documents`.
- `PDF_RENDERER=playwright`.
- `PDF_LEGACY_FALLBACK=false`.
- No browser launch or font errors appear in `storage/logs/laravel.log`.

## 5. Backup and Restore

`deploy/backup.sh` backs up:

- PostgreSQL database dump.
- Private generated documents and attachments under `storage/app/private/documents`.
- `.env` config.

All artifacts are encrypted with GPG symmetric encryption. Create a root-readable passphrase file:

```bash
sudo install -m 700 -d /etc/receipt-invoice-generator
sudo install -m 600 /dev/null /etc/receipt-invoice-generator/backup.passphrase
sudo nano /etc/receipt-invoice-generator/backup.passphrase
```

Install backup:

```bash
sudo cp deploy/backup.sh /usr/local/sbin/rig-backup.sh
sudo chmod 750 /usr/local/sbin/rig-backup.sh
sudo BACKUP_PASSPHRASE_FILE=/etc/receipt-invoice-generator/backup.passphrase /usr/local/sbin/rig-backup.sh
```

Add cron only after the first manual backup succeeds:

```cron
15 2 * * * root DB_PASSWORD=REPLACE_WITH_DB_PASSWORD OFFSITE_BACKUP_TARGET=REPLACE_WITH_RSYNC_TARGET BACKUP_PASSPHRASE_FILE=/etc/receipt-invoice-generator/backup.passphrase /usr/local/sbin/rig-backup.sh 2>&1 | logger -t rig-backup
```

Run restore drill after the first backup and monthly:

```bash
sudo cp deploy/restore-drill.sh /usr/local/sbin/rig-restore-drill.sh
sudo chmod 750 /usr/local/sbin/rig-restore-drill.sh
sudo BACKUP_PASSPHRASE_FILE=/etc/receipt-invoice-generator/backup.passphrase /usr/local/sbin/rig-restore-drill.sh
```

## 6. Production Smoke Checklist

- Login page loads over HTTPS.
- Admin login works for the intended company.
- Company switch/context is correct.
- Create draft invoice; official number remains empty.
- Preview/download PDF works for A4.
- Upload valid artwork; SVG/path traversal upload is rejected.
- Issue document only with `draft_hash`, `confirmed_total`, and idempotency key.
- Download issued PDF by version.
- Create payment and official receipt.
- Telegram webhook rejects missing/wrong secret and unauthorized chat.
- Telegram bot sends the draft summary, confirmation instruction, and issued-number message.
- DeepSeek outage falls back to manual draft parsing.
- DeepSeek live-key parse is tested with `php artisan rig:deepseek-smoke --require-live`.
- Playwright renders A4 invoice, quotation, DO, official receipt, and 60mm receipt.
- Queue worker status healthy and no failed jobs.
- `storage/logs/laravel.log` has no new production error.

## 7. Rollback

Before deployment:

```bash
git rev-parse HEAD > /var/www/receipt-invoice-generator/.last_release
php artisan down --secret=REPLACE_WITH_SECRET
```

Rollback steps:

```bash
cd /var/www/receipt-invoice-generator
git checkout "$(cat .last_release)"
composer install --no-dev --optimize-autoloader
npm ci && npx playwright install --with-deps chromium && npm run build
php artisan migrate:rollback --step=1 --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart rig-queue:*
php artisan up
```

Only roll back migrations when the release notes confirm the previous schema remains compatible.

## Upload size limits (artwork attachments)

Three layers must stay aligned. Bumping one without the others causes the
upload to silently truncate (nginx 413) or fail with "file failed to
upload" (PHP/Laravel).

| Layer | File | Default after this commit |
|-------|------|---------------------------|
| nginx | `deploy/nginx/receipt-invoice-generator.conf` | `client_max_body_size 12m` |
| PHP-FPM | `public/.user.ini` (in-repo, auto-picked) | `upload_max_filesize 10M`, `post_max_size 12M` |
| Laravel | `app/Http/Controllers/Api/DocumentAttachmentController.php` | `max:10240` (KB) |
| Vue client | `resources/js/Pages/Documents/Edit.vue` `UPLOAD_MAX_BYTES` | `10 * 1024 * 1024` (B) |

To raise to e.g. 20 MB:

```bash
# 1) Edit deploy/nginx/receipt-invoice-generator.conf -> client_max_body_size 24m
# 2) Edit public/.user.ini -> upload_max_filesize 20M, post_max_size 24M
# 3) Edit DocumentAttachmentController validation -> max:20480
# 4) Edit Edit.vue -> UPLOAD_MAX_BYTES = 20 * 1024 * 1024
# 5) Apply:
sudo nginx -s reload
# .user.ini is re-read by PHP-FPM every 300 s automatically (user_ini.cache_ttl).
# Force immediate refresh:
sudo systemctl reload php8.3-fpm
```

If the server still rejects large files, also check `client_max_body_size`
in the `http {}` block (`/etc/nginx/nginx.conf`) — the site-level value
is capped by the global default in some distros.
