#!/bin/sh
set -e
: "${MAIL_DOMAIN:?}"
: "${DB_HOST:?}"
: "${DB_PASSWORD:?}"
for f in /etc/dovecot/*.conf* /etc/dovecot/conf.d/*.conf; do
  [ -f "$f" ] && grep -q '\${' "$f" && (tmp=$(mktemp); envsubst < "$f" > "$tmp"; mv "$tmp" "$f")
done
# Cert
CERT=/etc/letsencrypt/live/${MAIL_DOMAIN}/fullchain.pem
if [ ! -f "$CERT" ]; then
  mkdir -p /etc/dovecot/tls
  openssl req -new -x509 -nodes -days 30 -subj "/CN=${MAIL_DOMAIN}" \
    -keyout /etc/dovecot/tls/key.pem -out /etc/dovecot/tls/cert.pem 2>/dev/null
fi
exec "$@"
