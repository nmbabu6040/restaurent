#!/usr/bin/env bash
# Daily backup of the database and uploaded photos. Needs /etc/meher-backup.cnf (see docs/DEPLOYMENT.md).
# .env is NOT included on purpose (it holds secrets): keep a copy in a password manager.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/meher}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/meher}"
DB_NAME="${DB_NAME:-meher}"
DB_CNF="${DB_CNF:-/etc/meher-backup.cnf}"
KEEP_DAYS="${KEEP_DAYS:-14}"

umask 077
mkdir -p "$BACKUP_DIR"
stamp="$(date +%Y%m%d-%H%M%S)"

# Written to a temp name first, so a failed dump never leaves a broken file that looks like a backup.
tmp="$BACKUP_DIR/db-$stamp.sql.gz.tmp"
mysqldump --defaults-extra-file="$DB_CNF" --single-transaction --quick --no-tablespaces "$DB_NAME" | gzip > "$tmp"
mv "$tmp" "$BACKUP_DIR/db-$stamp.sql.gz"

tar -czf "$BACKUP_DIR/uploads-$stamp.tar.gz" -C "$APP_DIR/storage/app" public

find "$BACKUP_DIR" -type f \( -name 'db-*.sql.gz' -o -name 'uploads-*.tar.gz' \) -mtime +"$KEEP_DAYS" -delete

echo "Backup finished ($stamp). Copy $BACKUP_DIR off this server too (rsync, rclone or your host's snapshots)."
