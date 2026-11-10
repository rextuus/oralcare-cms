#!/bin/bash
# Nächtliches Backup für oralcare. Läuft als Dienst „backup“ im Stack, gestartet von der
# Komodo-Procedure „oralcare-backup“ (RunStackService, täglich 02:30 Europe/Berlin).
# Sichert die Datenbank und die Sulu-Originalbilder (var/storage) nach $BACKUP_ROOT.
# Bilder werden als Snapshots mit Hardlinks gesichert: unveränderte Dateien belegen keinen zusätzlichen Platz.
# Bei jedem Fehler endet das Skript mit Exit-Code ≠ 0, damit Komodo den Lauf als fehlgeschlagen meldet.
# Aufruf ohne Argument: nächtliches Backup. Mit „pre-deploy“ (deploy/komodo/pre-deploy.sh): nur die Datenbank,
# eigene Dateinamen und Rotation, damit häufige Deploys keine Nachtsicherungen verdrängen.
set -euo pipefail

MODE="${1:-daily}"
DB_HOST="${DB_HOST:-database}"
DATA_DIR="${DATA_DIR:-/data}"
BACKUP_ROOT="${BACKUP_ROOT:-/backups}"
KEEP="${KEEP:-14}"   # Anzahl Sicherungen (eine pro Nacht)
: "${MYSQL_PWD:?MYSQL_PWD (DB-Root-Passwort) fehlt}"
export MYSQL_PWD    # mysqldump liest das Passwort von hier, es erscheint nicht in der Prozessliste

STAMP=$(date +%Y-%m-%d_%H%M)

log() { echo "[$(date '+%F %T')] $*"; }
fail() { log "FEHLER: $*"; exit 1; }

# Zum Prüfen der Fehlermeldung in Komodo: einmal mit env BACKUP_SELFTEST_FAIL=1 starten
[ "${BACKUP_SELFTEST_FAIL:-0}" = 1 ] && fail "Absichtlicher Testfehler (BACKUP_SELFTEST_FAIL=1)"

mkdir -p "$BACKUP_ROOT/db" "$BACKUP_ROOT/storage"
chmod 700 "$BACKUP_ROOT"

exec 9>"$BACKUP_ROOT/.lock"
flock -n 9 || fail "Backup läuft bereits"

# --- Datenbank -------------------------------------------------------------
mysqladmin ping -h "$DB_HOST" -uroot --silent >/dev/null 2>&1 || fail "Datenbank $DB_HOST nicht erreichbar"

case "$MODE" in
    daily) DB_FILE="$BACKUP_ROOT/db/su_myapp_$STAMP.sql.gz" ;;
    pre-deploy) DB_FILE="$BACKUP_ROOT/db/pre-deploy_su_myapp_$STAMP.sql.gz" ;;
    *) fail "Unbekannter Modus: $MODE (erlaubt: daily, pre-deploy)" ;;
esac
if ! mysqldump -h "$DB_HOST" -uroot --single-transaction --routines --triggers --databases su_myapp \
        | gzip > "$DB_FILE.part"; then
    rm -f "$DB_FILE.part"
    fail "mysqldump fehlgeschlagen"
fi
zcat "$DB_FILE.part" | tail -1 | grep -q "Dump completed" || { rm -f "$DB_FILE.part"; fail "DB-Dump unvollständig"; }
mv "$DB_FILE.part" "$DB_FILE"
log "DB gesichert: $DB_FILE ($(du -h "$DB_FILE" | cut -f1))"

if [ "$MODE" = pre-deploy ]; then
    ls -1 "$BACKUP_ROOT/db"/pre-deploy_su_myapp_*.sql.gz | sort | head -n -5 | xargs -r rm -f --
    log "Sicherung vor Deploy fertig"
    exit 0
fi

# --- Originalbilder (var/storage) ------------------------------------------
[ -d "$DATA_DIR/storage" ] || fail "$DATA_DIR/storage fehlt"
[ -n "$(ls -A "$DATA_DIR/storage")" ] || fail "$DATA_DIR/storage ist leer – Mount prüfen"
LATEST="$BACKUP_ROOT/storage/latest"
TARGET="$BACKUP_ROOT/storage/$STAMP"
LINK_DEST=()
# Relativer Link, damit „latest“ auch auf dem Host (anderer Pfad als im Container) stimmt
[ -L "$LATEST" ] && LINK_DEST=(--link-dest="$BACKUP_ROOT/storage/$(readlink "$LATEST")")

rsync -a --delete "${LINK_DEST[@]}" "$DATA_DIR/storage/" "$TARGET.part/" || { rm -rf "$TARGET.part"; fail "rsync var/storage fehlgeschlagen"; }
mv "$TARGET.part" "$TARGET"
ln -sfn "$STAMP" "$LATEST"
log "Bilder gesichert: $TARGET ($(find "$TARGET" -type f | wc -l) Dateien)"

# --- Aufräumen -------------------------------------------------------------
# Nach Anzahl statt Alter: rsync -a übernimmt das (alte) Änderungsdatum des Quellordners,
# eine -mtime-Regel würde frische Snapshots sofort wieder löschen.
ls -1 "$BACKUP_ROOT/db"/su_myapp_*.sql.gz | sort | head -n -"$KEEP" | xargs -r rm -f --
ls -1d "$BACKUP_ROOT/storage"/20*/ | sort | head -n -"$KEEP" | xargs -r rm -rf --

log "Backup fertig"
