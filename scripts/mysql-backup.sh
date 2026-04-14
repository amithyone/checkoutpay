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
echo "Backing up ${DB_DATABASE} -> $FILE"
mysqldump \
  -h"${DB_HOST:-127.0.0.1}" \
  -P"${DB_PORT:-3306}" \
  -u"${DB_USERNAME}" \
  -p"${DB_PASSWORD}" \
  "${DB_DATABASE}" \
  --single-transaction \
  --routines \
  > "$FILE"
chmod 600 "$FILE"
echo "OK: $FILE"
ls -lh "$FILE"
