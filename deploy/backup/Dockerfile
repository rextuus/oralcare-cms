# Backup-Dienst für den Stack „oralcare“ (Komodo-Procedure „oralcare-backup“ → RunStackService „backup“).
# Basis mysql:8.4, damit mysqldump zur Server-Version passt; rsync für die Bilder-Snapshots, flock gegen Doppelstarts.
FROM mysql:8.4

RUN microdnf install -y rsync util-linux tzdata && microdnf clean all

COPY deploy/backup.sh /usr/local/bin/oralcare-backup
RUN chmod 755 /usr/local/bin/oralcare-backup

ENTRYPOINT ["oralcare-backup"]
