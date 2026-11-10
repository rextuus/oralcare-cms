#!/bin/sh
# oralcare – Komodo „Pre Deploy“: Secrets prüfen und Datenbank sichern, bevor neue Images gebaut und gestartet werden.
# Läuft im Periphery-Container im Stack-Ordner (Repo-Wurzel), nach `git pull` und nachdem Komodo .env.prod.local
# geschrieben hat. Braucht nur sh und die docker-CLI. Exit ≠ 0 bricht den Deploy ab.
#
# In Komodo: pre_deploy.command = "sh deploy/komodo/pre-deploy.sh"
# Von Hand im selben Ordner genauso aufrufbar (als root, die Env-Datei hat Rechte 600).
set -eu

project=${COMPOSE_PROJECT:-oralcare}
env_file=${ENV_FILE:-.env.prod.local}
dc() { docker compose -p "$project" -f deploy/compose.yaml --env-file "$env_file" "$@"; }

test -f "$env_file" || { echo "${env_file} fehlt – in Komodo Env File Path und Environment prüfen." >&2; exit 1; }
test -r "$env_file" || { echo "${env_file} ist nicht lesbar – als root (sudo) aufrufen." >&2; exit 1; }

# Komodo lässt einen [[PLATZHALTER]] stehen, wenn es die Variable bzw. das Secret nicht gibt. Dann lieber
# abbrechen, als mit einem wörtlichen „[[…]]“ als Passwort zu starten.
if missing=$(grep -o '\[\[[A-Za-z0-9_]*\]\]' "$env_file"); then
  echo "In Komodo fehlen diese Variablen/Secrets (Settings → Variables):" >&2
  echo "$missing" | sort -u | sed 's/^/  /' >&2
  exit 1
fi

# Das DB-Volume ist extern und darf nie neu angelegt werden; fehlt es, stimmt etwas Grundsätzliches nicht.
volume=$(dc config --format json | sed -n '/"database_data": {/{n;s/.*"name": *"\([^"]*\)".*/\1/p;}')
test -n "$volume" || { echo "DB-Volume nicht in der Compose-Konfiguration gefunden – Deploy abgebrochen." >&2; exit 1; }
docker volume inspect "$volume" >/dev/null 2>&1 || { echo "DB-Volume ${volume} fehlt – Deploy abgebrochen." >&2; exit 1; }

# Beim Umzug ist die Datenbank hier noch gestoppt (Dump von Hand, deploy/UMZUG.md Schritt 3.1) – dann nichts zu sichern.
if [ -z "$(dc ps -q --status running database 2>/dev/null)" ]; then
  echo "Datenbank läuft nicht – Sicherung vor dem Deploy übersprungen."
  exit 0
fi

# --no-deps: Ändert der neue Commit den Dienst database, würde Compose ihn sonst schon VOR der Sicherung neu erstellen.
dc run --rm --no-TTY --no-deps backup pre-deploy
