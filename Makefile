# Deployment für oralcare-cms – wird auf dem Server im Projektordner ausgeführt
#
#   make deploy              # sichert die DB und deployt origin/main
#   make deploy BRANCH=foo   # anderen Branch deployen
#   make backup              # nur DB-Backup
#   make logs                # Logs des PHP-Containers
#
# Variablen lassen sich überschreiben, z.B. `make deploy HEALTH_URL=http://localhost/`.

BRANCH     ?= main
HEALTH_URL ?= http://localhost:8080/

# Das Skript wird vor dem Start kopiert, weil `git merge` es sonst während der Ausführung ändern kann
RUN_SCRIPT := cp deploy/deploy.sh /tmp/oralcare-deploy.sh && bash /tmp/oralcare-deploy.sh

.PHONY: help deploy backup logs status

help:
	@echo "make deploy              DB sichern und origin/$(BRANCH) deployen"
	@echo "make deploy BRANCH=foo   anderen Branch deployen"
	@echo "make backup              nur DB-Backup nach backups/"
	@echo "make status              laufende Container und PHP-Version anzeigen"
	@echo "make logs                Logs des PHP-Containers (Strg+C beendet)"

deploy:
	@$(RUN_SCRIPT) deploy '$(CURDIR)' '$(BRANCH)' '$(HEALTH_URL)'

backup:
	@$(RUN_SCRIPT) backup '$(CURDIR)'

logs:
	docker compose logs --tail=200 -f php

status:
	@docker compose ps
	@docker compose exec -T php php -v 2>/dev/null | head -1 | grep . || echo "PHP-Container läuft nicht"
