#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/receipt-invoice-generator}"
PASSPHRASE_FILE="${BACKUP_PASSPHRASE_FILE:-/etc/receipt-invoice-generator/backup.passphrase}"
DRILL_DIR="${DRILL_DIR:-/tmp/rig-restore-drill}"

latest_file() {
    local pattern="$1"
    find "$BACKUP_DIR" -type f -name "$pattern" -printf '%T@ %p\n' \
        | sort -nr \
        | awk 'NR==1 {print $2}'
}

require_file() {
    local path="$1"
    if [[ -z "$path" || ! -f "$path" ]]; then
        echo "Missing required backup artifact: $path" >&2
        exit 1
    fi
}

DB_BACKUP="$(latest_file 'rig_*.dump.gpg')"
FILES_BACKUP="$(latest_file 'private_documents_*.tar.gz.gpg')"
ENV_BACKUP="$(latest_file 'env_*.gpg')"
MANIFEST="$(latest_file 'manifest_*.sha256')"

require_file "$DB_BACKUP"
require_file "$FILES_BACKUP"
require_file "$ENV_BACKUP"
require_file "$MANIFEST"
require_file "$PASSPHRASE_FILE"

rm -rf "$DRILL_DIR"
mkdir -p "$DRILL_DIR"

echo "Restore drill workspace: $DRILL_DIR"
echo "DB backup: $DB_BACKUP"
echo "Files backup: $FILES_BACKUP"
echo "Env backup: $ENV_BACKUP"
echo "Manifest: $MANIFEST"

echo "Verifying manifest..."
sha256sum --check "$MANIFEST"

echo "Decrypting latest artifacts for dry-run inspection..."
gpg --batch --yes --decrypt --passphrase-file "$PASSPHRASE_FILE" \
    --output "$DRILL_DIR/db.dump" "$DB_BACKUP"
gpg --batch --yes --decrypt --passphrase-file "$PASSPHRASE_FILE" \
    --output "$DRILL_DIR/private_documents.tar.gz" "$FILES_BACKUP"
gpg --batch --yes --decrypt --passphrase-file "$PASSPHRASE_FILE" \
    --output "$DRILL_DIR/env" "$ENV_BACKUP"

echo "Inspecting archive and DB dump headers..."
pg_restore --list "$DRILL_DIR/db.dump" >/dev/null
tar -tzf "$DRILL_DIR/private_documents.tar.gz" >/dev/null
test -s "$DRILL_DIR/env"

echo "Restore drill passed. To complete a full restore test, restore db.dump into a disposable database and compare application smoke output."
