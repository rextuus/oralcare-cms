# Deployment für oralcare-cms
#
#   make deploy              # prüft lokal, sichert die DB und deployt origin/main auf den Server
#   make deploy BRANCH=foo   # anderen Branch deployen
#   make backup              # nur DB-Backup auf dem Server
#   make logs                # Logs des PHP-Containers auf dem Server
#
# Alle Variablen lassen sich überschreiben, z.B. `make deploy SERVER_IP=1.2.3.4`.

SERVER_USER ?= master
SERVER_IP   ?= 192.168.178.135
REMOTE_DIR  ?= /root/oralcare-cms
BRANCH      ?= main
HEALTH_URL  ?= http://localhost:8080/
# Neueste lokal verfügbare PHP-Version (das Default-`php` kann zu alt für Composer sein)
PHP         ?= $(shell command -v php8.5 || command -v php8.4 || command -v php8.3 || command -v php)

SSH         := ssh -t $(SERVER_USER)@$(SERVER_IP)
REMOTE_TMP  := /tmp/oralcare-deploy.sh

.PHONY: deploy preflight upload backup logs

deploy: preflight upload
	@echo "🚀 Deploye $(BRANCH) nach $(SERVER_IP)..."
	$(SSH) "sudo bash $(REMOTE_TMP) deploy '$(REMOTE_DIR)' '$(BRANCH)' '$(HEALTH_URL)'"

backup: upload
	$(SSH) "sudo bash $(REMOTE_TMP) backup '$(REMOTE_DIR)'"

logs:
	$(SSH) "sudo sh -c 'cd $(REMOTE_DIR) && docker compose logs --tail=200 -f php'"

upload:
	@scp -q deploy/remote-deploy.sh $(SERVER_USER)@$(SERVER_IP):$(REMOTE_TMP)

# Lokale Checks: Es wird nur deployt, was committet, gepusht und ohne Sicherheitslücken ist
preflight:
	@test "$(CURDIR)" != "$(REMOTE_DIR)" \
		|| (echo "❌ make deploy läuft auf deinem Rechner, nicht auf dem Server. Es verbindet sich selbst per SSH."; exit 1)
	@test -n "$(PHP)" || (echo "❌ Kein PHP gefunden. Setzen mit: make deploy PHP=/pfad/zu/php"; exit 1)
	@echo "🔍 Prüfe lokalen Stand..."
	@git diff --quiet && git diff --cached --quiet \
		|| (echo "❌ Uncommittete Änderungen vorhanden. Erst committen oder stashen."; exit 1)
	@git fetch -q origin $(BRANCH)
	@test "$$(git rev-parse HEAD)" = "$$(git rev-parse origin/$(BRANCH))" \
		|| (echo "❌ Lokaler Stand != origin/$(BRANCH). Erst pushen bzw. pullen."; exit 1)
	@$(PHP) $$(command -v composer) validate --no-check-publish -q \
		|| (echo "❌ composer.json/composer.lock ungültig."; exit 1)
	@$(PHP) $$(command -v composer) audit --locked -q \
		|| (echo "❌ composer audit meldet Sicherheitslücken."; exit 1)
	@echo "✅ Preflight ok ($$(git rev-parse --short HEAD))"
