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
# ─── DKIM ───────────────────────────────────────────────────────────────
# Génère une clé DKIM par domaine au premier démarrage (persistée dans
# /etc/opendkim/keys via le volume postfix-dkim) et démarre opendkim sur
# inet:8891. Tu publies ensuite le record TXT côté DNS (UI admin l'affiche
# dans /admin/domains/{id}/dns).
mkdir -p /etc/opendkim/keys/${MAIL_DOMAIN}
if [ ! -f /etc/opendkim/keys/${MAIL_DOMAIN}/mail.private ]; then
  echo "[entrypoint] Generating DKIM key for ${MAIL_DOMAIN}..."
  opendkim-genkey -b 2048 -d "${MAIL_DOMAIN}" -D /etc/opendkim/keys/${MAIL_DOMAIN} -s mail -v
  chown -R opendkim:opendkim /etc/opendkim/keys
  chmod 600 /etc/opendkim/keys/${MAIL_DOMAIN}/mail.private
fi
# La clé publique (mail.txt) est exposée en lecture pour le container web
# (mount ro) qui l'affiche dans /admin/domains/{id}/dns.
find /etc/opendkim/keys -name "mail.txt" -exec chmod 644 {} \; 2>/dev/null || true
cat > /etc/opendkim.conf <<EOF
Syslog                  yes
UMask                   002
Socket                  inet:8891@0.0.0.0
PidFile                 /run/opendkim/opendkim.pid
Mode                    sv
Canonicalization        relaxed/relaxed
KeyTable                refile:/etc/opendkim/key.table
SigningTable            refile:/etc/opendkim/signing.table
ExternalIgnoreList      refile:/etc/opendkim/trusted.hosts
InternalHosts           refile:/etc/opendkim/trusted.hosts
SignatureAlgorithm      rsa-sha256
UserID                  opendkim:opendkim
EOF
echo "mail._domainkey.${MAIL_DOMAIN} ${MAIL_DOMAIN}:mail:/etc/opendkim/keys/${MAIL_DOMAIN}/mail.private" > /etc/opendkim/key.table
echo "*@${MAIL_DOMAIN} mail._domainkey.${MAIL_DOMAIN}" > /etc/opendkim/signing.table
cat > /etc/opendkim/trusted.hosts <<EOF
127.0.0.1
::1
localhost
${MAIL_DOMAIN}
.${MAIL_DOMAIN}
EOF
mkdir -p /run/opendkim
chown opendkim:opendkim /run/opendkim
opendkim || echo "[entrypoint] opendkim start failed"

CERT=/etc/letsencrypt/live/${MAIL_DOMAIN}/fullchain.pem
KEY=/etc/letsencrypt/live/${MAIL_DOMAIN}/privkey.pem
if [ ! -f "$CERT" ]; then
  echo "No LE cert found, generating self-signed (replace for production)..."
  mkdir -p /etc/postfix/tls
  openssl req -new -x509 -nodes -days 365 -subj "/CN=${MAIL_DOMAIN}" \
    -keyout /etc/postfix/tls/key.pem -out /etc/postfix/tls/cert.pem 2>/dev/null
  chmod 600 /etc/postfix/tls/key.pem
  # Regex permissive : Postfix accepte ' = ' OU '=' ; on remplace les 2 formes
  sed -i -E "s|^[[:space:]]*smtpd_tls_cert_file[[:space:]]*=.*|smtpd_tls_cert_file = /etc/postfix/tls/cert.pem|" /etc/postfix/main.cf
  sed -i -E "s|^[[:space:]]*smtpd_tls_key_file[[:space:]]*=.*|smtpd_tls_key_file = /etc/postfix/tls/key.pem|"   /etc/postfix/main.cf
fi
# Toujours vérifier qu'on a un cert lisible côté serveur (sinon STARTTLS échoue)
postconf -e "smtpd_tls_loglevel=1"

# Répare les perms de la spool Postfix au cas où un ancien container l'aurait
# laissée dans un état incohérent (changement d'UID entre rebuilds).
postfix set-permissions 2>/dev/null || true

# Copie les fichiers de résolution DNS dans le chroot Postfix (/var/spool/postfix/etc)
# sinon les daemons en chroot ne peuvent résoudre 'rspamd', 'postgres' etc.
mkdir -p /var/spool/postfix/etc
for f in /etc/resolv.conf /etc/services /etc/hosts /etc/host.conf /etc/nsswitch.conf /etc/localtime; do
  [ -f "$f" ] && cp -f "$f" /var/spool/postfix/etc/ 2>/dev/null || true
done

# rsyslog (Postfix log via syslog). On enlève le pidfile orphelin sinon
# rsyslogd refuse de démarrer (« pidfile already exist »).
rm -f /run/rsyslogd.pid
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
