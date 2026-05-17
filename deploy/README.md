# Production Deployment

## Server: Tencent Ubuntu 22.04 (43.133.34.55)

### Pre-requisites (already configured per SERVER_NOTES.md)
- [x] SSH key-only access
- [x] fail2ban (sshd jail, bantime 1h, maxretry 5)
- [x] 2GB swap (swappiness=10)
- [x] Daily backup script at /usr/local/sbin/server-backup.sh
- [x] Backup cron at /etc/cron.d/server-backup (disabled, ready to enable)

### To be completed

#### 1. Deploy application
```bash
cd /var/www/receipt-invoice-generator
git clone https://github.com/hakimalek27/Receipt-Invoice-Generator.git .
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force
```

#### 2. Environment (.env)
- DB_CONNECTION=pgsql / DB_HOST=127.0.0.1
- REDIS_HOST=127.0.0.1
- APP_ENV=production / APP_DEBUG=false
- APP_TIMEZONE=Asia/Kuala_Lumpur

#### 3. Nginx + SSL
See `deploy/nginx/receipt-invoice-generator.conf`

#### 4. Queue workers
See `deploy/supervisor/queue-worker.conf`

#### 5. UFW firewall
```bash
sudo ufw default deny incoming
sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp
sudo ufw enable
```

#### 6. SSH hardening
PermitRootLogin no, PasswordAuthentication no
