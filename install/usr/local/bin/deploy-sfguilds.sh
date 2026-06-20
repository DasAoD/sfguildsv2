#!/usr/bin/env bash
# =============================================================================
# deploy-sfguilds.sh – Deployment-Script für sfguildsv2
#
# HINWEIS: Folgende Variablen vor Verwendung an die eigene Umgebung anpassen:
#   REPO_DIR  – Pfad zum Projektverzeichnis
#   LOCK      – Pfad zur Lock-Datei
#   LOG       – Pfad zur Log-Datei
#   WEB_GROUP – Webserver-Gruppe (meist www-data)
# =============================================================================
set -euo pipefail

REPO_DIR="/var/www/sfguildsv2"
BRANCH="main"
REMOTE="origin"
WEB_GROUP="www-data"

LOCK="/run/lock/sfguilds-deploy.lock"
LOG="/var/log/sfguilds-deploy.log"

umask 002

exec 200>"$LOCK"
flock -n 200 || { echo "Deploy läuft schon."; exit 1; }

{
  echo "=== $(date -Is) deploy start ==="
  cd "$REPO_DIR"

  git fetch --prune "$REMOTE" "$BRANCH"
    # --- LINT (vor Deploy) ---
  NEW_SHA="$(git rev-parse "$REMOTE/$BRANCH")"
  WT="$REPO_DIR/.deploy_lint_worktree"
  
  cleanup() {
  git worktree remove -f "$WT" >/dev/null 2>&1 || true
  rm -rf "$WT" >/dev/null 2>&1 || true
  }
  trap cleanup EXIT

  # altes Worktree aufräumen (falls vorhanden)
  git worktree remove -f "$WT" >/dev/null 2>&1 || true
  rm -rf "$WT" >/dev/null 2>&1 || true

  # neuen Stand in Worktree auschecken und linten
  git worktree add -f --detach "$WT" "$NEW_SHA" >/dev/null

  echo "Lint commit: $NEW_SHA"
  while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null
  done < <(find "$WT" -type f -name '*.php' -print0)

  # Worktree wieder entfernen
  git worktree remove -f "$WT" >/dev/null
  rm -rf "$WT" >/dev/null 2>&1 || true

  echo "Lint OK"
  # --- /LINT ---

  # Sicherstellen, dass keine lokalen Änderungen den Checkout blockieren können
  git reset --hard
  git clean -fd
  git clean -fd \
  -e storage/ \
  -e public/uploads/ \
  -e .env \
  -e .env.*

  git checkout -B "$BRANCH" "$REMOTE/$BRANCH"
  git reset --hard "$REMOTE/$BRANCH"

  echo "Git rev: $(git rev-parse --short HEAD)"

  # Rechte: Code schreibbar für deploy+www-data, Runtime nicht anfassen
  chgrp -R "$WEB_GROUP" "$REPO_DIR/app" "$REPO_DIR/public" || true
  find "$REPO_DIR/app" "$REPO_DIR/public" -type d -exec chmod 2775 {} +
  find "$REPO_DIR/app" "$REPO_DIR/public" -type f -exec chmod 0664 {} +

  # Runtime-Ordner sicherstellen (CSV-Upload / Import)
  install -d -m 2775 -o deploy -g "$WEB_GROUP" \
    "$REPO_DIR/storage/import/archive" \
    "$REPO_DIR/storage/import/failed" \
    "$REPO_DIR/storage/import/incoming" \
    "$REPO_DIR/storage/import/processing"

  # Runtime-Dateien (falls vorhanden) schreibbar für Gruppe halten
  for f in "$REPO_DIR/storage/import/import.lock" "$REPO_DIR/storage/import/import.log"; do
    [ -e "$f" ] && chgrp "$WEB_GROUP" "$f" && chmod 0664 "$f"
  done

  echo "=== $(date -Is) deploy ok ==="
} 2>&1 | tee -a "$LOG"
