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
- **Database**: PostgreSQL — DB `bpp` (owner `bpp_app`). Schema lengkap (12 tables: mosques, lecturers, evaluations, dll.). **Mosques table cuma 3 baris** — kemungkinan data masjid lain (mam, mamad test, dll.) belum migrate dari server lama. Perlu siasat.
- **Uploads**: `/var/www/jawi.cc/bpp/uploads/` (di-serve oleh nginx terus, 9.3 MB)
- **SSL**: ✅ Let's Encrypt valid sehingga **2026-08-16**, **auto-renew via DNS-01 (Cloudflare API)**
- **DNS A record**: `104.21.15.86` (Cloudflare proxy aktif, Full Strict mode)
- **Cloudflare**: SSL/TLS mode = Full (Strict), API token di `/root/.secrets/cloudflare.ini` (Zone:DNS:Edit untuk jawi.cc)
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

## 4. Yang Sudah Dibuat

### Hari 1 (2026-05-17)
| # | Tindakan | Status |
|---|---|---|
| 1 | Tambah swap 2 GB (`/swapfile`, persist di `/etc/fstab`, swappiness=10) | ✅ |
| 2 | Pasang fail2ban + jail `sshd` (bantime 1h, maxretry 5) | ✅ (3 IP brute force sudah di-ban automatik) |
| 3 | Backup script di `/usr/local/sbin/server-backup.sh` | ✅ |
| 4 | Folder backup `/var/backups/server/{db,files,configs}` (perms 700, root) | ✅ |
| 5 | Backup manual pertama (9.8 MB total — MariaDB + Postgres + SQLite + storage + configs) | ✅ |
| 6 | Cron `/etc/cron.d/server-backup` — **DIMATIKAN** (buang `#` bila ready) | ⏸️ |
| 7 | Persada `.env` DB_DATABASE corrupt — diperbetulkan (Laravel + SQLite sambung balik) | ✅ |

### Hari 2 (2026-05-18) — DNS cutover + SSL
| # | Tindakan | Status |
|---|---|---|
| 8 | DNS tukar ke `43.133.34.55` (papaprint, persada, www.persada) | ✅ (oleh user) |
| 9 | Persada SSL renew (Let's Encrypt) — valid sehingga 2026-08-15 | ✅ |
| 10 | BPP SSL migrate ke Let's Encrypt + DNS-01 via Cloudflare API token | ✅ valid sehingga 2026-08-16 |
| 11 | Pasang plugin `python3-certbot-dns-cloudflare` | ✅ |
| 12 | Cloudflare API token disimpan di `/root/.secrets/cloudflare.ini` (perms 600, root) | ✅ |
| 13 | Cloudflare SSL/TLS mode = **Full (Strict)** | ✅ (oleh user) |
| 14 | Revoke + cleanup CF Origin Cert lama (folder `/etc/ssl/cloudflare` dipadam) | ✅ |
| 15 | Auto-renew dry-run untuk semua 3 cert: PASS | ✅ |

### Hari 3 (2026-05-18) — Phase A/B/C/D finishing
| # | Tindakan | Status |
|---|---|---|
| 16 | Snapshot baseline state ke `~/migration-snapshot/` (10 fail) | ✅ |
| 17 | Persada → production values (APP_ENV=production, APP_DEBUG=false, APP_URL=https://persadagemilang.my) + config/route/view cache | ✅ |
| 18 | BPP port 3001 bind `127.0.0.1` via systemd drop-in `/etc/systemd/system/pm2-ubuntu.service.d/host-bind.conf` | ✅ |
| 19 | Cron backup harian aktif (3:30 AM waktu server) | ✅ |
| 20 | Investigate scheduler `papaprint:monitor-whatsapp-health` → root cause: **WhatsApp session disconnected** (perlu re-pair QR) | ✅ |
| 21 | UFW enable (default deny in, allow 22/80/443) | ✅ |
| 22 | SSH hardening: `PermitRootLogin no`, `PasswordAuthentication no`, `KbdInteractiveAuthentication no`, `PubkeyAuthentication yes` | ✅ |
| 23 | Investigate BPP mosques data: **3 baris BETUL** — confirmed dari pg_dump 30-Mac yang ditinggalkan di `/home/ubuntu/migration-20260330-1215/bpp.sql` (size identik dengan finalsync). Tiada data hilang. | ✅ |
| 24 | Server lama `43.156.242.39` — user hilang akses; tidak boleh snapshot/decommission. Server sudah unreachable. | ⚠️ Tutup |
| 25 | **GRANT ALL PRIVILEGES** kepada `bpp_app` user di Postgres DB `bpp` — tables sebelum ini owned by `postgres` tanpa grant, menyebabkan "permission denied for table admins" pada login. Sekarang fixed + default privileges set untuk future tables. | ✅ |
| 26 | Reset password superadmin Hakim (phone 0189030363) via bcryptjs hash + dollar-quoted SQL | ✅ |

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

## 5. Yang TERTINGGAL

### 🟠 Tindakan user (saya tak boleh buat)

#### A. Pair semula WhatsApp untuk PapaPrint
- Scheduler `papaprint:monitor-whatsapp-health` keep report "Whatsmeow tidak terhubung" → exit 1 setiap 5 minit (spam log)
- whatsmeow Go service running normal (`/health` → 200 di port 4010), tapi sesi WhatsApp logged out
- Penyelesaian: pair semula nombor pengirim WhatsApp via admin panel PapaPrint (scan QR dari handphone)
- Setelah pair: scheduler akan exit 0, spam log berhenti automatik

### 🟡 Nice-to-have (boleh tunggu lama)

- **Off-server backup**: sekarang backup harian tinggal dalam server yang sama. Tambah rsync ke object storage (Tencent COS, S3, BackBlaze B2, dll.) untuk disaster recovery sebenar.
- **Monitoring uptime**: setup UptimeRobot/healthchecks.io untuk monitor 3 domain.
- **Persada scheduler**: kalau Persada perlu cron (semak `app/Console/Kernel.php`), tambah `persada-scheduler.service` setara dengan papaprint.
- **Persada migration verification**: sekarang hanya 4 migrations dijalankan di SQLite. Kalau Persada perlu lebih, jalankan `sudo -u www-data php8.4 artisan migrate`.
- **Cosmetic**: bersihkan duplicate `PasswordAuthentication no` dalam `/etc/ssh/sshd_config` (sed produced 3 baris sama, tapi sshd hormat baris pertama jadi fungsi tetap betul).

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
