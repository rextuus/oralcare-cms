# Umzug oralcare: manueller Stack → Komodo-Stack (abyss)

Ziel: Die Website der Zahnarztpraxis läuft danach als Komodo-Stack **oralcare** aus einem fertigen Image
(Code, vendor und Assets sind im Image), Bilder liegen unter `/srv/oralcare/…`, die Datenbank bleibt im
bestehenden Volume. Ausfallzeit beim Umschalten: etwa 1–2 Minuten.

**Rückfallebene:** Der alte Ordner `/opt/oralcare-cms` und der alte Startbefehl bleiben unverändert erhalten.

Alle Befehle als root auf abyss.

---

## Dateien im Repo

| Datei | Zweck |
|---|---|
| `deploy/Dockerfile` | Multi-Stage-Build: PHP 8.5 + Apache, `composer install --no-dev` nach `composer.lock`, Website- und Sulu-Admin-Build, Cache vorgewärmt |
| `deploy/compose.yaml` | Komodo-Stack (Projektname `oralcare`), Port `10.0.0.2:8192`, Datenordner, bestehendes DB-Volume (extern) |
| `deploy/apache/oralcare-env.conf` | Reicht die Umgebungsvariablen an mod_php durch (sonst greift die eingecheckte `.env`) |
| `deploy/komodo/oralcare.toml` | Komodo-Resource-Sync: Stack „oralcare“ und Procedure „oralcare-backup“ (Aufbau wie bei wedora/debes) |
| `deploy/komodo/pre-deploy.sh` | Vor jedem Deploy: fehlende Secrets und DB-Volume prüfen, DB sichern (`pre-deploy_*.sql.gz`, letzte 5) |
| `deploy/backup.sh` + `deploy/backup/Dockerfile` | Backup-Dienst `backup` im Stack, gestartet von der Komodo-Procedure „oralcare-backup“ (02:30): DB + Originalbilder |
| `assets/admin/package-lock.json` | Neu: fixiert die npm-Pakete des Sulu-Admin-Builds (gab es vorher nicht) |
| `.dockerignore` | Keine Dumps, Bilder, `.env.local`, `vendor`, `node_modules` im Image |

Kein Composer-Update, kein Sulu-Upgrade, keine Änderung an Inhalten oder Sulu-Konfiguration.

---

## Was geprüft wurde (lokaler Probelauf am 09.11.2026)

Probelauf mit genau diesem Image und dieser `compose.yaml`, DB aus `~/rescue-thinkcentre/oralcare.sql.gz`,
Bilder aus dem lokalen Arbeitsordner (Stand 08.07.; die Projektkopie `~/oralcare-test/oralcare-cms` existiert nicht):

- Startseite, 8 Unterseiten, CSS: HTTP 200. Admin-Oberfläche und Admin-Assets: HTTP 200 (Login selbst nicht getestet).
- 22 von 24 Bildern der Startseite: 200. Die 2 fehlenden („blaues Zimmer neu“, hochgeladen am 26.07.) fehlen nur in der
  lokalen Bildkopie, nicht auf dem Server.
- Neue Bildformate werden in den gemounteten Ordner geschrieben (Rechte stimmen).
- Hinter einem vertrauenswürdigen Proxy mit `X-Forwarded-Proto: https` erzeugt Sulu `https://`-Canonical-URLs.
- Eigener DB-Benutzer statt root: Seiten, Schreibzugriffe und `doctrine:schema:validate` ok.
- Backup: DB-Dump zurückgespielt (93 Tabellen, 156 Medien), Bilder-Snapshots identisch mit der Quelle; Rotation getestet.

Unterwegs gefunden und behoben:

1. **mod_php sieht die Container-Umgebungsvariablen nicht.** Ohne `PassEnv` hätte die Website die Werte aus der
   eingecheckten `.env` genommen (DB auf `127.0.0.1:3307`). Live funktioniert es bisher nur, weil das alte Image keine
   `php.ini` hat. Ohne `php.ini` landen die Container-Variablen in `$_ENV`. Das neue Image nutzt die
   Produktions-`php.ini`, deshalb braucht es `PassEnv`.
2. **Sulus HTTP-Cache unterscheidet nicht zwischen http und https.** Wer eine Seite zuerst abruft, legt die Links für alle
   fest. Der Container-Healthcheck fragt deshalb `/admin/` ab statt der Startseite. Ein Healthcheck auf `/` per http
   hätte gecachte Seiten mit `http://`-Canonical/hreflang gefüllt.
3. **Backup-Rotation:** `rsync -a` übernimmt das alte Änderungsdatum des Bilderordners. Eine Aufräumregel nach Alter
   hätte jeden neuen Bilder-Snapshot sofort gelöscht. Jetzt wird nach Anzahl rotiert (14 Stück).

Hintergrunddienste: keine. Messenger läuft synchron (kein async-Transport), kein Scheduler, kein Cron.
`app:analytics:cleanup` ist ein manueller, interaktiver Befehl.

Trusted Proxies: Das Projekt kennt kein `REVERSE_PROXY_IPS`. `public/index.php` liest `TRUSTED_PROXIES` und vertraut
`X-Forwarded-For/-Port/-Proto`. Der Stack setzt `TRUSTED_PROXIES` aus `REVERSE_PROXY_IPS` (Standard `10.0.0.1`).
Den Host gibt Caddy per `header_up Host` direkt weiter, der wird nicht über Proxy-Header gelesen.

---

## 1. Vorab prüfen (ändert nichts, Seite bleibt online)

```bash
cd /opt/oralcare-cms

# a) Läuft live derselbe Code wie main? Sonst NICHT umziehen (das Image würde die Sulu-Version ändern).
git fetch -q origin && git status -sb | head -1 && git rev-parse --short HEAD origin/main
git status --short            # geänderte getrackte Dateien = Änderungen direkt auf dem Server → erst klären
grep -A1 '"name": "sulu/sulu"' composer.lock | tail -1      # erwartet: "version": "3.0.10"
docker exec oralcare-php-1 php -v | head -1                  # PHP-Version live

# b) Namen von Volume und Containern
docker volume ls | grep -i oralcare        # erwartet: oralcare_database_data
docker ps -a --format '{{.Names}}\t{{.Status}}' | grep -i oralcare

# c) Werte für die Komodo-Secrets (Ausgabe nicht weitergeben), Quellen siehe 2.3
cat compose.prod.yaml                     # DATABASE_URL, Root-Passwort
grep -E '^APP_SECRET=' .env               # .env ist unverändert gegenüber Git
grep -E '^SENTRY_DSN=' .env.local

# d) Platz
df -h /srv /var/lib/docker
du -sh var/storage public/uploads var/indexes

# e) Neuer Port 8192 frei? (keine Ausgabe = frei)
ss -ltnp | grep ':8192 '
# Firewall auf abyss: 8192 muss vom VPS (10.0.0.1) erreichbar sein, genau wie bisher 8080
ufw status 2>/dev/null | grep -E '8080|8192'; iptables -S 2>/dev/null | grep -E '8080|8192'
```
Gibt es für 8080 eine Firewall-Regel, die gleiche Regel vorab für 8192 anlegen.

Stand der Prüfung vom 10.11.2026:
- Port live: `10.0.0.2:8080`. Der neue Stack nutzt `10.0.0.2:8192`, Caddy wird beim Umschalten umgestellt (Schritt 3).
- `var/storage` 241M, `var/share` 35M, `var/indexes` 752K, `var/cache` 58M. `var/sessions` und `var/uploads` sind leer.
- `.env.local` enthält nur `APP_ENV=prod`, `SENTRY_DSN` (gesetzt) und die ungenutzten `DEBUG_MODE`/`#APP_URL`.
- Außerhalb der Datenordner schreibt die App nichts. Neuere Dateien gibt es nur in `vendor/`, aus Git und in `backups/`.

Stimmt bei a) etwas nicht oder heißt das Volume anders: **anhalten** und klären (`ORALCARE_DB_VOLUME` im Stack anpassen).

## 2. Vorbereiten (Seite bleibt online)

### 2.1 Repo
Auf dem Rechner: `deploy/`, `.dockerignore`, `assets/admin/package-lock.json` und die Löschung von `Makefile` /
`deploy/deploy.sh` committen und nach `main` pushen.

### 2.2 Datenordner anlegen und erste Kopie
```bash
mkdir -p /srv/oralcare/{storage,uploads,share,indexes} /srv/backups/oralcare
rsync -aH /opt/oralcare-cms/var/storage/   /srv/oralcare/storage/
rsync -aH /opt/oralcare-cms/public/uploads/ /srv/oralcare/uploads/
rsync -aH /opt/oralcare-cms/var/indexes/   /srv/oralcare/indexes/
chown -R 33:33 /srv/oralcare
diff <(cd /opt/oralcare-cms/var/storage && find . -type f | sort) \
     <(cd /srv/oralcare/storage && find . -type f | sort) && echo "storage identisch"
```

`var/share` wird **nicht** kopiert, `/srv/oralcare/share` startet leer. Darin liegen nur Caches: Sulus Seiten-Cache
(`prod/http_cache`) und der App-Cache (`prod/pools`). Der alte Seiten-Cache kann Seiten mit `http://`-Canonical
enthalten. Leer erzeugt Sulu alle Seiten frisch.

### 2.3 Komodo: Secrets anlegen, Resource Sync einbinden, NICHT deployen
1. **Settings → Variables**: vier Variablen mit Häkchen „Secret“ anlegen: `ORALCARE_APP_SECRET`,
   `ORALCARE_DATABASE_URL`, `ORALCARE_DB_ROOT_PASSWORD`, `ORALCARE_SENTRY_DSN` (Werte siehe unten).
2. **Syncs → New Resource Sync**: Quelle dieses Repo (`rextuus/oralcare-cms`, `main`), Resource Path
   `deploy/komodo/oralcare.toml`. Im Sync-Diff prüfen, dass nur der Stack „oralcare“ und die Procedure
   „oralcare-backup“ neu angelegt werden. Dann ausführen.
   Der Sync legt den Stack nur an (`deploy = false`), es startet nichts. Alles Übrige (Port 8192, Datenordner,
   `--wait`, Pre-Deploy-Prüfung, Build auf dem Server) steht in der TOML.

Die Pre-Deploy-Prüfung bricht jeden Deploy ab, solange eines der vier Secrets fehlt (Komodo lässt sonst
`[[…]]` wörtlich stehen) oder das Volume `oralcare_database_data` nicht existiert.

Werte:
- `ORALCARE_DB_ROOT_PASSWORD`: **exakt** das bisherige Root-Passwort aus `compose.prod.yaml`. Das bestehende Volume
  behält sein Passwort. Bei einem anderen Wert schlägt der DB-Healthcheck fehl und die Website startet nicht.
- `ORALCARE_DATABASE_URL`: bisheriger Wert aus `compose.prod.yaml`, Host `database`, also
  `mysql://root:<passwort>@database:3306/su_myapp?serverVersion=8.4&charset=utf8mb4`.
  Sonderzeichen im Passwort URL-kodieren (`@` → `%40`, `/` → `%2F`, `:` → `%3A`, `#` → `%23`).
- `ORALCARE_APP_SECRET`: für den Umzug den bisherigen Wert übernehmen. Live kommt er aus der eingecheckten `.env`,
  nicht aus `.env.local` und nicht aus dem Container. Ein neuer Wert würde alle Admin-Logins und „vertrauenswürdige
  Geräte“ der 2FA ungültig machen. Später rotieren, siehe Schritt 5.
- `ORALCARE_SENTRY_DSN`: der Wert von `SENTRY_DSN` aus `/opt/oralcare-cms/.env.local`. Ohne ihn gehen nach dem Umzug
  keine Fehlermeldungen mehr an Sentry.
- `APP_ENV=prod` setzt der Stack selbst. `DEBUG_MODE` und `APP_URL` aus `.env.local` werden nirgends verwendet und
  entfallen.

### 2.4 Image vorab bauen
Damit beim Umschalten nicht 7–8 Minuten gebaut wird, das Image einmal vorher bauen. Danach kommt beim Deploy alles aus
dem Docker-Cache:
```bash
git clone -q --depth 1 https://github.com/rextuus/oralcare-cms.git /tmp/oralcare-build
docker build -f /tmp/oralcare-build/deploy/Dockerfile --target prod -t oralcare-cms:prod /tmp/oralcare-build
rm -rf /tmp/oralcare-build
```

## 3. Umschalten (Wartungsfenster, abends, ~1–2 Minuten offline)

```bash
cd /opt/oralcare-cms

# 1. Sicherheits-Dump der Datenbank (DB läuft noch)
docker exec oralcare-database-1 sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --databases su_myapp' \
  | gzip > /srv/backups/oralcare/vor-umzug_$(date +%F_%H%M).sql.gz
zcat /srv/backups/oralcare/vor-umzug_*.sql.gz | tail -1     # muss "Dump completed" enthalten

# 2. Website stoppen (ab hier offline), damit keine Uploads mehr dazukommen
docker stop oralcare-php-1

# 3. Letzter Abgleich der Bilder (nur Änderungen seit 2.2, dauert Sekunden)
rsync -aH --delete /opt/oralcare-cms/var/storage/   /srv/oralcare/storage/
rsync -aH --delete /opt/oralcare-cms/public/uploads/ /srv/oralcare/uploads/
rsync -aH --delete /opt/oralcare-cms/var/indexes/   /srv/oralcare/indexes/
chown -R 33:33 /srv/oralcare

# 4. Alten Stack entfernen – OHNE -v, das DB-Volume bleibt
docker compose -p oralcare -f compose.yaml -f compose.prod.yaml down
```

5. **In Komodo: Stack „oralcare“ deployen.** Warten, bis `oralcare-php-1` „healthy“ ist; lokal prüfen:
   `curl -s -o /dev/null -w '%{http_code}\n' http://10.0.0.2:8192/admin/` → 200 (oder 302).

6. **Auf dem VPS: Caddy auf den neuen Port umstellen.** Im Caddyfile bei den oralcare-Domains
   `reverse_proxy 10.0.0.2:8080` → `reverse_proxy 10.0.0.2:8192` ändern (die `header_up`-Zeilen bleiben), dann
   `caddy validate --config /etc/caddy/Caddyfile && systemctl reload caddy` (bzw. das Reload des Caddy-Containers).
   Bis hier zeigt die Seite einen 502-Fehler.

7. Prüfen:
```bash
docker ps --format '{{.Names}}\t{{.Status}}' | grep oralcare     # beide "healthy"
for p in / /kontakt /leistungen /impressum /datenschutz; do
  curl -s -o /dev/null -w "%{http_code} $p\n" https://zahnarztpraxis-kaldauen.de$p; done
curl -s https://zahnarztpraxis-kaldauen.de/ | grep -oE '<link rel="canonical"[^>]*>'    # muss https:// sein
curl -s -o /dev/null -w "%{http_code} www\n" https://www.zahnarztpraxis-kaldauen.de/
docker logs oralcare-php-1 2>&1 | grep -E '"GET / ' | tail -2 | cut -d' ' -f1           # Absender: 10.0.0.1 erwartet
docker logs oralcare-php-1 2>&1 | grep -c -E '"level_name":"(ERROR|CRITICAL)"'          # 0 erwartet
```
Danach im Browser: Startseite mit Bildern, eine Unterseite, Admin-Login, ein Testbild hochladen und wieder löschen.

Ist das Canonical `http://` statt `https://`: Zeigt das Log als Absender nicht `10.0.0.1`, sondern z.B. `172.x.0.1`,
dann kommt Docker-NAT dazwischen. Diese Adresse bei `REVERSE_PROXY_IPS` ergänzen (`10.0.0.1,172.x.0.1`) und neu deployen.

## 4. Rückfall (wenn Schritt 3.7 scheitert)

Auf dem VPS zuerst Caddy zurück auf `reverse_proxy 10.0.0.2:8080` stellen und neu laden. Dann auf abyss:

```bash
# Komodo-Stack „oralcare“ stoppen (in Komodo oder):
docker compose -p oralcare down          # ohne -v!

# Uploads seit dem Umschalten zurückkopieren, dann alten Stack starten
rsync -aH /srv/oralcare/storage/ /opt/oralcare-cms/var/storage/
rsync -aH /srv/oralcare/uploads/ /opt/oralcare-cms/public/uploads/
# Alten Seiten-Cache verwerfen, sonst zeigt er den Stand von vor dem Umzug
rm -rf /opt/oralcare-cms/var/share/prod/http_cache
cd /opt/oralcare-cms && docker compose -p oralcare -f compose.yaml -f compose.prod.yaml up -d
```
Inhaltliche Änderungen in der DB bleiben in beiden Richtungen erhalten: dasselbe Volume, dieselbe Sulu-Version.
Im Notfall gibt es den Dump aus Schritt 3.1.

## 5. Nach dem Umzug

### 5.1 Nächtliches Backup: Komodo-Procedure „oralcare-backup“ (02:30)

Der Stack enthält den Dienst `backup`: Image `mysql:8.4` plus `rsync`, Skript `deploy/backup.sh`. Durch `replicas: 0`
startet er beim Deploy nicht, wird aber mitgebaut. Die Procedure startet ihn per `RunStackService`.

Die Procedure legt der Resource Sync aus 2.3 an (`deploy/komodo/oralcare.toml`, wie bei wedora/debes):
`RunStackService` auf `oralcare`/`backup` mit `no_deps` und `no_tty`, „Every day at 02:30“, `Europe/Berlin`,
`failure_alert` an. Sie ist sofort aktiv; vor dem Umzug scheitert sie (Stack läuft noch nicht) und meldet das. Ist der
Sync vor dem Umzugsabend fertig, die Procedure bis dahin in Komodo deaktivieren oder den Sync erst am Umzugstag ausführen.

Ein Testlauf vor dem ersten Nachtlauf (nach dem Umzug, Stack läuft):
1. Procedure einmal von Hand starten. Danach müssen in `/srv/backups/oralcare/db/` ein Dump und in
   `/srv/backups/oralcare/storage/` ein Snapshot plus `latest` liegen.
2. **Fehlermeldung prüfen**: In der Procedure-Execution unter `env` einmalig `BACKUP_SELFTEST_FAIL=1` setzen und
   starten. Das Skript bricht dann absichtlich mit Exit-Code 1 ab. Der Lauf muss in Komodo als fehlgeschlagen erscheinen,
   und der Failure-Alert muss ankommen. Danach `env` wieder leeren. Achtung: Die Änderung in der UI weicht von der
   TOML ab; der nächste Sync setzt sie zurück. Bleibt der Alert aus, wertet Komodo den Exit-Code nicht aus. Dann
   bitte melden, damit wir einen anderen Weg wählen.

Ablage: `/srv/backups/oralcare/db/` (Dumps) und `/srv/backups/oralcare/storage/<datum>` (Bilder-Snapshots, Hardlinks).
Es werden 14 Stück behalten. Die Ausgabe jedes Laufs steht im Komodo-Update der Procedure.
Jeder Fehler beendet das Skript mit Exit-Code ≠ 0: DB nicht erreichbar, Dump unvollständig, Bilder-Mount leer,
rsync-Fehler, Lauf parallel zu einem anderen.
Das sind lokale Backups auf demselben Server. Wenn debes/wedora irgendwohin extern gesichert werden, `/srv/backups/oralcare`
dort mit aufnehmen.

### 5.2 Eigener DB-Benutzer statt root (optional, eigener Termin)
Lokal getestet, risikoarm und jederzeit umkehrbar:
```bash
docker exec -it oralcare-database-1 sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
  CREATE USER 'oralcare'@'%' IDENTIFIED BY '<neues-passwort>';
  GRANT ALL PRIVILEGES ON su_myapp.* TO 'oralcare'@'%';
```
Dann das Secret `ORALCARE_DATABASE_URL` auf `mysql://oralcare:<neues-passwort>@database:3306/su_myapp?serverVersion=8.4&charset=utf8mb4`
ändern und neu deployen. Zurück: alten Wert eintragen. Backup und Healthcheck nutzen weiter root.

### 5.3 APP_SECRET rotieren (optional)
Der bisherige Wert steht in der eingecheckten `.env` im GitHub-Repo. Neuen Wert erzeugen (`openssl rand -hex 32`),
als Secret hinterlegen und neu deployen. Folge: Alle Admins müssen sich neu anmelden, 2FA-Geräte neu bestätigen.

### 5.4 Altlasten in /opt/oralcare-cms
Nichts davon kommt ins Image oder Repo: Komodo baut aus dem Git-Repo, `.dockerignore` schließt Dumps und Archive aus.

| Datei | Empfehlung |
|---|---|
| `db_dump.sql`, `data_dump.sql`, `sicherheit_vor_export.sql` | Alte DB-Dumps mit Kontakt- und Nutzerdaten. Nach 4 Wochen stabilem Betrieb löschen. Bis dahin `chmod 600`. |
| `backups/db_*.sql.gz` | DB-Dumps vom alten `make deploy` (22.–29.10.). Zusätzliche Rückfallebene, `chmod 700 backups`. Werden mit dem Ordner gelöscht. |
| `Makefile`, `deploy/deploy.sh` | Alter Deploy-Weg. In `/opt` **nicht mehr ausführen**. `make deploy` würde ein Projekt `oralcare-cms` mit leerer DB anlegen. |
| `compose.override.yaml`, `.env.local` | Werden vom alten Startbefehl bzw. live gebraucht. `.env.local` enthält den Sentry-DSN. Bleiben für den Rückfall. |
| `uploads_backup.tar.gz` | Überholt durch `/srv/oralcare` und das neue Backup. Nach erfolgreichem Umzug löschen. |
| `backup_for_local.sh`, `update.sh` | Alter Ablauf. `update.sh` nicht mehr benutzen. `backup_for_local.sh` wird von meinem lokalen `sync_from_live.sh` aufgerufen; beide müssen auf die neuen Pfade angepasst werden (Container `oralcare-database-1`, Bilder unter `/srv/oralcare/storage`). |
| Datei mit kryptischem Namen (`PR97…`) | Vermutlich versehentlich entstanden, etwa durch ein Passwort als Dateiname. Erst ansehen (`ls -la -- PR97*`, `file -- PR97*`, `head -c 200 -- PR97*`), dann löschen. War es ein Passwort, dieses Passwort ändern. |
| `compose.prod.yaml` | Enthält das Root-Passwort. Bleibt für den Rückfall, wird mit dem Ordner gelöscht. |
| Ganzer Ordner `/opt/oralcare-cms` | Nach 4 Wochen stabilem Betrieb und mindestens einem geprüften Backup aus 5.1 löschen. |

### 5.5 Künftige Deploys
Push nach `main`, dann in Komodo den Stack deployen. Das Image wird neu gebaut, die Daten in `/srv/oralcare` und im
DB-Volume bleiben. Datenbank-Schema-Änderungen passieren nie automatisch. Bringt ein späteres Update welche mit, vorher
`docker exec oralcare-php-1 bin/adminconsole doctrine:schema:update --dump-sql` ansehen und bewusst anwenden.
