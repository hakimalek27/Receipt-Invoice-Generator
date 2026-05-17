#!/bin/bash
set -euo pipefail
BACKUP_DIR="/var/backups/receipt-invoice-generator"
APP_DIR="/var/www/receipt-invoice-generator"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=14

mkdir -p "$BACKUP_DIR/db" "$BACKUP_DIR/files" "$BACKUP_DIR/configs"

echo "[$(date)] DB backup..."
pg_dump -h 127.0.0.1 -U postgres -d receipt_invoice_generator -F c \
    -f "$BACKUP_DIR/db/rig_${TIMESTAMP}.dump"

echo "[$(date)] Storage backup..."
tar -czf "$BACKUP_DIR/files/storage_${TIMESTAMP}.tar.gz" \
    -C "$APP_DIR" storage/app/documents --exclude='*.log'

echo "[$(date)] Config backup..."
cp "$APP_DIR/.env" "$BACKUP_DIR/configs/.env.${TIMESTAMP}"

find "$BACKUP_DIR" -type f -mtime +$RETENTION_DAYS -delete
echo "[$(date)] Done."
