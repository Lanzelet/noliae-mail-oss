#!/bin/bash
# Noliae Mail OSS — installation interactive 100% automatique.
#
# 1. Génère .env (tous les secrets aléatoires forts)
# 2. Build les 4 images Docker (web / postfix / dovecot / rspamd)
# 3. Démarre la stack
# 4. Attend que Postfix génère la clé DKIM
# 5. Récupère la vraie clé DKIM et l'injecte dans DNS-RECORDS.txt
# 6. Affiche les identifiants admin + tous les records DNS à publier
#
# Usage : ./install.sh
set -e

cd "$(dirname "$0")"

c_red()    { printf "\033[31m%s\033[0m" "$*"; }
c_green()  { printf "\033[32m%s\033[0m" "$*"; }
c_yellow() { printf "\033[33m%s\033[0m" "$*"; }
c_bold()   { printf "\033[1m%s\033[0m"  "$*"; }
c_cyan()   { printf "\033[36m%s\033[0m" "$*"; }
step()     { echo ""; c_bold "▶ $*"; echo ""; }

echo ""
echo "  ╔══════════════════════════════════════════════════╗"
echo "  ║          NOLIAE MAIL OSS — INSTALLATION          ║"
echo "  ╚══════════════════════════════════════════════════╝"
echo ""

# ── 1. Vérifs prérequis ─────────────────────────────────────────────
step "1/9 · Vérification des prérequis"
for cmd in docker openssl curl; do
  if ! command -v $cmd >/dev/null 2>&1; then
    c_red "✗ $cmd manquant. Installe-le d'abord.\n"; exit 1
  fi
  c_green "  ✓ $cmd\n"
done
docker compose version >/dev/null 2>&1 || { c_red "✗ docker compose v2 requis.\n"; exit 1; }
c_green "  ✓ docker compose v2\n"

# ── Compat API Docker (>= 28 rejette les vieux clients par défaut) ──
# Le SDK Docker embarqué dans Traefik négocie en API 1.24 au premier /info,
# que les démons Docker récents refusent. On force DOCKER_MIN_API_VERSION=1.24
# côté démon via un drop-in systemd. Idempotent.
DOCKER_MAJOR=$(docker version --format '{{.Server.Version}}' 2>/dev/null | cut -d. -f1)
if [ -n "$DOCKER_MAJOR" ] && [ "$DOCKER_MAJOR" -ge 28 ] 2>/dev/null; then
  DROPIN=/etc/systemd/system/docker.service.d/api-version.conf
  if [ ! -f "$DROPIN" ] || ! grep -q 'DOCKER_MIN_API_VERSION=1.24' "$DROPIN" 2>/dev/null; then
    c_yellow "  ⚠ Docker ${DOCKER_MAJOR}.x détecté : configuration DOCKER_MIN_API_VERSION=1.24\n"
    sudo mkdir -p /etc/systemd/system/docker.service.d
    echo -e '[Service]\nEnvironment="DOCKER_MIN_API_VERSION=1.24"' | sudo tee "$DROPIN" >/dev/null
    sudo systemctl daemon-reload
    sudo systemctl restart docker
    c_green "  ✓ Démon Docker reconfiguré (compat API 1.24)\n"
  else
    c_green "  ✓ DOCKER_MIN_API_VERSION=1.24 déjà configuré\n"
  fi
fi

# Vérifie qu'on est dans le repo
[ -f docker-compose.yml ] && [ -f .env.example ] || {
  c_red "✗ Lance ce script depuis la racine du repo noliae-mail-oss.\n"; exit 1; }

# ── 2. Prompts utilisateur ──────────────────────────────────────────
step "2/9 · Configuration"
RESET_DB=0
if [ -f .env ]; then
  read -p "$(c_yellow ".env existe déjà. Écraser ? (oui/NON) : ")" overwrite
  [ "$overwrite" = "oui" ] || { echo "Abandon."; exit 0; }
  # On va regénérer DB_PASSWORD : le volume Postgres existant garde l'ancien
  # password (PG ne lit POSTGRES_PASSWORD qu'à l'init du data dir vide). On
  # doit wipe le volume pour éviter "password authentication failed".
  c_yellow "  ⚠ Le volume Postgres sera wipé (nouveau DB_PASSWORD)\n"
  read -p "$(c_yellow "Confirmer wipe du volume postgres_data ? (oui/NON) : ")" confirm_wipe
  if [ "$confirm_wipe" = "oui" ]; then
    RESET_DB=1
  else
    c_red "✗ Impossible de continuer sans wipe (password mismatch garanti). Abandon.\n"; exit 1
  fi
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
step "3/9 · Génération des secrets aléatoires"
gen_secret() { openssl rand -base64 32 | tr -d '/+=' | head -c 40; }
DB_PASSWORD=$(gen_secret)
MAIL_MASTER_PASS=$(gen_secret)
MINIO_ROOT_PASSWORD=$(gen_secret)
POSTFIX_QUEUE_TOKEN=$(openssl rand -hex 32)
RSPAMD_PASSWORD=$(gen_secret)
APP_KEY="base64:$(openssl rand -base64 32)"
c_green "  ✓ APP_KEY + 5 secrets générés\n"

cp .env.example .env
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

# MAIL_TRUSTED_NETS doit être quoté (sinon Dotenv plante sur les espaces)
grep -q '^MAIL_TRUSTED_NETS="' .env || \
  sed $SED_INPLACE 's|^MAIL_TRUSTED_NETS=\(.*\)|MAIL_TRUSTED_NETS="\1"|' .env
rm -f .env.bak

c_green "  ✓ .env écrit\n"

# ── 4. Détection LE possible + fallback cert auto-signé ────────────
step "4/9 · Détection accessibilité Let's Encrypt"

# Test : le domaine résout-il vers une IP publique routable, et le serveur
# est-il joignable sur port 80 depuis internet ? Si non → cert auto-signé.
LE_OK=0
RESOLVED_IP=$(getent hosts "${MAIL_DOMAIN}" 2>/dev/null | awk '{print $1}' | head -1 || echo "")
SERVER_IP=$(curl -s --max-time 5 https://api.ipify.org || echo "")

if [ -n "$RESOLVED_IP" ] && [ -n "$SERVER_IP" ] && [ "$RESOLVED_IP" = "$SERVER_IP" ]; then
  c_green "  ✓ ${MAIL_DOMAIN} résout vers ${SERVER_IP} (IP publique du serveur)\n"
  c_green "  → Let's Encrypt sera tenté au premier démarrage Traefik\n"
  LE_OK=1
else
  c_yellow "  ⚠ ${MAIL_DOMAIN} résout vers '${RESOLVED_IP:-AUCUN}' (IP publique serveur: '${SERVER_IP:-INDÉTECTABLE}')\n"
  c_yellow "  → Let's Encrypt impossible (LAN / IP locale / DNS pas propagé)\n"
  c_yellow "  → Génération d'un cert auto-signé (TLS valide, juste pas trusted par les navigateurs)\n"
fi

# Toujours générer le cert auto-signé en fallback (Traefik l'utilisera si LE échoue)
mkdir -p docker/traefik
if [ ! -f docker/traefik/cert.pem ] || [ ! -f docker/traefik/key.pem ]; then
  openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
    -keyout docker/traefik/key.pem \
    -out docker/traefik/cert.pem \
    -subj "/CN=${MAIL_DOMAIN}/O=Noliae Mail Self-Signed" \
    -addext "subjectAltName=DNS:${MAIL_DOMAIN},DNS:*.${MAIL_DOMAIN},DNS:s3.${MAIL_DOMAIN}" 2>/dev/null
  chmod 644 docker/traefik/cert.pem
  chmod 600 docker/traefik/key.pem
  c_green "  ✓ Cert auto-signé créé (CN=${MAIL_DOMAIN}, validité 825j)\n"
fi

# Config dynamique Traefik : sert le cert auto-signé comme default
cat > docker/traefik/tls.yml <<EOF
# Auto-généré par install.sh. Édite à tes risques.
# Sert le cert auto-signé comme defaultCertificate Traefik : utilisé si
# Let's Encrypt n'a pas pu obtenir de cert valide pour ce domaine.
tls:
  certificates:
    - certFile: /etc/traefik/dynamic/cert.pem
      keyFile:  /etc/traefik/dynamic/key.pem
      stores: [default]
  stores:
    default:
      defaultCertificate:
        certFile: /etc/traefik/dynamic/cert.pem
        keyFile:  /etc/traefik/dynamic/key.pem
EOF
c_green "  ✓ Config TLS dynamique Traefik écrite (docker/traefik/tls.yml)\n"

# ── 5. Build des images Docker ──────────────────────────────────────
step "5/9 · Build des images Docker (~5 min, patience)"
docker compose build 2>&1 | grep -E '^(#[0-9]+ \[)|^ ✔|^ ✘|^FAILED|^ERROR' | tail -20 || true
docker compose build || { c_red "\n✗ Build échoué. Vérifie les logs ci-dessus.\n"; exit 1; }
c_green "  ✓ 4 images buildées (web · postfix · dovecot · rspamd)\n"

# ── 5. Démarrage de la stack ────────────────────────────────────────
step "6/9 · Démarrage de la stack"

# Si on régénère .env, wipe le volume Postgres pour qu'il re-init avec le nouveau password
if [ "$RESET_DB" = "1" ]; then
  c_yellow "  ⟳ Wipe volume postgres_data (re-init au nouveau password)\n"
  docker compose down 2>/dev/null || true
  # Volume PG nommé "pg-data" dans le compose, préfixé du nom du projet
  for vol in $(docker volume ls -q | grep -E 'pg-data$' || true); do
    c_yellow "    rm volume $vol\n"
    docker volume rm "$vol" >/dev/null 2>&1 || true
  done
fi

docker compose up -d
c_green "  ✓ Containers démarrés\n"

echo -n "  Attente Postgres healthy "
for i in $(seq 1 60); do
  if docker compose ps postgres --format json 2>/dev/null | grep -q '"Health":"healthy"'; then
    c_green "OK\n"; break
  fi
  echo -n "."; sleep 2
done

# Fix permissions storage + storage:link (sinon symlink: Permission denied)
echo -n "  Fix permissions + storage:link "
docker compose exec -T -u root web chown -R www-data:www-data /var/www/html/public /var/www/html/storage 2>/dev/null || true
docker compose exec -T -u root web php artisan storage:link --force 2>/dev/null || true
c_green "OK\n"

# ── 6. Attente génération clé DKIM par Postfix ──────────────────────
step "7/9 · Attente génération clé DKIM (opendkim-genkey au boot Postfix)"
DKIM_TXT_FILE="/etc/opendkim/keys/${MAIL_DOMAIN}/mail.txt"
echo -n "  "
for i in $(seq 1 30); do
  if docker compose exec -T postfix test -f "${DKIM_TXT_FILE}" 2>/dev/null; then
    c_green "OK\n"; break
  fi
  echo -n "."; sleep 2
done

DKIM_RAW=$(docker compose exec -T postfix cat "${DKIM_TXT_FILE}" 2>/dev/null || echo "")
# Extrait la valeur "p=…" en concaténant toutes les sous-chaînes entre guillemets
DKIM_VALUE=$(echo "$DKIM_RAW" | grep -oE '"[^"]+"' | tr -d '"' | tr -d '\n')

if [ -z "$DKIM_VALUE" ]; then
  c_yellow "  ⚠ Clé DKIM pas encore générée. Récupère-la plus tard avec :\n"
  c_yellow "    docker compose exec postfix cat ${DKIM_TXT_FILE}\n"
  DKIM_VALUE="<EXÉCUTE: docker compose exec postfix cat ${DKIM_TXT_FILE}>"
else
  c_green "  ✓ Clé DKIM 2048-bit récupérée\n"
fi

# ── 7. Génère DNS-RECORDS.txt avec la vraie clé DKIM ────────────────
step "8/9 · Écriture DNS-RECORDS.txt"
SERVER_IP=$(curl -s --max-time 5 https://api.ipify.org || echo "<ton-IP-publique>")

cat > DNS-RECORDS.txt <<EOF
# ═══════════════════════════════════════════════════════════════════
# Noliae Mail OSS — Records DNS à publier
# Domaine : ${MAIL_DOMAIN}
# Serveur : ${SERVER_IP}
# Généré  : $(date -Iseconds)
# ═══════════════════════════════════════════════════════════════════

# ─── 1. Pointage du domaine vers ton serveur ──────────────────────
${MAIL_DOMAIN}.                  IN A      ${SERVER_IP}

# ─── 2. Endpoint S3 MinIO (pièces jointes) ────────────────────────
# OBLIGATOIRE : le navigateur des destinataires télécharge les PJ ici.
s3.${MAIL_DOMAIN}.               IN A      ${SERVER_IP}

# ─── 3. MX (route le courrier entrant) ────────────────────────────
${MAIL_DOMAIN}.                  IN MX 10  ${MAIL_DOMAIN}.

# ─── 4. SPF (autorise ce serveur à envoyer) ───────────────────────
${MAIL_DOMAIN}.                  IN TXT    "v=spf1 mx -all"

# ─── 5. DKIM (clé 2048-bit générée par opendkim au premier boot) ──
mail._domainkey.${MAIL_DOMAIN}.  IN TXT    "${DKIM_VALUE}"

# ─── 6. DMARC (politique anti-spoofing) ───────────────────────────
_dmarc.${MAIL_DOMAIN}.           IN TXT    "v=DMARC1; p=quarantine; rua=mailto:postmaster@${MAIL_DOMAIN}; ruf=mailto:postmaster@${MAIL_DOMAIN}; fo=1"

# ─── 7. MTA-STS (force TLS en transit) ────────────────────────────
_mta-sts.${MAIL_DOMAIN}.         IN TXT    "v=STSv1; id=$(date +%Y%m%d%H)"

# ─── 8. TLS-RPT (rapports d'erreur TLS) ───────────────────────────
_smtp._tls.${MAIL_DOMAIN}.       IN TXT    "v=TLSRPTv1; rua=mailto:tls-reports@${MAIL_DOMAIN}"

# ─── 9. Alias indispensables ──────────────────────────────────────
# Crée dans /admin/aliases après login :
#   postmaster@${MAIL_DOMAIN}     → ${ADMIN_EMAIL}
#   abuse@${MAIL_DOMAIN}          → ${ADMIN_EMAIL}
#   tls-reports@${MAIL_DOMAIN}    → ${ADMIN_EMAIL}
EOF
c_green "  ✓ DNS-RECORDS.txt écrit\n"

# ── 8. Smoke test ───────────────────────────────────────────────────
step "9/9 · Smoke test"
sleep 3
WEB_STATUS=$(curl -sk -o /dev/null -w "%{http_code}" -H "Host: ${MAIL_DOMAIN}" http://localhost 2>/dev/null || echo "—")
echo "  HTTP local (web container)  : $(c_cyan "${WEB_STATUS}")"
SMTP_BANNER=$(echo "QUIT" | nc -w 2 localhost 25 2>/dev/null | head -1 || echo "—")
echo "  Banner SMTP local (port 25) : $(c_cyan "${SMTP_BANNER}")"

# ── Récap final ─────────────────────────────────────────────────────
echo ""
echo "  ╔══════════════════════════════════════════════════╗"
echo "  ║              ✓ INSTALLATION OK                   ║"
echo "  ╚══════════════════════════════════════════════════╝"
echo ""
c_bold "Identifiants admin\n"
echo "  Email     : $(c_cyan "${ADMIN_EMAIL}")"
if [ "$ADMIN_PASSWORD_GENERATED" = "1" ]; then
  echo "  Password  : $(c_yellow "${ADMIN_PASSWORD}")  ← $(c_red "copie-le maintenant, plus jamais affiché")"
else
  echo "  Password  : $(c_yellow "<celui que tu as saisi>")"
fi
echo "  Login URL : $(c_cyan "https://${MAIL_DOMAIN}/login")"
echo ""
c_bold "À faire MAINTENANT chez ton registrar DNS\n"
echo "  1. Publie les records dans $(c_yellow "DNS-RECORDS.txt")"
echo "     $(c_cyan "cat DNS-RECORDS.txt")"
echo "  2. Configure le rDNS de $(c_yellow "${SERVER_IP}") → $(c_yellow "${MAIL_DOMAIN}")"
echo "     (chez ton hébergeur, sinon les gros mailers refuseront tes mails)"
echo "  3. Ouvre les ports : $(c_yellow "25 · 80 · 143 · 443 · 465 · 587 · 993")"
echo ""
c_bold "Commandes utiles\n"
echo "  Voir logs       : $(c_cyan "docker compose logs -f web postfix")"
echo "  Status         : $(c_cyan "docker compose ps")"
echo "  Restart web    : $(c_cyan "docker compose restart web")"
echo "  Backup         : $(c_cyan "docker compose exec web /backups/backup.sh")"
echo ""
c_yellow "⚠ SAUVEGARDE le fichier .env (contient TOUS les secrets). Sans, pas de restore.\n"
echo ""
