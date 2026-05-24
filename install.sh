#!/bin/bash
# Noliae Mail OSS — installation interactive.
# Génère .env, DKIM, lance la stack, affiche les enregistrements DNS à publier.
set -e

cd "$(dirname "$0")"

c_red()    { printf "\033[31m%s\033[0m" "$*"; }
c_green()  { printf "\033[32m%s\033[0m" "$*"; }
c_yellow() { printf "\033[33m%s\033[0m" "$*"; }
c_bold()   { printf "\033[1m%s\033[0m"  "$*"; }

echo ""
echo "  ╔══════════════════════════════════════════════════╗"
echo "  ║          NOLIAE MAIL OSS — INSTALLATION          ║"
echo "  ╚══════════════════════════════════════════════════╝"
echo ""

# 1. Vérifs prérequis
for cmd in docker openssl curl; do
  if ! command -v $cmd >/dev/null 2>&1; then
    c_red "✗ $cmd manquant. Installe-le d'abord.\n"; exit 1
  fi
done
docker compose version >/dev/null 2>&1 || { c_red "✗ docker compose v2 requis.\n"; exit 1; }

# 2. .env
if [ -f .env ]; then
  read -p "$(c_yellow ".env existe déjà. Écraser ? (oui/NON) : ")" overwrite
  [ "$overwrite" = "oui" ] || { echo "Abandon."; exit 0; }
fi

read -p "$(c_bold "Domaine mail (ex: mail.example.com) : ")" MAIL_DOMAIN
[ -n "$MAIL_DOMAIN" ] || { c_red "Domaine requis\n"; exit 1; }
read -p "$(c_bold "Email admin (ex: admin@example.com) : ")" ADMIN_EMAIL
[ -n "$ADMIN_EMAIL" ] || { c_red "Email requis\n"; exit 1; }
read -p "$(c_bold "Autoriser inscriptions publiques ? (oui/non, défaut non) : ")" ALLOW_REG
[ "$ALLOW_REG" = "oui" ] && ALLOW_REGISTRATION=true || ALLOW_REGISTRATION=false

# 3. Génère secrets
gen_secret() { openssl rand -base64 32 | tr -d '/+=' | head -c 40; }
DB_PASSWORD=$(gen_secret)
MAIL_MASTER_PASS=$(gen_secret)
MINIO_ROOT_PASSWORD=$(gen_secret)
APP_KEY="base64:$(openssl rand -base64 32)"

cp .env.example .env
sed -i.bak \
  -e "s|^MAIL_DOMAIN=.*|MAIL_DOMAIN=$MAIL_DOMAIN|" \
  -e "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=$ADMIN_EMAIL|" \
  -e "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" \
  -e "s|^MAIL_MASTER_PASS=.*|MAIL_MASTER_PASS=$MAIL_MASTER_PASS|" \
  -e "s|^MINIO_ROOT_PASSWORD=.*|MINIO_ROOT_PASSWORD=$MINIO_ROOT_PASSWORD|" \
  -e "s|^ALLOW_REGISTRATION=.*|ALLOW_REGISTRATION=$ALLOW_REGISTRATION|" \
  .env
rm .env.bak

# 4. DKIM key
mkdir -p volumes/dkim
if [ ! -f volumes/dkim/${MAIL_DOMAIN}.key ]; then
  echo "Génération de la clé DKIM (sélecteur 'mail')..."
  openssl genrsa -out volumes/dkim/${MAIL_DOMAIN}.key 2048 2>/dev/null
  openssl rsa -in volumes/dkim/${MAIL_DOMAIN}.key -pubout 2>/dev/null \
    | grep -v -- "---" | tr -d '\n' > volumes/dkim/${MAIL_DOMAIN}.pub
  chmod 600 volumes/dkim/${MAIL_DOMAIN}.key
fi
DKIM_PUB=$(cat volumes/dkim/${MAIL_DOMAIN}.pub)
SERVER_IP=$(curl -s https://api.ipify.org || echo "<ton-IP-publique>")

# 5. DNS à publier
cat > DNS-RECORDS.txt <<EOF
# Noliae Mail OSS — enregistrements DNS à publier chez ton registrar
# Domaine : ${MAIL_DOMAIN}

# A (pointe vers ton serveur)
${MAIL_DOMAIN}.            IN A     ${SERVER_IP}

# A pour le endpoint S3 (MinIO) — sert les pièces jointes signées
# Sans ça, le navigateur ne peut pas télécharger les attachments des mails.
s3.${MAIL_DOMAIN}.         IN A     ${SERVER_IP}

# MX (route le courrier entrant)
${MAIL_DOMAIN}.            IN MX 10 ${MAIL_DOMAIN}.

# SPF (autorise ce serveur à envoyer)
${MAIL_DOMAIN}.            IN TXT   "v=spf1 mx -all"

# DKIM (signature des mails sortants — sélecteur 'mail')
mail._domainkey.${MAIL_DOMAIN}. IN TXT "v=DKIM1; k=rsa; p=${DKIM_PUB}"

# DMARC (politique anti-spoofing)
_dmarc.${MAIL_DOMAIN}.     IN TXT   "v=DMARC1; p=quarantine; rua=mailto:postmaster@${MAIL_DOMAIN}"

# MTA-STS (encryption obligatoire en transit)
_mta-sts.${MAIL_DOMAIN}.   IN TXT   "v=STSv1; id=$(date +%Y%m%d%H)"
mta-sts.${MAIL_DOMAIN}.    IN A     ${SERVER_IP}

# TLS-RPT (rapports d'erreur TLS)
_smtp._tls.${MAIL_DOMAIN}. IN TXT   "v=TLSRPTv1; rua=mailto:tls-reports@${MAIL_DOMAIN}"
EOF

c_green "✓ .env généré\n"
c_green "✓ Clé DKIM générée → volumes/dkim/${MAIL_DOMAIN}.key\n"
c_green "✓ Records DNS écrits dans DNS-RECORDS.txt\n"
echo ""
c_bold "Prochaine étape :\n"
echo "  1. Publie les records DNS (cat DNS-RECORDS.txt)"
echo "  2. Ouvre ces ports sur ton serveur : 25, 80, 143, 443, 465, 587, 993"
echo "  3. Lance la stack : docker compose up -d"
echo "  4. Crée ton premier compte sur https://${MAIL_DOMAIN}/register"
echo ""
c_yellow "⚠ Vérifie que le rDNS de ${SERVER_IP} pointe vers ${MAIL_DOMAIN} chez ton hébergeur (sinon les gros mailers refuseront).\n"
