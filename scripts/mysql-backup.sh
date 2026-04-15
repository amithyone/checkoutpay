#!/usr/bin/env bash
# Backup MySQL database from .env (run from project root: ./scripts/mysql-backup.sh)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
if [[ ! -f .env ]]; then
  echo "No .env in $ROOT" >&2
  exit 1
fi
set -a
# shellcheck disable=SC1091
source <(grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env | sed 's/\r$//')
set +a
if [[ "${DB_CONNECTION:-mysql}" != "mysql" ]]; then
  echo "This script only supports mysql driver (got DB_CONNECTION=${DB_CONNECTION:-empty})" >&2
  exit 1
fi
OUT_DIR="$ROOT/storage/backups/mysql"
mkdir -p "$OUT_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$OUT_DIR/${DB_DATABASE}-${STAMP}.sql"
KEEP_COUNT="${MYSQL_BACKUP_KEEP:-5}"
COMPRESS="${MYSQL_BACKUP_COMPRESS:-true}"
echo "Backing up ${DB_DATABASE} -> $FILE"
mysqldump \
  -h"${DB_HOST:-127.0.0.1}" \
  -P"${DB_PORT:-3306}" \
  -u"${DB_USERNAME}" \
  -p"${DB_PASSWORD}" \
  "${DB_DATABASE}" \
  --single-transaction \
  --triggers \
  --events \
  --routines \
  > "$FILE"

if [[ "$COMPRESS" == "true" ]]; then
  gzip -f "$FILE"
  FILE="${FILE}.gz"
fi

chmod 600 "$FILE"
echo "OK: $FILE"
ls -lh "$FILE"

# Rotation: keep only latest N backups for this database.
if [[ "$KEEP_COUNT" =~ ^[0-9]+$ ]] && [[ "$KEEP_COUNT" -gt 0 ]]; then
  mapfile -t backups < <(ls -1t "$OUT_DIR/${DB_DATABASE}-"*.sql* 2>/dev/null || true)
  if [[ "${#backups[@]}" -gt "$KEEP_COUNT" ]]; then
    for old_file in "${backups[@]:$KEEP_COUNT}"; do
      rm -f "$old_file"
      echo "Deleted old backup: $old_file"
    done
  fi
else
  echo "Skipping rotation: MYSQL_BACKUP_KEEP must be a positive integer."
fi
