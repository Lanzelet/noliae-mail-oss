#!/bin/sh
set -e

: "${MAIL_DOMAIN:?MAIL_DOMAIN required}"
: "${DB_HOST:?DB_HOST required}"
: "${DB_NAME:=noliaemail}"
: "${DB_USER:=noliae}"
: "${DB_PASSWORD:?DB_PASSWORD required}"

# Rend les .cf depuis les templates (substitue ${MAIL_DOMAIN} etc.)
envsubst < /etc/postfix/main.cf.tmpl   > /etc/postfix/main.cf
envsubst < /etc/postfix/master.cf.tmpl > /etc/postfix/master.cf

# Rend les requêtes pgsql/*.cf
for f in /etc/postfix/pgsql/*.cf; do
  if grep -q '\${' "$f"; then
    tmp=$(mktemp); envsubst < "$f" > "$tmp"; mv "$tmp" "$f"
  fi
done

# Attente Postgres
echo "Waiting for Postgres @${DB_HOST}..."
for i in $(seq 1 30); do
  nc -z "$DB_HOST" 5432 && break || sleep 1
done

# Certificat TLS : si Let's Encrypt déjà obtenu monter /etc/letsencrypt
# sinon, génère un cert auto-signé temporaire (Postfix refusera STARTTLS sans cert)
CERT=/etc/letsencrypt/live/${MAIL_DOMAIN}/fullchain.pem
KEY=/etc/letsencrypt/live/${MAIL_DOMAIN}/privkey.pem
if [ ! -f "$CERT" ]; then
  echo "No LE cert found, generating self-signed (replace for production)..."
  mkdir -p /etc/postfix/tls
  openssl req -new -x509 -nodes -days 30 -subj "/CN=${MAIL_DOMAIN}" \
    -keyout /etc/postfix/tls/key.pem -out /etc/postfix/tls/cert.pem 2>/dev/null
  sed -i "s|smtpd_tls_cert_file=.*|smtpd_tls_cert_file=/etc/postfix/tls/cert.pem|" /etc/postfix/main.cf
  sed -i "s|smtpd_tls_key_file=.*|smtpd_tls_key_file=/etc/postfix/tls/key.pem|" /etc/postfix/main.cf
fi

# rsyslog (Postfix log via syslog)
rsyslogd

# Crée maps Postfix
newaliases 2>/dev/null || true

exec "$@"
