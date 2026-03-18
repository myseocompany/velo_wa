#!/usr/bin/env bash
# backup.sh — Daily PostgreSQL backup with S3 upload
# Usage: ./scripts/backup.sh
# Add to cron: 0 3 * * * /path/to/velo_wa/scripts/backup.sh >> /var/log/aricrm-backup.log 2>&1

set -euo pipefail

# ── Config (inherit from .env or set here) ───────────────────────────────────
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-velo}"
DB_USERNAME="${DB_USERNAME:-velo}"
DB_PASSWORD="${DB_PASSWORD:-secret}"
S3_BUCKET="${BACKUP_S3_BUCKET:-}"           # e.g. aricrm-backups
S3_PREFIX="${BACKUP_S3_PREFIX:-db}"
RETAIN_DAYS="${BACKUP_RETAIN_DAYS:-30}"
COMPOSE="docker compose -f $(dirname "$0")/../docker-compose.prod.yml"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILENAME="backup_${DB_DATABASE}_${TIMESTAMP}.sql.gz"
TMPDIR=$(mktemp -d)

cleanup() { rm -rf "$TMPDIR"; }
trap cleanup EXIT

echo "[$(date)] Starting backup: $FILENAME"

# ── Dump ──────────────────────────────────────────────────────────────────────
PGPASSWORD="$DB_PASSWORD" $COMPOSE exec -T postgres \
    pg_dump -U "$DB_USERNAME" -h "$DB_HOST" -p "$DB_PORT" "$DB_DATABASE" \
    | gzip > "${TMPDIR}/${FILENAME}"

SIZE=$(du -sh "${TMPDIR}/${FILENAME}" | cut -f1)
echo "[$(date)] Dump complete: $SIZE"

# ── Upload to S3 (if configured) ──────────────────────────────────────────────
if [[ -n "$S3_BUCKET" ]]; then
    aws s3 cp "${TMPDIR}/${FILENAME}" "s3://${S3_BUCKET}/${S3_PREFIX}/${FILENAME}" \
        --storage-class STANDARD_IA
    echo "[$(date)] Uploaded to s3://${S3_BUCKET}/${S3_PREFIX}/${FILENAME}"

    # Delete old backups beyond retention window
    echo "[$(date)] Purging backups older than ${RETAIN_DAYS} days..."
    CUTOFF=$(date -d "-${RETAIN_DAYS} days" +%Y-%m-%d 2>/dev/null || date -v -${RETAIN_DAYS}d +%Y-%m-%d)
    aws s3 ls "s3://${S3_BUCKET}/${S3_PREFIX}/" \
        | awk '{print $4}' \
        | while read -r key; do
            KEY_DATE=$(echo "$key" | grep -oE '[0-9]{8}' | head -1 | sed 's/\(.\{4\}\)\(.\{2\}\)\(.\{2\}\)/\1-\2-\3/')
            if [[ -n "$KEY_DATE" && "$KEY_DATE" < "$CUTOFF" ]]; then
                aws s3 rm "s3://${S3_BUCKET}/${S3_PREFIX}/${key}"
                echo "[$(date)] Deleted old backup: $key"
            fi
        done
else
    # Keep locally if no S3 configured
    LOCAL_BACKUP_DIR="${LOCAL_BACKUP_DIR:-/var/backups/aricrm}"
    mkdir -p "$LOCAL_BACKUP_DIR"
    mv "${TMPDIR}/${FILENAME}" "${LOCAL_BACKUP_DIR}/${FILENAME}"
    find "$LOCAL_BACKUP_DIR" -name "backup_*.sql.gz" -mtime +${RETAIN_DAYS} -delete
    echo "[$(date)] Saved locally: ${LOCAL_BACKUP_DIR}/${FILENAME}"
fi

echo "[$(date)] Backup completed successfully."
