#!/bin/sh
# Bereitet die gemounteten Datenverzeichnisse vor und startet dann Apache.
set -e

cd /var/www/html

# Leere, frisch angelegte Mounts gehören sonst root; Inhalte werden beim Umzug einmalig per chown gesetzt.
for dir in var/storage var/share var/indexes public/uploads; do
    mkdir -p "$dir"
    [ "$(stat -c %u "$dir")" = "33" ] || chown www-data:www-data "$dir"
done

exec docker-php-entrypoint "$@"
