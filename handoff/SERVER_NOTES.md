# Server Notes — Tencent Ubuntu Migration (43.133.34.55)

Tarikh audit awal: **2026-05-17**
Auditor: Claude Code
User: `ubuntu` (sudo), `lighthouse` (default Tencent, tidak digunakan)

---

## 1. Ringkasan Server

| Item | Nilai |
|---|---|
| IP awam | `43.133.34.55` |
| Hostname | `VM-0-13-ubuntu` |
| OS | Ubuntu 22.04.5 LTS (kernel 5.15.0-171) |
| Disk | 40 GB, 22% guna (`/dev/vda2` ext4) |
| RAM | 1.9 GB |
| Swap | **2 GB** (ditambah 2026-05-17, swappiness=10) |
| Server lama | `43.156.242.39` (masih hidup, bertindak sebagai reverse proxy untuk trafik live) |

---

## 2. Domain & Sistem Aktif

### 2.1 BPP — `bpp.jawi.cc`
- **Stack**: Node.js (SvelteKit) + PM2
- **Process**: PM2 service `bpp` (id 0), `node /var/www/jawi.cc/bpp/build/index.js`
- **Port**: 3001 (sekarang bind `0.0.0.0`, sepatutnya `127.0.0.1`)
- **Folder**: `/var/www/jawi.cc/bpp/`
- **Database**: PostgreSQL — DB `bpp` (owner `bpp_app`)
- **Uploads**: `/var/www/jawi.cc/bpp/uploads/` (di-serve oleh nginx terus)
- **SSL**: ❌ **EXPIRED 2026-05-11**
- **DNS A record**: `104.21.15.86` (Cloudflare proxy aktif)
- **systemd service**: `pm2-ubuntu.service` (enabled, active)

### 2.2 PapaPrint — `papaprint.wehdah.my`
- **Stack**: Laravel + PHP 8.4-FPM
- **Folder**: `/var/www/papaprint/papaprint/` (public root: `public/`)
- **Database**: MariaDB — DB `papaprint` (39 tables, 2.84 MB), user `papaprint@127.0.0.1`
- **SSL**: ✅ valid sehingga **2026-07-01**
- **DNS A record**: `43.156.242.39` (masih server lama — perlu tukar ke `43.133.34.55`)
- **systemd services**:
  - `papaprint-queue.service` — Laravel queue worker (queue: `default,drive-uploads`)
  - `papaprint-scheduler.service` — `php artisan schedule:work`
  - `papaprint-whatsmeow.service` — Go service WhatsApp (port 4010, user `whatsmeow`)
- **Issue diketahui**: scheduler `papaprint:monitor-whatsapp-health` exit code 1 setiap 5 minit (spam log, bukan kritikal)
- **Nginx**: SSL + security headers (HSTS, CSP report-only, dll.) + SSE endpoint `/admin/notifications/stream` (gunakan socket `php8.4-sse.sock`)

### 2.3 Persada — `persadagemilang.my` (+ www)
- **Stack**: Laravel + PHP 8.4-FPM
- **Folder**: `/var/www/persadagemilang/` (public root: `public/`)
- **Database**: SQLite — `/var/www/persadagemilang/database/database.sqlite` (file wujud, 90 KB)
- **SSL**: ✅ valid sehingga **2026-06-03** (16 hari lagi)
- **DNS A record**: `43.156.242.39` (masih server lama — perlu tukar ke `43.133.34.55`)
- **Queue/Scheduler**: TIADA systemd service. `QUEUE_CONNECTION=sync`, jadi queue tak perlu worker. **Scheduler tidak dipasang** — kalau Persada ada `php artisan schedule:run`, kena setup cron/systemd.

### ⚠️ ISU KRITIKAL: Persada `.env` rosak
Baris berikut salah (dua kunci dilekat tanpa newline):
```
DB_DATABASE=/var/www/persadagemilang/database/database.sqliteDB_DATABASE=laravel
```
Persada di server baru **tidak boleh sambung ke DB** sekarang. Belum nampak sebab DNS masih point ke server lama. **Mesti dibetulkan SEBELUM tukar DNS.**

Tambahan baris .env yang salah untuk production:
- `APP_ENV=local` → patut `production`
- `APP_DEBUG=true` → patut `false` (debug page bocor stack trace)
- `APP_URL=http://localhost` → patut `https://persadagemilang.my`

---

## 3. Punca Migrasi Belum Selesai

DNS untuk dua domain Laravel **masih point ke server lama** (`43.156.242.39`). Server lama bertindak sebagai reverse proxy ke server baru. Bukti:
- Nginx server baru ada rule `if ($remote_addr = 43.156.242.39) { set $allow_old_proxy 1; }` — hanya benarkan HTTP 80 tanpa redirect untuk trafik dari server lama
- Certbot gagal renew di server baru kerana HTTP-01 challenge mendarat di server lama

**Maksudnya**: SSL cert di server baru tak boleh renew sehingga DNS tukar. Itulah sebab `bpp.jawi.cc` expired tanpa awak sedar (renewal automatik gagal selama ini).

---

## 4. Yang Sudah Dibuat (2026-05-17)

| # | Tindakan | Status |
|---|---|---|
| 1 | Tambah swap 2 GB (`/swapfile`, persist di `/etc/fstab`, swappiness=10) | ✅ |
| 2 | Pasang fail2ban + jail `sshd` (bantime 1h, maxretry 5) | ✅ (3 IP brute force sudah di-ban automatik) |
| 3 | Backup script di `/usr/local/sbin/server-backup.sh` | ✅ |
| 4 | Folder backup `/var/backups/server/{db,files,configs}` (perms 700, root) | ✅ |
| 5 | Backup manual pertama (9.8 MB total — MariaDB + Postgres + SQLite + storage + configs) | ✅ |
| 6 | Cron `/etc/cron.d/server-backup` — **DIMATIKAN** (buang `#` bila ready) | ⏸️ |

### Detail script backup
- **Rotasi**: simpan 14 hari, padam yang lebih lama
- **Cakupan**:
  - MariaDB: `mysqldump papaprint` (single-transaction, gzip)
  - PostgreSQL: `pg_dump bpp` (custom format)
  - SQLite: copy `database.sqlite` Persada
  - Tar `storage/` papaprint + persada, `uploads/` BPP
  - Salin semua `.env` files
  - Snapshot nginx sites + systemd services + letsencrypt renewal
- **Log**: `/var/backups/server/backup.log` + `/var/log/server-backup.cron.log` (bila cron diaktifkan)
- **Aktifkan cron**: edit `/etc/cron.d/server-backup`, buang `#` di baris `30 3 * * *` → backup tiap-tiap hari pukul 3:30 pagi

---

## 5. Yang TERTINGGAL (susun ikut keutamaan)

### 🔴 Mesti buat (blocking migrasi)

#### A. Betulkan Persada `.env` (saya akan tanya kebenaran asingkan)
- Tukar baris bermasalah:
  ```
  DB_DATABASE=/var/www/persadagemilang/database/database.sqliteDB_DATABASE=laravel
  ```
  jadi:
  ```
  DB_DATABASE=/var/www/persadagemilang/database/database.sqlite
  ```
- Bila ready production: tukar juga `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://persadagemilang.my`
- Lepas tukar APP_ENV/APP_DEBUG: `php artisan config:cache && php artisan view:cache`
- Backup current `.env` ke `/var/backups/server/configs/` sebelum tukar

#### B. Tukar DNS A records (awak yang buat — di registrar/Cloudflare)
| Domain | A record sekarang | Tukar ke |
|---|---|---|
| `papaprint.wehdah.my` | `43.156.242.39` | `43.133.34.55` |
| `persadagemilang.my` | `43.156.242.39` | `43.133.34.55` |
| `www.persadagemilang.my` | `43.156.242.39` | `43.133.34.55` |
| `bpp.jawi.cc` (Cloudflare) | DNS-only OR setup Cloudflare Origin Cert | (lihat 5.C) |

**Cadangan urutan**:
1. **Persada dahulu** (cert masih valid 16 hari, ada margin) — TTL tukar low (300s) → tunggu propagate (5-10 min) → renew SSL → verify.
2. **PapaPrint** (cert valid 45 hari, paling banyak service jalan — paling kompleks).
3. **BPP** terakhir (cert dah expired, perlu strategi khas).

#### C. Renew SSL BPP (kompleks, sebab Cloudflare proxy)
3 pilihan:
1. **Mod Cloudflare Full (Strict)**: gunakan **Cloudflare Origin Certificate** (15 tahun, free). Ganti cert Let's Encrypt di nginx → tak perlu certbot lagi. **Cadangan terbaik.**
2. **Disable Cloudflare proxy** (gray cloud) buat sementara → renew certbot via HTTP-01 → enable proxy semula.
3. **DNS-01 challenge** via Cloudflare API → certbot pakai plugin `python3-certbot-dns-cloudflare`.

#### D. Bind port 3001 ke `127.0.0.1` sahaja (selepas DNS BPP siap)
- Sekarang `0.0.0.0:3001` terdedah ke internet, bypass nginx + SSL
- Fail BPP `.env` di `/var/www/jawi.cc/bpp/.env` — tambah/tukar `HOST=127.0.0.1` (ikut framework, mungkin pakai `LISTEN_ADDRESS`)
- Restart: `sudo -u ubuntu pm2 restart bpp`
- Verify: `sudo ss -tlnp | grep 3001` patut tunjuk `127.0.0.1:3001`

### 🟠 Patut buat selepas DNS migrasi siap

#### E. Hidupkan UFW firewall
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp comment 'SSH'
sudo ufw allow 80/tcp comment 'HTTP'
sudo ufw allow 443/tcp comment 'HTTPS'
# JANGAN enable lagi — tambah IP awak ke whitelist dulu:
# sudo ufw allow from <IP-AWAK> to any port 22
sudo ufw status verbose       # review dulu
sudo ufw enable               # baru aktifkan
```
⚠️ **Pastikan IP awak masih boleh SSH selepas enable.**

#### F. SSH hardening
File `/etc/ssh/sshd_config`:
```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```
**Sebelum tukar**: pastikan `~/.ssh/authorized_keys` di server ada key awak.
**Selepas tukar**: `sudo systemctl reload sshd`, JANGAN logout sesi semasa sehingga login baru disahkan dari terminal lain.

#### G. Whitelist IP awak di fail2ban
Edit `/etc/fail2ban/jail.local`:
```
ignoreip = 127.0.0.1/8 ::1 <IP-AWAK>
```
Reload: `sudo systemctl reload fail2ban`

#### H. Aktifkan cron backup harian
```bash
sudo sed -i 's|^#30 3|30 3|' /etc/cron.d/server-backup
```

### 🟡 Boleh tunggu / nice-to-have

- **Scheduler Persada**: kalau Persada ada job berjadual (semak `app/Console/Kernel.php`), tambah `papaprint-scheduler.service` setara untuk Persada.
- **Siasat error `papaprint:monitor-whatsapp-health`**: tengok `php artisan papaprint:monitor-whatsapp-health -vvv` untuk root cause.
- **Off-server backup**: rsync `/var/backups/server` ke object storage (Tencent COS, S3, dll.) — sekarang backup tinggal dalam server yang sama.
- **Monitoring**: setup uptime monitor (UptimeRobot/healthchecks.io) untuk 3 domain.
- **Decommission server lama** `43.156.242.39` — selepas DNS tukar siap dan diuji 1-2 minggu.

---

## 6. Maklumat Sambungan & Akses

- **SSH**: `ssh ubuntu@43.133.34.55` (key-based via `~/.ssh/`)
- **MariaDB root**: socket auth (sudo mysql)
- **PostgreSQL**: `sudo -u postgres psql`
- **PM2**: `sudo -u ubuntu pm2 [list|logs bpp|restart bpp]`
- **Service status**: `systemctl status papaprint-queue papaprint-scheduler papaprint-whatsmeow pm2-ubuntu nginx mariadb postgresql redis-server fail2ban`

---

## 7. Lokasi Penting

| Apa | Path |
|---|---|
| Nginx sites | `/etc/nginx/sites-enabled/` |
| Cert Let's Encrypt | `/etc/letsencrypt/live/<domain>/` |
| Cert renewal config | `/etc/letsencrypt/renewal/<domain>.conf` |
| systemd service custom | `/etc/systemd/system/papaprint-*.service`, `/etc/systemd/system/pm2-ubuntu.service` |
| Backup script | `/usr/local/sbin/server-backup.sh` |
| Backup folder | `/var/backups/server/` |
| Cron backup (matikan) | `/etc/cron.d/server-backup` |
| Fail2ban config | `/etc/fail2ban/jail.local` |
| Swap | `/swapfile` |
| Sysctl tweak | `/etc/sysctl.d/99-swappiness.conf` |
| Laravel logs | `/var/www/<app>/storage/logs/laravel-*.log` |
| Nginx logs | `/var/log/nginx/{access,error}.log` |
| Let's Encrypt log | `/var/log/letsencrypt/letsencrypt.log` |
