#!/bin/sh
set -e
: "${DB_HOST:?}"
: "${DB_NAME:?}"
: "${DB_USER:?}"
: "${DB_PASSWORD:?}"
: "${MAIL_MASTER_USER:=masteruser}"
: "${MAIL_MASTER_PASS:=changeme}"

# Render SQL confs (substitue ${DB_*})
for f in /etc/dovecot/dovecot-sql.conf.ext /etc/dovecot/dovecot-sql-tokens.conf.ext; do
  [ -f "$f" ] || continue
  envsubst < "$f" > "$f.tmp" && mv "$f.tmp" "$f"
  chmod 600 "$f"
done

HASH=$(doveadm pw -s BLF-CRYPT -p "$MAIL_MASTER_PASS" 2>/dev/null || \
       echo "{BLF-CRYPT}$(openssl passwd -apr1 "$MAIL_MASTER_PASS")")
echo "${MAIL_MASTER_USER}:${HASH}::::" > /etc/dovecot/master-users
chmod 644 /etc/dovecot/master-users

for i in $(seq 1 30); do
  PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "\q" 2>/dev/null && break
  sleep 1
done

mkdir -p /var/vmail
chown vmail:vmail /var/vmail

exec "$@"
