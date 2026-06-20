#!/bin/bash
set -euo pipefail

BASE="/var/www/sfguildsv2/storage/import"
IN="$BASE/incoming"
PROC="$BASE/processing"
ARCH="$BASE/archive"
FAIL="$BASE/failed"

LOCK="$BASE/import.lock"
LOG="$BASE/import.log"

mkdir -p "$IN" "$PROC" "$ARCH" "$FAIL"
touch "$LOG"

# nur ein Lauf gleichzeitig
exec 200>"$LOCK"
flock -n 200 || exit 0

log() { echo "[$(date '+%F %T')] $*" >> "$LOG"; }

IMPORT_CMD=(php /var/www/sfguildsv2/cli/import_sftools.php --file)

shopt -s nullglob
for f in "$IN"/*.csv; do
  bn="$(basename "$f")"
  procfile="$PROC/$bn"

  mv "$f" "$procfile"
  log "Processing: $procfile"

  if "${IMPORT_CMD[@]}" "$procfile" >> "$LOG" 2>&1; then
    mv "$procfile" "$ARCH/"
    log "OK -> archive: $bn"
  else
    mv "$procfile" "$FAIL/"
    log "FAIL -> failed: $bn"
  fi
done
