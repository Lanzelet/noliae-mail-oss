#!/bin/bash
# Noliae Mail OSS — script de sauvegarde quotidienne.
#
# Lit l'activation et l'heure depuis app_settings (table DB), puis dump :
#   - Postgres complet (pg_dump)
#   - Maildirs Dovecot (/var/vmail)
#   - Bucket MinIO (attachments)
# Tout va dans /backups/<date>.tar.gz, conservation 30 jours.
#
# À déclencher depuis le host (ou via le service web container : entrypoint
# peut spawn ce script en background via `flock` + `at`/`sleep`).
set -e

BACKUP_DIR="${BACKUP_DIR:-/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

# Lit les settings via web container (Laravel cache OK)
SETTINGS=$(docker exec "$(docker ps -q -f name=web)" sh -c "HOME=/tmp php -r '
require \"/var/www/html/vendor/autoload.php\";
\$app = require \"/var/www/html/bootstrap/app.php\";
\$app->make(\"Illuminate\\Contracts\\Console\\Kernel\")->bootstrap();
echo (\App\Services\AppSettings::bool(\"backup_enabled\") ? 1 : 0) . \"|\" . \App\Services\AppSettings::int(\"backup_hour_utc\", 0);
' 2>/dev/null")

ENABLED="${SETTINGS%|*}"
HOUR="${SETTINGS##*|}"

if [ "$ENABLED" != "1" ]; then
  echo "[backup] Disabled in admin settings — skip."
  exit 0
fi

# Vérifie qu'on est dans la bonne heure
CURRENT_HOUR=$(date -u +%H)
if [ "$CURRENT_HOUR" != "$(printf '%02d' "$HOUR")" ]; then
  echo "[backup] Skipped — current hour $CURRENT_HOUR != configured $HOUR UTC"
  exit 0
fi

mkdir -p "$BACKUP_DIR"
STAMP=$(date -u +%Y%m%d-%H%M)
TMP=$(mktemp -d)
trap "rm -rf $TMP" EXIT

echo "[backup] $(date -Iseconds) Starting backup → $BACKUP_DIR/$STAMP.tar.gz"

# 1. Postgres dump
PG=$(docker ps -q -f name=postgres)
[ -z "$PG" ] && { echo "Postgres container introuvable"; exit 1; }
: "${DB_USER:=noliae}"
: "${DB_NAME:=noliaemail}"
docker exec "$PG" pg_dump -U "$DB_USER" "$DB_NAME" > "$TMP/postgres.sql"

# 2. Maildirs (volume vmail)
DOVECOT=$(docker ps -q -f name=dovecot)
if [ -n "$DOVECOT" ]; then
  docker exec "$DOVECOT" tar czf - /var/vmail 2>/dev/null > "$TMP/vmail.tar.gz" || true
fi

# 3. MinIO attachments bucket
MINIO=$(docker ps -q -f name=minio)
if [ -n "$MINIO" ]; then
  docker exec "$MINIO" mc alias set local http://127.0.0.1:9000 \
    "${MINIO_ROOT_USER:-noliae}" "${MINIO_ROOT_PASSWORD}" >/dev/null 2>&1 || true
  docker exec "$MINIO" sh -c "tar czf - /data/attachments 2>/dev/null" > "$TMP/attachments.tar.gz" || true
fi

# 4. .env (info utile pour la restauration, sans les secrets en clair)
[ -f /root/noliae-mail-oss/.env ] && grep -v PASS /root/noliae-mail-oss/.env > "$TMP/env.public" || true

# 5. Manifest
cat > "$TMP/MANIFEST.txt" <<EOF
Noliae Mail OSS backup
Generated:  $(date -Iseconds)
Hostname:   $(hostname)
Postgres:   postgres.sql (pg_dump)
Mails:      vmail.tar.gz (Maildirs Dovecot)
Attachments: attachments.tar.gz (MinIO data)
Env:        env.public (sans secrets)
EOF

# 6. Tar le tout
tar -C "$TMP" -czf "$BACKUP_DIR/$STAMP.tar.gz" .
chmod 600 "$BACKUP_DIR/$STAMP.tar.gz"

# 7. Rétention : supprime les > 30 jours
find "$BACKUP_DIR" -name "*.tar.gz" -mtime "+$RETENTION_DAYS" -delete

SIZE=$(du -h "$BACKUP_DIR/$STAMP.tar.gz" | cut -f1)
echo "[backup] OK — $SIZE → $BACKUP_DIR/$STAMP.tar.gz"
