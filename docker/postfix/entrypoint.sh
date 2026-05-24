#!/bin/sh
set -e

: "${MAIL_DOMAIN:?MAIL_DOMAIN required}"
: "${DB_HOST:?DB_HOST required}"
: "${DB_NAME:=noliaemail}"
: "${DB_USER:=noliae}"
: "${DB_PASSWORD:?DB_PASSWORD required}"
: "${MAIL_TRUSTED_NETS:=127.0.0.0/8 [::1]/128 172.16.0.0/12}"

# Rend les .cf depuis les templates : LIMITER envsubst aux vars qu'on veut
# substituer, sinon il vide aussi $myhostname, $data_directory etc.
VARS='$MAIL_DOMAIN $MAIL_TRUSTED_NETS $DB_HOST $DB_NAME $DB_USER $DB_PASSWORD'
envsubst "$VARS" < /etc/postfix/main.cf.tmpl   > /etc/postfix/main.cf
envsubst "$VARS" < /etc/postfix/master.cf.tmpl > /etc/postfix/master.cf

# Rend les requêtes pgsql/*.cf — uniquement les vars DB
DB_VARS='$DB_HOST $DB_NAME $DB_USER $DB_PASSWORD'
for f in /etc/postfix/pgsql/*.cf; do
  if grep -q '\${' "$f"; then
    tmp=$(mktemp); envsubst "$DB_VARS" < "$f" > "$tmp"; mv "$tmp" "$f"
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

# Démarre les 3 daemons Python policy/admin en background.
# Bind 0.0.0.0 pour que le web container y accède via le réseau intnet.
mkdir -p /var/log
: "${POSTFIX_QUEUE_TOKEN:=}"
: "${LIST_POLICY_PORT:=10031}"
: "${RATE_LIMIT_PORT:=10033}"

export BIND_ADDR=0.0.0.0 PORT=10032 QUEUE_TOKEN="$POSTFIX_QUEUE_TOKEN"
python3 /usr/local/bin/queue-daemon.py >>/var/log/queue-daemon.log 2>&1 &
echo "[entrypoint] queue-daemon → :10032"

export LIST_POLICY_PORT
python3 /usr/local/bin/list-policy.py >>/var/log/list-policy.log 2>&1 &
echo "[entrypoint] list-policy → :$LIST_POLICY_PORT"

export RATE_LIMIT_PORT
python3 /usr/local/bin/rate-limit.py >>/var/log/rate-limit.log 2>&1 &
echo "[entrypoint] rate-limit → :$RATE_LIMIT_PORT"

exec "$@"
