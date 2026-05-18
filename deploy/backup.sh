#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/receipt-invoice-generator}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/receipt-invoice-generator}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-receipt_invoice_generator}"
DB_USERNAME="${DB_USERNAME:-receipt_invoice_generator}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
PASSPHRASE_FILE="${BACKUP_PASSPHRASE_FILE:-/etc/receipt-invoice-generator/backup.passphrase}"
OFFSITE_TARGET="${OFFSITE_BACKUP_TARGET:-}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

umask 077
mkdir -p "$BACKUP_DIR/db" "$BACKUP_DIR/files" "$BACKUP_DIR/configs" "$BACKUP_DIR/manifests"

require_file() {
    local path="$1"
    if [[ ! -f "$path" ]]; then
        echo "Missing required file: $path" >&2
        exit 1
    fi
}

encrypt_file() {
    local input="$1"
    require_file "$PASSPHRASE_FILE"
    gpg --batch --yes --symmetric --cipher-algo AES256 \
        --passphrase-file "$PASSPHRASE_FILE" \
        --output "${input}.gpg" "$input"
    shred -u "$input"
}

echo "[$(date --iso-8601=seconds)] Backing up database..."
PGPASSWORD="${DB_PASSWORD:-}" pg_dump \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USERNAME" \
    -d "$DB_DATABASE" \
    -F c \
    -f "$BACKUP_DIR/db/rig_${TIMESTAMP}.dump"
encrypt_file "$BACKUP_DIR/db/rig_${TIMESTAMP}.dump"

echo "[$(date --iso-8601=seconds)] Backing up private document storage..."
tar -czf "$BACKUP_DIR/files/private_documents_${TIMESTAMP}.tar.gz" \
    -C "$APP_DIR" storage/app/private/documents
encrypt_file "$BACKUP_DIR/files/private_documents_${TIMESTAMP}.tar.gz"

echo "[$(date --iso-8601=seconds)] Backing up environment config..."
require_file "$APP_DIR/.env"
cp "$APP_DIR/.env" "$BACKUP_DIR/configs/env_${TIMESTAMP}"
encrypt_file "$BACKUP_DIR/configs/env_${TIMESTAMP}"

echo "[$(date --iso-8601=seconds)] Writing manifest..."
find "$BACKUP_DIR" -type f -name "*_${TIMESTAMP}*" -print0 \
    | sort -z \
    | xargs -0 sha256sum > "$BACKUP_DIR/manifests/manifest_${TIMESTAMP}.sha256"

if [[ -n "$OFFSITE_TARGET" ]]; then
    echo "[$(date --iso-8601=seconds)] Syncing encrypted backups off-server..."
    rsync -az --delete "$BACKUP_DIR/" "$OFFSITE_TARGET/"
fi

find "$BACKUP_DIR" -type f -mtime +"$RETENTION_DAYS" -delete

echo "[$(date --iso-8601=seconds)] Backup complete."
