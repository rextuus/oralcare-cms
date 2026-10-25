#!/bin/bash
# Läuft auf dem Server, gestartet über `make deploy` / `make backup` im Projektordner.
#   deploy.sh deploy <projekt-dir> <branch> <health-url>
#   deploy.sh backup <projekt-dir>
set -euo pipefail

ACTION="$1"
PROJECT_DIR="$2"
BRANCH="${3:-main}"
HEALTH_URL="${4:-http://localhost:8080/}"

cd "$PROJECT_DIR"

if ! docker info >/dev/null 2>&1; then
    echo "❌ Kein Zugriff auf Docker. Als root bzw. mit sudo ausführen."
    exit 1
fi

backup_db() {
    echo "🗄️ Sichere Datenbank..."
    mkdir -p backups
    local file="backups/db_$(date +%Y-%m-%d_%H-%M-%S)_$(git rev-parse --short HEAD).sql.gz"
    # Zugangsdaten kommen aus den Env-Variablen des DB-Containers, nicht aus diesem Skript
    docker compose exec -T database sh -c \
        'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
        | gzip > "$file"
    echo "   → $file"
    # Nur die letzten 10 Backups behalten
    ls -1t backups/db_*.sql.gz | tail -n +11 | xargs -r rm --
}

php_exec() {
    docker compose exec -T php "$@"
}

if [ "$ACTION" = "backup" ]; then
    backup_db
    exit 0
fi

PREVIOUS=$(git rev-parse --short HEAD)

backup_db

echo "📥 Hole Code ($BRANCH)..."
git fetch origin "$BRANCH"
git checkout -q "$BRANCH"
# --ff-only bricht ab, statt lokale Änderungen auf dem Server zu überschreiben
git merge --ff-only "origin/$BRANCH"
CURRENT=$(git rev-parse --short HEAD)

echo "🐳 Baue Container neu (inkl. aktuellem PHP-Patch-Release)..."
docker compose build --pull php
docker compose up -d --remove-orphans

APP_ENV=$(grep -E '^APP_ENV=' .env.local 2>/dev/null | tail -1 | cut -d= -f2 | tr -d '"'\' || true)
COMPOSER_FLAGS="--no-interaction --optimize-autoloader"
if [ "$APP_ENV" = "prod" ]; then
    COMPOSER_FLAGS="$COMPOSER_FLAGS --no-dev"
else
    echo "⚠️  APP_ENV ist '${APP_ENV:-nicht gesetzt}', nicht 'prod' – installiere inkl. Dev-Paketen."
fi

echo "📦 Installiere PHP-Abhängigkeiten..."
php_exec composer install $COMPOSER_FLAGS

echo "🗄️ Aktualisiere Datenbank-Schema..."
# Das Projekt nutzt keine Migrations, Sulu-Updates bringen aber Schema-Änderungen mit.
# Automatisch angewendet wird nur, was keine Daten löscht (DROP FOREIGN KEY/INDEX werden neu angelegt).
SCHEMA_SQL=$(php_exec bin/adminconsole doctrine:schema:update --dump-sql)
if echo "$SCHEMA_SQL" | grep -q "Nothing to update"; then
    echo "   Schema ist aktuell."
elif echo "$SCHEMA_SQL" | grep -iE '\bDROP\b' | grep -viqE 'DROP (FOREIGN KEY|INDEX)'; then
    echo "$SCHEMA_SQL"
    echo "❌ Die Schema-Änderung würde Tabellen oder Spalten löschen. Nicht automatisch angewendet."
    echo "   Prüfen und ggf. von Hand ausführen: docker compose exec php bin/adminconsole doctrine:schema:update --force"
    echo "   DB-Backup liegt in $PROJECT_DIR/backups/"
    exit 1
else
    echo "$SCHEMA_SQL"
    php_exec bin/adminconsole doctrine:schema:update --force
fi

echo "🛡️ Prüfe auf bekannte Sicherheitslücken..."
if ! php_exec composer audit --locked; then
    echo "⚠️  composer audit meldet Sicherheitslücken – siehe oben. Deploy läuft weiter, bitte zeitnah updaten."
fi

echo "🎨 Baue Frontend-Assets..."
php_exec npm ci --no-audit --no-fund
php_exec npm run build

echo "🧹 Cache leeren und aufwärmen..."
php_exec bin/adminconsole cache:clear
php_exec bin/websiteconsole cache:clear
php_exec bin/websiteconsole cache:warmup

echo "🔓 Setze Schreibrechte..."
php_exec chown -R www-data:www-data var public/uploads public/build

echo "🩺 Healthcheck $HEALTH_URL ..."
STATUS=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$HEALTH_URL" || echo "000")
if [ "$STATUS" -ge 200 ] && [ "$STATUS" -lt 400 ]; then
    echo "========================================================="
    echo " 🎉 Deployment erfolgreich: $PREVIOUS → $CURRENT (HTTP $STATUS)"
    echo "========================================================="
else
    echo "❌ Healthcheck fehlgeschlagen (HTTP $STATUS)."
    echo "   Vorheriger Stand war $PREVIOUS (Rollback: dort git checkout $PREVIOUS, dann Container/Composer/Cache wie oben)"
    echo "   DB-Backup liegt in $PROJECT_DIR/backups/"
    exit 1
fi
