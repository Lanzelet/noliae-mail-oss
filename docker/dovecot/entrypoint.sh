#!/bin/sh
set -e
: "${DB_HOST:?}"
: "${DB_NAME:?}"
: "${DB_USER:?}"
: "${DB_PASSWORD:?}"
: "${MAIL_MASTER_USER:=masteruser}"
: "${MAIL_MASTER_PASS:=changeme}"

# Render sql conf
envsubst < /etc/dovecot/dovecot-sql.conf.ext > /etc/dovecot/dovecot-sql.conf.ext.tmp
mv /etc/dovecot/dovecot-sql.conf.ext.tmp /etc/dovecot/dovecot-sql.conf.ext
chmod 600 /etc/dovecot/dovecot-sql.conf.ext

# Master users file (used by webmail to impersonate any user)
HASH=$(doveadm pw -s BLF-CRYPT -p "$MAIL_MASTER_PASS" 2>/dev/null || \
       echo "{BLF-CRYPT}$(openssl passwd -apr1 "$MAIL_MASTER_PASS")")
echo "${MAIL_MASTER_USER}:${HASH}::::" > /etc/dovecot/master-users
chmod 644 /etc/dovecot/master-users

# Wait Postgres
echo "Wait for ${DB_HOST}..."
for i in $(seq 1 30); do
  PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c '\q' 2>/dev/null && break
  sleep 1
done

# Maildir parent
mkdir -p /var/vmail
chown vmail:vmail /var/vmail

exec "$@"
