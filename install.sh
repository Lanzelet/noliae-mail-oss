#!/bin/bash
# Noliae Mail OSS — installation interactive.
#
# Génère .env (tous les secrets), lance la stack, affiche les records DNS
# à publier + récupère la clé DKIM réelle (générée par Postfix au boot).
set -e

cd "$(dirname "$0")"

c_red()    { printf "\033[31m%s\033[0m" "$*"; }
c_green()  { printf "\033[32m%s\033[0m" "$*"; }
c_yellow() { printf "\033[33m%s\033[0m" "$*"; }
c_bold()   { printf "\033[1m%s\033[0m"  "$*"; }
c_cyan()   { printf "\033[36m%s\033[0m" "$*"; }

echo ""
echo "  ╔══════════════════════════════════════════════════╗"
echo "  ║          NOLIAE MAIL OSS — INSTALLATION          ║"
echo "  ╚══════════════════════════════════════════════════╝"
echo ""

# ── 1. Vérifs prérequis ─────────────────────────────────────────────
for cmd in docker openssl curl; do
  if ! command -v $cmd >/dev/null 2>&1; then
    c_red "✗ $cmd manquant. Installe-le d'abord.\n"; exit 1
  fi
done
docker compose version >/dev/null 2>&1 || { c_red "✗ docker compose v2 requis.\n"; exit 1; }

# ── 2. .env ─────────────────────────────────────────────────────────
if [ -f .env ]; then
  read -p "$(c_yellow ".env existe déjà. Écraser ? (oui/NON) : ")" overwrite
  [ "$overwrite" = "oui" ] || { echo "Abandon."; exit 0; }
fi

read -p "$(c_bold "Domaine mail (ex: mail.example.com) : ")" MAIL_DOMAIN
[ -n "$MAIL_DOMAIN" ] || { c_red "Domaine requis\n"; exit 1; }

read -p "$(c_bold "Email admin (ex: admin@${MAIL_DOMAIN}) : ")" ADMIN_EMAIL
[ -n "$ADMIN_EMAIL" ] || { c_red "Email requis\n"; exit 1; }

read -s -p "$(c_bold "Mot de passe admin (laisse vide = généré aléatoire) : ")" ADMIN_PASSWORD
echo ""
ADMIN_PASSWORD_GENERATED=0
if [ -z "$ADMIN_PASSWORD" ]; then
  ADMIN_PASSWORD=$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)
  ADMIN_PASSWORD_GENERATED=1
fi

read -p "$(c_bold "Autoriser inscriptions publiques ? (oui/NON) : ")" ALLOW_REG
[ "$ALLOW_REG" = "oui" ] && ALLOW_REGISTRATION=true || ALLOW_REGISTRATION=false

# ── 3. Génère TOUS les secrets ──────────────────────────────────────
gen_secret() { openssl rand -base64 32 | tr -d '/+=' | head -c 40; }
DB_PASSWORD=$(gen_secret)
MAIL_MASTER_PASS=$(gen_secret)
MINIO_ROOT_PASSWORD=$(gen_secret)
POSTFIX_QUEUE_TOKEN=$(openssl rand -hex 32)
RSPAMD_PASSWORD=$(gen_secret)
APP_KEY="base64:$(openssl rand -base64 32)"

cp .env.example .env

# sed compatible BSD (macOS) + GNU (Linux) via backup suffix puis suppression
SED_INPLACE="-i.bak"

sed $SED_INPLACE \
  -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
  -e "s|^MAIL_DOMAIN=.*|MAIL_DOMAIN=${MAIL_DOMAIN}|" \
  -e "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=${ADMIN_EMAIL}|" \
  -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=${ADMIN_PASSWORD}|" \
  -e "s|^ALLOW_REGISTRATION=.*|ALLOW_REGISTRATION=${ALLOW_REGISTRATION}|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
  -e "s|^MAIL_MASTER_PASS=.*|MAIL_MASTER_PASS=${MAIL_MASTER_PASS}|" \
  -e "s|^MINIO_ROOT_PASSWORD=.*|MINIO_ROOT_PASSWORD=${MINIO_ROOT_PASSWORD}|" \
  -e "s|^POSTFIX_QUEUE_TOKEN=.*|POSTFIX_QUEUE_TOKEN=${POSTFIX_QUEUE_TOKEN}|" \
  -e "s|^RSPAMD_PASSWORD=.*|RSPAMD_PASSWORD=${RSPAMD_PASSWORD}|" \
  -e "s|^AWS_ENDPOINT=.*|AWS_ENDPOINT=https://s3.${MAIL_DOMAIN}|" \
  .env
rm -f .env.bak

# Garantit que MAIL_TRUSTED_NETS reste quoté (sinon Dotenv plante sur les espaces)
grep -q '^MAIL_TRUSTED_NETS="' .env || \
  sed $SED_INPLACE 's|^MAIL_TRUSTED_NETS=\(.*\)|MAIL_TRUSTED_NETS="\1"|' .env
rm -f .env.bak

c_green "✓ .env généré avec secrets aléatoires forts\n"

# ── 4. IP publique + records DNS ────────────────────────────────────
SERVER_IP=$(curl -s --max-time 5 https://api.ipify.org || echo "<ton-IP-publique>")

cat > DNS-RECORDS.txt <<EOF
# ═══════════════════════════════════════════════════════════════════
# Noliae Mail OSS — Records DNS à publier
# Domaine : ${MAIL_DOMAIN}
# Serveur : ${SERVER_IP}
# ═══════════════════════════════════════════════════════════════════
#
# ⚠ Le record DKIM ci-dessous (mail._domainkey) est généré par Postfix
#   au PREMIER démarrage du container. Lance la stack puis exécute :
#
#      docker compose exec postfix cat /etc/opendkim/keys/${MAIL_DOMAIN}/mail.txt
#
#   Copie le contenu de la parenthèse en valeur TXT (sans guillemets).
#   Ou va dans l'admin panel : https://${MAIL_DOMAIN}/admin/domains
#

# ─── 1. Pointage du domaine vers ton serveur ──────────────────────
${MAIL_DOMAIN}.                  IN A      ${SERVER_IP}

# ─── 2. Endpoint S3 MinIO (pièces jointes) ────────────────────────
# OBLIGATOIRE : le navigateur des destinataires télécharge les PJ ici.
s3.${MAIL_DOMAIN}.               IN A      ${SERVER_IP}

# ─── 3. MX (route le courrier entrant) ────────────────────────────
${MAIL_DOMAIN}.                  IN MX 10  ${MAIL_DOMAIN}.

# ─── 4. SPF (autorise ce serveur à envoyer) ───────────────────────
${MAIL_DOMAIN}.                  IN TXT    "v=spf1 mx -all"

# ─── 5. DKIM (à compléter après premier docker compose up -d) ─────
# mail._domainkey.${MAIL_DOMAIN}.  IN TXT  "v=DKIM1; k=rsa; p=<VOIR docker compose exec postfix cat /etc/opendkim/keys/${MAIL_DOMAIN}/mail.txt>"

# ─── 6. DMARC (politique anti-spoofing) ───────────────────────────
_dmarc.${MAIL_DOMAIN}.           IN TXT    "v=DMARC1; p=quarantine; rua=mailto:postmaster@${MAIL_DOMAIN}; ruf=mailto:postmaster@${MAIL_DOMAIN}; fo=1"

# ─── 7. MTA-STS (force TLS en transit) ────────────────────────────
_mta-sts.${MAIL_DOMAIN}.         IN TXT    "v=STSv1; id=$(date +%Y%m%d%H)"
# Note : pour activer MTA-STS, il faut aussi servir une policy sur
# https://mta-sts.${MAIL_DOMAIN}/.well-known/mta-sts.txt
# Pas inclus dans la stack OSS par défaut — à mettre en place côté Traefik
# si tu veux MTA-STS strict.

# ─── 8. TLS-RPT (rapports d'erreur TLS) ───────────────────────────
_smtp._tls.${MAIL_DOMAIN}.       IN TXT    "v=TLSRPTv1; rua=mailto:tls-reports@${MAIL_DOMAIN}"

# ─── 9. Alias indispensables ──────────────────────────────────────
# Crée dans /admin/aliases après login :
#   postmaster@${MAIL_DOMAIN}     → ${ADMIN_EMAIL}
#   abuse@${MAIL_DOMAIN}          → ${ADMIN_EMAIL}
#   tls-reports@${MAIL_DOMAIN}    → ${ADMIN_EMAIL}
EOF

c_green "✓ Records DNS écrits dans DNS-RECORDS.txt\n"

# ── 5. Récap + next steps ───────────────────────────────────────────
echo ""
echo "  ╔══════════════════════════════════════════════════╗"
echo "  ║                  ✓ TERMINÉ                       ║"
echo "  ╚══════════════════════════════════════════════════╝"
echo ""
c_bold "Identifiants admin\n"
echo "  Email     : $(c_cyan "${ADMIN_EMAIL}")"
if [ "$ADMIN_PASSWORD_GENERATED" = "1" ]; then
  echo "  Password  : $(c_yellow "${ADMIN_PASSWORD}")  ← copie-le maintenant, plus jamais affiché"
else
  echo "  Password  : $(c_yellow "<celui que tu as saisi>")"
fi
echo ""
c_bold "Prochaines étapes\n"
echo "  $(c_cyan 1.) Ouvre les ports : $(c_yellow "25 · 80 · 143 · 443 · 465 · 587 · 993")"
echo "  $(c_cyan 2.) Configure le rDNS de $(c_yellow "${SERVER_IP}") → $(c_yellow "${MAIL_DOMAIN}")"
echo "      (chez ton hébergeur, sinon les gros mailers refuseront tes mails)"
echo "  $(c_cyan 3.) Build les images Docker (~5 min one-shot) :"
echo "      $(c_yellow "docker compose build")"
echo "  $(c_cyan 4.) Démarre la stack :"
echo "      $(c_yellow "docker compose up -d")"
echo "  $(c_cyan 5.) Récupère la clé DKIM réelle (générée par Postfix au boot) :"
echo "      $(c_yellow "docker compose exec postfix cat /etc/opendkim/keys/${MAIL_DOMAIN}/mail.txt")"
echo "      Ajoute la valeur TXT à ton DNS (cf $(c_yellow "DNS-RECORDS.txt") section 5)."
echo "  $(c_cyan 6.) Publie les autres records DNS :"
echo "      $(c_yellow "cat DNS-RECORDS.txt")"
echo "  $(c_cyan 7.) Connecte-toi :"
echo "      $(c_yellow "https://${MAIL_DOMAIN}/login")"
echo ""
c_yellow "⚠ Sauvegarde le .env (contient tous les secrets). Sans, pas de restore.\n"
echo ""
