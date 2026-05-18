# Gate R7 Report - Deployment and Production Hardening

## Scope Completed

- Rewrote the Tencent Ubuntu deployment runbook with explicit hardening, app deploy, environment, nginx, SSL, queue, scheduler, backup, restore drill, smoke, and rollback steps.
- Replaced ambiguous sample hostnames with `REPLACE_WITH_*` placeholders that must be filled before deployment.
- Updated nginx sample with domain placeholder, private storage denial, dotfile denial, upload size cap, SSL session settings, security headers, and PHP-FPM socket note.
- Updated supervisor queue worker sample with app directory, Redis queue target, timeout, max-time recycle, log rotation, and graceful stop signal.
- Reworked backup script to include PostgreSQL dump, `.env`, and private document storage under `storage/app/private/documents`.
- Backup artifacts are encrypted with GPG symmetric encryption and can be synced to an off-server rsync target.
- Added `deploy/restore-drill.sh` to verify encrypted DB, private documents, environment config, and manifest integrity before a real restore.

## Guardrails Preserved

- No production deployment was executed from this local remediation pass.
- No real domain, SSH key, API key, DB password, or backup target was committed.
- PostgreSQL is documented as local/private only, never public.
- Backup includes private generated PDFs and uploaded artwork.

## Verified Commands

- `bash -n ./deploy/backup.sh`
  - Passed.
- `bash -n ./deploy/restore-drill.sh`
  - Passed.
- `php artisan test`
  - Passed: 123 tests / 414 assertions.
- `npm run build`
  - Passed.

## Remaining Notes

- Production smoke on Tencent must still be run on the real server after DNS, SSL, `.env`, database, queue, scheduler, and backup target are configured.
- The PHP-FPM socket in nginx is set to PHP 8.3 by default; adjust it if the server is provisioned with PHP 8.4.
