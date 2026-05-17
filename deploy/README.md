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
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=receipt_invoice_generator
DB_USERNAME=receipt_invoice_generator
DB_PASSWORD=REPLACE_WITH_STRONG_DB_PASSWORD
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
FILESYSTEM_DISK=local
TELEGRAM_WEBHOOK_SECRET=REPLACE_WITH_LONG_RANDOM_SECRET
TELEGRAM_ALLOWED_CHAT_IDS=REPLACE_WITH_CHAT_ID
TELEGRAM_CHAT_USER_MAP=REPLACE_WITH_CHAT_ID:REPLACE_WITH_APP_USER_ID
DEEPSEEK_API_KEY=REPLACE_WITH_DEEPSEEK_KEY
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
- DeepSeek outage falls back to manual draft parsing.
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
npm ci && npm run build
php artisan migrate:rollback --step=1 --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart rig-queue:*
php artisan up
```

Only roll back migrations when the release notes confirm the previous schema remains compatible.
