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

.PHONY: deploy backup logs

deploy:
	@$(RUN_SCRIPT) deploy '$(CURDIR)' '$(BRANCH)' '$(HEALTH_URL)'

backup:
	@$(RUN_SCRIPT) backup '$(CURDIR)'

logs:
	docker compose logs --tail=200 -f php
