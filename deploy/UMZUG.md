# Umzug oralcare: manueller Stack → Komodo-Stack (abyss)

Ziel: Die Website der Zahnarztpraxis läuft danach als Komodo-Stack **oralcare** aus einem fertigen Image
(Code, vendor und Assets sind im Image), Bilder liegen unter `/srv/oralcare/…`, die Datenbank bleibt im
bestehenden Volume, Caddy zeigt auf `10.0.0.2:8192`. Ausfallzeit beim Umschalten: etwa 3–5 Minuten.

Diese Datei ist die **einzige Quelle für den Ablauf**.

**Grundsatz:** `/opt/oralcare-cms` bleibt im Hauptweg unverändert (wird nur gelesen) und ist die Rückfallebene samt
altem Startbefehl. In `/opt` geschrieben wird nur im Rückfall und bei der Altlasten-Aufräumung Wochen später.

Rechner:
- **Arbeitsrechner**: dieses Repo, das homelab-Repo (Caddy auf dem VPS nur über `bin/caddy-deploy`).
- **abyss**: als `master`, **alle docker-Befehle mit `sudo`**.
- **Komodo-UI**.

---

## Dateien im Repo

| Datei | Zweck |
|---|---|
| `deploy/Dockerfile` | Multi-Stage-Build: PHP 8.5 + Apache, `composer install --no-dev` nach `composer.lock`, Website- und Sulu-Admin-Build, Cache vorgewärmt |
| `deploy/compose.yaml` | Komodo-Stack (Projektname `oralcare`), Port `10.0.0.2:8192`, Datenordner, bestehendes DB-Volume (extern) |
| `deploy/apache/oralcare-env.conf` | Reicht die Umgebungsvariablen an mod_php durch (sonst greift die eingecheckte `.env`) |
| `deploy/docker-entrypoint.sh` | Setzt beim Start den Besitzer leerer Daten-Mounts auf www-data |
| `deploy/komodo/oralcare.toml` | Komodo-Resource-Sync: Stack „oralcare“ und Procedure „oralcare-backup“ (Aufbau wie bei wedora/debes) |
| `deploy/komodo/pre-deploy.sh` | Vor jedem Deploy: fehlende Secrets und DB-Volume prüfen, DB sichern (`pre-deploy_*.sql.gz`, letzte 5) |
| `deploy/backup.sh` + `deploy/backup/Dockerfile` | Backup-Dienst `backup` im Stack, gestartet von der Komodo-Procedure „oralcare-backup“ (02:30): DB + Originalbilder |
| `assets/admin/package-lock.json` | Neu: fixiert die npm-Pakete des Sulu-Admin-Builds (gab es vorher nicht) |
| `.dockerignore` | Keine Dumps, Bilder, `.env.local`/`.env.*.local`, `vendor`, `node_modules` im Image |

Kein Composer-Update, kein Sulu-Upgrade, keine Änderung an Inhalten oder Sulu-Konfiguration.

---

## Hintergrund

### Wohin die Daten kommen

| Live (`/opt/oralcare-cms/…`) | Größe | Neu | Umzug |
|---|---|---|---|
| `var/storage` (Originale, Flysystem `default.storage` → `var/storage/default`) | 241M | `/srv/oralcare/storage` | kopieren, **unersetzlich** |
| `public/uploads` (Format-Cache `media/`) | ~133M | `/srv/oralcare/uploads` | kopieren (erspart Neuberechnung) |
| `var/indexes` (Suchindex, `SEAL_DSN=loupe://…`) | 752K | `/srv/oralcare/indexes` | kopieren (wäre neu erzeugbar) |
| `var/share` (nur Caches: `prod/http_cache`, `prod/pools`) | 35M | `/srv/oralcare/share` | **nicht** kopieren, startet leer |
| `var/cache` | 58M | im Image | – |
| `var/sessions`, `var/uploads` | leer | – | – |
| DB | Volume | `oralcare_database_data` (`external: true`) | bleibt, wird nicht kopiert |

`var/share` startet leer, weil der alte Seiten-Cache Seiten mit `http://`-Canonical enthalten kann. Leer erzeugt Sulu
alle Seiten frisch. Admin-Sitzungen überleben den Umzug vermutlich nicht: einmal neu anmelden, kein Datenverlust.

### Herkunft der Secrets (Stand 10.11.2026)

| Komodo-Secret | Quelle live |
|---|---|
| `ORALCARE_DATABASE_URL` | `DATABASE_URL` in `compose.prod.yaml` (Container-Umgebung), Host `database` |
| `ORALCARE_DB_ROOT_PASSWORD` | Root-Passwort in `compose.prod.yaml` |
| `ORALCARE_APP_SECRET` | **effektiver** Wert, siehe B3: `.env.local` überschreibt `.env` |
| `ORALCARE_SENTRY_DSN` | `SENTRY_DSN` in `.env.local` (gesetzt) |

`APP_ENV=prod` setzt der Stack selbst. `DEBUG_MODE` und `APP_URL` aus `.env.local` werden nirgends verwendet.

### Was geprüft wurde (lokaler Probelauf am 09./10.11.2026)

Probelauf mit genau diesem Image und dieser `compose.yaml`, DB aus `~/rescue-thinkcentre/oralcare.sql.gz`,
Bilder aus dem lokalen Arbeitsordner (Stand 08.07.):

- Startseite, 8 Unterseiten, CSS: HTTP 200. Admin-Oberfläche und Admin-Assets: HTTP 200 (Login selbst nicht getestet).
- 22 von 24 Bildern der Startseite: 200. Die 2 fehlenden („blaues Zimmer neu“, hochgeladen am 26.07.) fehlen nur in der
  lokalen Bildkopie, nicht auf dem Server.
- Neue Bildformate werden in den gemounteten Ordner geschrieben (Rechte stimmen).
- Hinter einem vertrauenswürdigen Proxy mit `X-Forwarded-Proto: https` erzeugt Sulu `https://`-Canonical-URLs.
- Eigener DB-Benutzer statt root: Seiten, Schreibzugriffe und `doctrine:schema:validate` ok.
- Backup: DB-Dump zurückgespielt (93 Tabellen, 156 Medien), Bilder-Snapshots identisch mit der Quelle, Rotation getestet.
- `pre-deploy.sh`: fehlendes Secret und fehlendes Volume brechen ab, gestoppte DB wird übersprungen, Dumps rotieren (5).
- `up -d --wait` funktioniert mit dem Dienst `backup` (`replicas: 0`).

Unterwegs gefunden und behoben:

1. **mod_php sieht die Container-Umgebungsvariablen nicht.** Ohne `PassEnv` hätte die Website die Werte aus der
   eingecheckten `.env` genommen (DB auf `127.0.0.1:3307`). Live funktioniert es bisher nur, weil das alte Image keine
   `php.ini` hat. Ohne `php.ini` landen die Container-Variablen in `$_ENV`. Das neue Image nutzt die
   Produktions-`php.ini`, deshalb braucht es `PassEnv`.
2. **Sulus HTTP-Cache unterscheidet nicht zwischen http und https.** Wer eine Seite zuerst abruft, legt die Links für alle
   fest. Der Container-Healthcheck fragt deshalb `/admin/` ab statt der Startseite.
3. **Backup-Rotation:** `rsync -a` übernimmt das alte Änderungsdatum des Bilderordners. Eine Aufräumregel nach Alter
   hätte jeden neuen Bilder-Snapshot sofort gelöscht. Jetzt wird nach Anzahl rotiert (14 Stück).

Hintergrunddienste: keine. Messenger läuft synchron, kein Scheduler, kein Cron. `app:analytics:cleanup` ist ein
manueller, interaktiver Befehl.

Trusted Proxies: `public/index.php` liest `TRUSTED_PROXIES` und vertraut `X-Forwarded-For/-Port/-Proto`. Der Stack setzt
`TRUSTED_PROXIES` aus `REVERSE_PROXY_IPS` (`10.0.0.1`). Den Host gibt Caddy per `header_up Host` direkt weiter.

---

## A. Arbeitsrechner: Repo

Deploy-Dateien sind in `main` (Commit `7d4d027`, gepusht). Vor dem Umzug muss der Stand, aus dem Komodo baut, in
`origin/main` sein. Danach bis nach dem Umzug nichts mehr nach `main` pushen, was das Image ändert.

## B. Vorbereitung (Tage vorher, Seite bleibt online, `/opt` wird nur gelesen)

### B1. abyss: Vorab-Prüfungen
```bash
cd /opt/oralcare-cms

# Läuft live derselbe Code wie main? Sonst NICHT umziehen (das Image würde die Sulu-Version ändern).
git rev-parse --short HEAD
git ls-remote https://github.com/rextuus/oralcare-cms.git refs/heads/main | cut -c1-7
git status --short --untracked-files=no                     # leer erwartet
grep -A1 '"name": "sulu/sulu"' composer.lock | tail -1      # "version": "3.0.10"

sudo docker volume ls | grep -i oralcare                     # oralcare_database_data
sudo docker ps -a --format '{{.Names}}\t{{.Status}}' | grep -i oralcare
df -h /srv /var/lib/docker

# Port 8192 frei? (keine Ausgabe = frei) und Firewall wie bei 8080?
sudo ss -ltnp | grep ':8192 '
sudo ufw status 2>/dev/null | grep -E '8080|8192'; sudo iptables -S 2>/dev/null | grep -E '8080|8192'
```
Die beiden Hashes unterscheiden sich erwartungsgemäß, weil `main` die Deploy-Dateien zusätzlich enthält. Auf dem
**Arbeitsrechner** prüfen, dass sich sonst nichts unterscheidet (`<live>` = Hash aus der ersten Zeile):
```bash
git fetch -q origin
git diff --stat <live> origin/main -- . ':!deploy' ':!.dockerignore' ':!.gitignore' ':!assets/admin/package-lock.json' ':!Makefile'
```
Erwartet ist eine leere Ausgabe, höchstens `compose.yaml` mit `restart: unless-stopped`, was das Image nicht betrifft.

**Anhalten**, wenn: der Diff Code, Templates, Konfiguration oder `composer.lock` zeigt, `git status` auf abyss etwas
zeigt oder das Volume anders heißt. Gibt es eine Firewall-Regel für 8080, die gleiche für 8192 anlegen.

### B2. abyss: Image bauen und www-data-UID/GID ermitteln
Damit beim Umschalten nicht 7–8 Minuten gebaut wird. Komodo nutzt beim Deploy den Docker-Cache.
```bash
git clone -q --depth 1 https://github.com/rextuus/oralcare-cms.git /tmp/oralcare-build
sudo docker build -f /tmp/oralcare-build/deploy/Dockerfile --target prod -t oralcare-cms:prod /tmp/oralcare-build
rm -rf /tmp/oralcare-build

# Besitzer für die Datenordner: www-data im NEUEN Image (Debian-Basis: 33:33, Alpine wäre 82:82)
WWW_ID=$(sudo docker run --rm --entrypoint sh oralcare-cms:prod -c 'echo "$(id -u www-data):$(id -g www-data)"')
echo "$WWW_ID"
```
Den Wert notieren. In D3 wird er neu ermittelt, weil die Shell-Variable nicht bis zum Umzugsabend lebt.

### B3. abyss: Werte für die Secrets ablesen (Ausgabe nicht weitergeben)
```bash
cd /opt/oralcare-cms
sudo cat compose.prod.yaml                    # DATABASE_URL und MYSQL_ROOT_PASSWORD

# APP_SECRET: maßgeblich ist der EFFEKTIVE Wert. Symfony lädt .env, dann .env.local (überschreibt),
# dann .env.prod und .env.prod.local (gibt es live nicht).
grep -E '^APP_SECRET=' .env.local             # steht er hier → DIESEN Wert nehmen
grep -E '^APP_SECRET=' .env                   # nur wenn er nicht in .env.local steht
# Gegenprobe: so, wie die laufende App ihn tatsächlich ermittelt – muss mit dem gewählten Wert übereinstimmen
sudo docker exec oralcare-php-1 php -r 'require "vendor/autoload.php"; (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env"); echo $_SERVER["APP_SECRET"], PHP_EOL;'

grep -E '^SENTRY_DSN=' .env.local
```
Einordnung:
- `ORALCARE_DB_ROOT_PASSWORD`: **exakt** das bisherige Root-Passwort. Das Volume behält sein Passwort. Bei einem anderen
  Wert schlägt der DB-Healthcheck fehl und die Website startet nicht.
- `ORALCARE_DATABASE_URL`: `mysql://root:<passwort>@database:3306/su_myapp?serverVersion=8.4&charset=utf8mb4`.
  Sonderzeichen im Passwort URL-kodieren (`@` → `%40`, `/` → `%2F`, `:` → `%3A`, `#` → `%23`).
- `ORALCARE_APP_SECRET`: der effektive Wert von oben. Ein anderer Wert würde alle Admin-Logins und „vertrauenswürdige
  Geräte“ der 2FA ungültig machen. Rotieren erst später (Abschnitt H3).
- `ORALCARE_SENTRY_DSN`: Wert aus `.env.local`. Ohne ihn gehen keine Fehlermeldungen mehr an Sentry.

### B4. abyss: Datenordner anlegen, Vorkopie
```bash
WWW_ID=$(sudo docker run --rm --entrypoint sh oralcare-cms:prod -c 'echo "$(id -u www-data):$(id -g www-data)"')
echo "$WWW_ID"

sudo mkdir -p /srv/oralcare/{storage,uploads,share,indexes} /srv/backups/oralcare
sudo rsync -aH /opt/oralcare-cms/var/storage/    /srv/oralcare/storage/
sudo rsync -aH /opt/oralcare-cms/public/uploads/ /srv/oralcare/uploads/
sudo rsync -aH /opt/oralcare-cms/var/indexes/    /srv/oralcare/indexes/
sudo chown -R "$WWW_ID" /srv/oralcare
sudo chmod 700 /srv/backups/oralcare

diff <(sudo sh -c 'cd /opt/oralcare-cms/var/storage && find . -type f | sort') \
     <(sudo sh -c 'cd /srv/oralcare/storage && find . -type f | sort') && echo "storage identisch"
```

### B5. Arbeitsrechner: Caddy-Trockenlauf (homelab-Repo)
Im homelab-Repo ist die Änderung vorbereitet, Stand 10.11. ist sie noch nicht committet:
- `vps/Caddyfile`: `reverse_proxy 10.0.0.2:8080` → `reverse_proxy 10.0.0.2:8192`, die `header_up`-Zeilen bleiben;
- `inventory.yaml` → `projects.oralcare.port: 8192`.
```bash
cd ~/Documents/programming/homelab
git diff -- vps/Caddyfile inventory.yaml     # oralcare: 8080 → 8192, header_up unverändert
bin/caddy-deploy                             # Trockenlauf: Inventar-Abgleich, Diff live ↔ Repo, caddy validate
```
Der Diff live ↔ Repo darf **nur** die oralcare-Zeile `8080 → 8192` zeigen. Sonst erst klären.

**Achtung:** Ab jetzt bis zum Umzug **kein** `bin/caddy-deploy --apply`, auch nicht für andere Sites. Es würde die
Praxis-Seite auf den noch leeren Port 8192 umstellen (502).

## C. Komodo-UI (am Umzugstag, vor dem Wartungsfenster)

1. **Settings → Variables**: vier Variablen mit Häkchen „Secret“ anlegen: `ORALCARE_APP_SECRET`,
   `ORALCARE_DATABASE_URL`, `ORALCARE_DB_ROOT_PASSWORD`, `ORALCARE_SENTRY_DSN` (Werte aus B3).
2. **Syncs → New Resource Sync**: Quelle `rextuus/oralcare-cms`, `main`, Resource Path `deploy/komodo/oralcare.toml`.
   Im Sync-Diff prüfen, dass nur der Stack „oralcare“ und die Procedure „oralcare-backup“ neu angelegt werden. Dann
   ausführen. Der Sync legt nur an (`deploy = false`), es startet nichts.
3. Procedure **oralcare-backup deaktivieren**, bis der Umzug durch ist. Sonst schlägt sie vorher fehl und alarmiert.

Die Pre-Deploy-Prüfung bricht jeden Deploy ab, solange eines der vier Secrets fehlt (Komodo ließe sonst `[[…]]`
wörtlich stehen) oder das Volume `oralcare_database_data` nicht existiert.

## D. Umschalten (Wartungsfenster, abends)

### D1. abyss: Sicherheits-Dump (Seite noch online)
```bash
cd /opt/oralcare-cms
sudo docker exec oralcare-database-1 sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --databases su_myapp' \
  | gzip | sudo tee /srv/backups/oralcare/vor-umzug_$(date +%F_%H%M).sql.gz >/dev/null
sudo sh -c 'zcat /srv/backups/oralcare/vor-umzug_*.sql.gz | tail -1'      # muss "Dump completed" enthalten
```

### D2. abyss: manuelle Website stoppen (ab hier offline)
```bash
sudo docker stop oralcare-php-1          # keine neuen Uploads mehr; DB läuft weiter
```

### D3. abyss: letzter Abgleich (nur Änderungen seit B4, `/opt` nur gelesen)
```bash
WWW_ID=$(sudo docker run --rm --entrypoint sh oralcare-cms:prod -c 'echo "$(id -u www-data):$(id -g www-data)"')
echo "$WWW_ID"
sudo rsync -aH --delete /opt/oralcare-cms/var/storage/    /srv/oralcare/storage/
sudo rsync -aH --delete /opt/oralcare-cms/public/uploads/ /srv/oralcare/uploads/
sudo rsync -aH --delete /opt/oralcare-cms/var/indexes/    /srv/oralcare/indexes/
sudo chown -R "$WWW_ID" /srv/oralcare
```

### D4. abyss: manuellen Stack entfernen
```bash
cd /opt/oralcare-cms
sudo docker compose -p oralcare -f compose.yaml -f compose.prod.yaml down    # OHNE -v: Volume bleibt, Dateien bleiben
sudo docker volume ls | grep oralcare_database_data                          # muss noch da sein
```

### D5. Komodo-UI: Stack „oralcare“ → Deploy
Ablauf:
1. `pre-deploy.sh` prüft Secrets und Volume. Die DB ist gestoppt, die Sicherung wird übersprungen; D1 deckt das ab.
2. Build, überwiegend aus dem Cache von B2.
3. `up -d --wait`. Grün ist der Deploy erst, wenn php und database healthy sind.

Scheitert der Deploy: Ausgabe im Komodo-Update lesen; nicht weiterraten, sondern Rückfall (Abschnitt R).

### D6. abyss: neuer Stack antwortet?
```bash
sudo docker ps --format '{{.Names}}\t{{.Status}}' | grep oralcare           # beide "healthy"
curl -s -o /dev/null -w '%{http_code}\n' http://10.0.0.2:8192/admin/        # 200 oder 302
```

### D7. Arbeitsrechner: Caddy umstellen (homelab-Repo)
```bash
cd ~/Documents/programming/homelab
bin/caddy-deploy             # Trockenlauf noch einmal: nur die oralcare-Zeile 8080 → 8192
bin/caddy-deploy --apply     # sichert die alte Datei, installiert, reload; bei Fehler automatisch zurück
```
Ab hier ist die Seite wieder online.

### D8. Prüfen
Arbeitsrechner:
```bash
for p in / /kontakt /leistungen /impressum /datenschutz; do
  curl -s -o /dev/null -w "%{http_code} $p\n" https://zahnarztpraxis-kaldauen.de$p; done
curl -s https://zahnarztpraxis-kaldauen.de/ | grep -oE '<link rel="canonical"[^>]*>'    # https:// erwartet
curl -s -o /dev/null -w "%{http_code} www\n" https://www.zahnarztpraxis-kaldauen.de/
```
abyss:
```bash
sudo docker logs oralcare-php-1 2>&1 | grep -E '"GET / ' | tail -2 | cut -d' ' -f1     # Absender 10.0.0.1 erwartet
sudo docker logs oralcare-php-1 2>&1 | grep -c -E '"level_name":"(ERROR|CRITICAL)"'    # 0 erwartet
```
Im Browser: Startseite mit Bildern, eine Unterseite, Admin-Login, ein Testbild hochladen und wieder löschen.

Ist das Canonical `http://`: Zeigt das Log als Absender nicht `10.0.0.1`, sondern z.B. `172.x.0.1`, dann kommt Docker-NAT
dazwischen. In `deploy/komodo/oralcare.toml` `REVERSE_PROXY_IPS = 10.0.0.1,172.x.0.1` setzen, pushen, Sync, Deploy.

## R. Rückfall (wenn D5–D8 scheitert)

1. **Arbeitsrechner, homelab-Repo:** In `vps/Caddyfile` die oralcare-Zeile zurück auf `reverse_proxy 10.0.0.2:8080`
   setzen, in `inventory.yaml` `port: 8080`. Dann:
   ```bash
   cd ~/Documents/programming/homelab
   bin/caddy-deploy             # Trockenlauf: nur 8192 → 8080
   bin/caddy-deploy --apply
   ```
   Wurde D7 noch nicht ausgeführt, entfällt dieser Schritt.
2. **Komodo-UI:** Stack „oralcare“ → Stop. Oder auf abyss `cd / && sudo docker compose -p oralcare down`; dabei
   **niemals** `-v`.
3. **abyss:** alten Stack starten. Dies ist der einzige Schritt, der in `/opt` schreibt:
   ```bash
   # Nur wenn seit D2 im neuen Stack Bilder hochgeladen wurden:
   sudo rsync -aH /srv/oralcare/storage/ /opt/oralcare-cms/var/storage/
   sudo rsync -aH /srv/oralcare/uploads/ /opt/oralcare-cms/public/uploads/
   # Alten Seiten-Cache verwerfen, sonst zeigt er den Stand von vor dem Umzug
   sudo rm -rf /opt/oralcare-cms/var/share/prod/http_cache
   cd /opt/oralcare-cms && sudo docker compose -p oralcare -f compose.yaml -f compose.prod.yaml up -d
   ```

Inhaltliche Änderungen in der DB bleiben in beiden Richtungen erhalten: dasselbe Volume, dieselbe Sulu-Version.
Im Notfall gibt es den Dump aus D1.

## G. Nach dem Umzug

### G1. Komodo-UI: Backup aktivieren und testen
1. Procedure `oralcare-backup` wieder **aktivieren** und einmal von Hand starten. Danach auf abyss:
   ```bash
   sudo ls -la /srv/backups/oralcare/db/ /srv/backups/oralcare/storage/   # ein Dump, ein Snapshot, latest
   ```
2. **Fehlermeldung prüfen:** In der Procedure-Execution unter `env` einmalig `BACKUP_SELFTEST_FAIL=1` setzen und
   starten. Das Skript bricht absichtlich mit Exit-Code 1 ab. Der Lauf muss in Komodo als fehlgeschlagen erscheinen, und
   der Failure-Alert muss ankommen. Danach `env` wieder leeren; der nächste Sync würde es ohnehin zurücksetzen. Bleibt
   der Alert aus, wertet Komodo den Exit-Code nicht aus. Dann melden, damit wir einen anderen Weg wählen.

Der Backup-Dienst nutzt das Image `mysql:8.4` plus `rsync` und das Skript `deploy/backup.sh`. Durch `replicas: 0`
startet er beim Deploy nicht, wird aber mitgebaut. Die Procedure startet ihn per `RunStackService` (`no_deps`,
„Every day at 02:30“, Europe/Berlin, `failure_alert`).
- Ablage: `/srv/backups/oralcare/db/su_myapp_*.sql.gz` und `/srv/backups/oralcare/storage/<datum>` (Snapshots mit
  Hardlinks), je 14 Stände. Dazu kommen die letzten 5 `pre-deploy_*.sql.gz` aus Deploys.
- Jeder Fehler endet mit Exit-Code ≠ 0: DB nicht erreichbar, Dump unvollständig, Bilder-Mount leer, rsync-Fehler, Lauf
  parallel zu einem anderen.
- Das sind lokale Backups auf demselben Server. Werden debes/wedora extern gesichert, `/srv/backups/oralcare` dort mit
  aufnehmen.

### G2. Arbeitsrechner: homelab-Inventar nachziehen
In `inventory.yaml` → `projects.oralcare`:
- `komodo_stack: oralcare` und `status: live-komodo`.
- `data`: `/srv/oralcare/{storage,uploads,share,indexes}` (Bind-Mounts) und das Volume `oralcare_database_data`.
- `backup`: `missing` entfernen; `time: "02:30"`, `what: DB + Originalbilder`, `target: /srv/backups/oralcare`,
  `active: true`. Die bisherige Notiz nennt `/srv/oralcare/backups`, richtig ist `/srv/backups/oralcare`.
- `notes`: `/opt/oralcare-cms` nur noch Rückfallebene bis <Datum + 4 Wochen>.

Dann `bin/check` laufen lassen. Die Warnung `backup-missing:oralcare` muss verschwinden; in `test/test_homelab.py`
steht sie in `BASELINE_WARNINGS`. Außerdem den Punkt „oralcare … auf Komodo umstellen“ in `docs/offene-punkte.md`
abhaken.

## H. Später

### H1. Eigener DB-Benutzer statt root (optional, eigener Termin)
Lokal getestet, risikoarm und jederzeit umkehrbar:
```bash
sudo docker exec -it oralcare-database-1 sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
  CREATE USER 'oralcare'@'%' IDENTIFIED BY '<neues-passwort>';
  GRANT ALL PRIVILEGES ON su_myapp.* TO 'oralcare'@'%';
```
Dann das Secret `ORALCARE_DATABASE_URL` auf `mysql://oralcare:<neues-passwort>@database:3306/su_myapp?serverVersion=8.4&charset=utf8mb4`
ändern und neu deployen. Zurück: alten Wert eintragen. Backup und Healthcheck nutzen weiter root.

### H2. Künftige Deploys
Push nach `main`, dann in Komodo den Stack deployen. Das Image wird neu gebaut, `pre-deploy.sh` sichert vorher die DB, die
Daten in `/srv/oralcare` und im DB-Volume bleiben. Datenbank-Schema-Änderungen passieren nie automatisch. Bringt ein
späteres Update welche mit, vorher
`sudo docker exec oralcare-php-1 bin/adminconsole doctrine:schema:update --dump-sql` ansehen und bewusst anwenden.

### H3. APP_SECRET rotieren (optional)
Der bisherige Wert aus `.env` steht im GitHub-Repo. Neuen Wert erzeugen (`openssl rand -hex 32`), Secret
`ORALCARE_APP_SECRET` ändern und neu deployen. Folge: Alle Admins müssen sich neu anmelden, 2FA-Geräte neu bestätigen.

### H4. Altlasten in /opt/oralcare-cms (frühestens nach 4 Wochen stabilem Betrieb)
Erst nach mindestens einem geprüften Backup aus G1. Die Aufräumung schreibt bewusst in `/opt`. Nichts davon kommt ins
Image oder Repo: Komodo baut aus Git, `.dockerignore` und `.gitignore` schließen Dumps und Archive aus.

| Datei | Empfehlung |
|---|---|
| `db_dump.sql`, `data_dump.sql`, `sicherheit_vor_export.sql` | Alte DB-Dumps mit Kontakt- und Nutzerdaten. Löschen. Bis dahin können sie mit `sudo chmod 600` geschützt werden (ändert `/opt`, ist aber unkritisch). |
| `backups/db_*.sql.gz` | DB-Dumps vom alten `make deploy` (22.–29.10.). Zusätzliche Rückfallebene, wird mit dem Ordner gelöscht. |
| `Makefile`, `deploy/deploy.sh` | Alter Deploy-Weg. In `/opt` **nicht mehr ausführen**: `make deploy` würde ein Projekt `oralcare-cms` mit leerer DB anlegen. |
| `compose.prod.yaml`, `compose.override.yaml`, `.env.local` | Gehören zum alten Startbefehl (Rückfall). `compose.prod.yaml` enthält das Root-Passwort, `.env.local` den Sentry-DSN. Werden mit dem Ordner gelöscht. |
| `uploads_backup.tar.gz` | Überholt durch `/srv/oralcare` und das neue Backup. Löschen. |
| `backup_for_local.sh`, `update.sh` | Alter Ablauf. `update.sh` nicht mehr benutzen. `backup_for_local.sh` und das lokale `sync_from_live.sh` müssen auf die neuen Pfade angepasst werden (Container `oralcare-database-1`, Bilder unter `/srv/oralcare/storage`). |
| Datei mit kryptischem Namen (`PR97…`) | Vermutlich versehentlich entstanden, etwa durch ein Passwort als Dateiname. Ansehen (`ls -la -- PR97*`, `file -- PR97*`, `head -c 200 -- PR97*`), dann löschen. War es ein Passwort, dieses Passwort ändern. |
| Ganzer Ordner `/opt/oralcare-cms` | Zum Schluss löschen. Danach gibt es keinen Rückfall auf den alten Stack mehr. |
