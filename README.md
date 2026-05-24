# Noliae Mail OSS

> Webmail souverain auto-hébergeable. Open source, sans IA imposée, sans tracking, sans dépendance cloud.

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![Docker](https://img.shields.io/badge/docker-ready-blue.svg)](docker-compose.yml)

**Noliae Mail OSS** est la version open-source du webmail [Noliae](https://noliae.com). L'objectif : remplacer Google Workspace / Microsoft 365 par une stack mail complète, contrôlable, qu'on peut installer chez soi en une heure. Tout le code utile (webmail, MTA, IMAP, anti-spam, admin, organisations, carnet, branding) est dans ce repo.

---

## ✨ Fonctionnalités

### 📧 Webmail utilisateur
- 📥 **IMAP + SMTP** via Postfix/Dovecot (Maildir, quotas par compte)
- 🔍 **Recherche full-text** dans sujet/corps/expéditeur avec opérateurs (`from:`, `subject:`, `is:unread`…)
- 🏷️ **Labels, étoiles**, lu/non-lu, mode conversation
- ⏰ **Snooze** + **Schedule send** + **Undo send** (30s) + **Envoyer maintenant** pour skip
- 🌴 **Vacation responder**
- ✉️ **Brouillons IMAP** synchronisés + **signatures**
- ⌨️ **Raccourcis clavier** Gmail-like (`j`/`k`, `r`, `f`, `e`, `#`)
- 🌙 **Dark mode** complet
- 🔐 **PGP server-side** (gpg keyring jetable, auto-import inbound)
- 🖼️ **Proxy d'images** signé HMAC anti-tracking
- 📎 **Pièces jointes S3** (MinIO inclus, URLs signées 7 jours, cards Outlook-style avec preview)
- 🎯 **Autocomplete style O365** dans le composer (To/Cc) avec avatars + fréquence d'envoi

### 👤 Espace utilisateur `/account`
Sidebar OWA-style avec sections **Général** :
- **Mon compte** : profil étendu (Prénom, Nom, Initiales, Téléphone fixe/mobile, Fonction, Société, Adresse postale complète, Fuseau horaire, Langue)
- **Avatar uploadable** (96px, PNG/JPG/WebP/GIF) + SVG initiales fallback avec gradient déterministe
- **Sécurité & 2FA** : changement mot de passe (BCRYPT Dovecot) + activation **TOTP** (Google Authenticator / Authy / 1Password / Bitwarden) avec 8 codes de récupération one-shot
- **Tokens SMTP** : mots de passe app pour Thunderbird/iPhone Mail/scripts, révocables
- **Identity switcher** style Outlook 365 (basculer entre boîte perso et boîtes partagées)

### 🏢 Organisation (style M365 / O365)
- 🌐 **Multi-domaines** par organisation avec **domaine primaire** (affiché par défaut sur `/login`)
- 👥 **RBAC 4 rôles** : `owner` (tout) > `admin` (domaines/comptes/settings) > `support` (lecture + reset pwd) > `member` (mail seul). Anti-lockout sur dernier owner
- 🔐 **2FA TOTP obligatoire** pour owner/admin/support (middleware `Force2faForAdmins` bloque l'accès admin sans TOTP activé)
- 📇 **Annuaire `/people`** : grid avec photos + badges rôle + recherche full-text sur tous les membres de l'orga
- 📒 **Carnet d'adresses double niveau** :
  - **Personnel** (privé à chaque user) — auto-capture des destinataires à chaque envoi réussi + CRUD perso, tri par fréquence d'envoi
  - **Organisation** (partagé) — owner/admin gèrent, tous lisent ; sync auto avec les comptes mail (membres)
- 🎨 **Branding personnalisable** (owner) : logo orga uploadé + texte footer + URL custom sur `/login` et `/admin/login` (remplace le lien Github par défaut)
- 📊 **Stats orga** : nombre de membres, domaines, comptes

### ⚙️ Admin panel `/admin` — design Exchange Admin Center
Sidebar verticale 256px avec 5 sections groupées :

| Section | Items |
|---|---|
| 📊 **Vue d'ensemble** | Tableau de bord (stats domaines/comptes) |
| 🏢 **Organisation** | Paramètres · Membres & RBAC · Annuaire · Carnet adresses |
| 📬 **Boîtes mail** | Domaines · Comptes · Boîtes partagées · Forwards · Listes |
| 🛡️ **Sécurité** | Anti-spam · Quarantaine · Audit log |
| 🔧 **Système** | Migrations IMAP · Paramètres app |

Détails :
- 🌐 **Multi-domaines** avec page DNS records par domaine + bouton "définir primaire"
- 👤 **Comptes** : créer, suspendre, supprimer, reset password, **quota éditable inline**, **édition complète** (email/domaine, profil étendu) via modale, **renomme le maildir Dovecot** au changement d'email
- 🔁 **Forwards / aliases** multi-destination, on/off
- 📬 **Listes de diffusion** 3 scopes (interne / domaines internes / public, enforcement Postfix policy daemon)
- 👥 **Boîtes partagées** avec ACL multi-utilisateurs (read / send / manage)
- 📥 **Quarantaine spam** : voir les mails en HOLD Postfix, prévisualiser, **forcer la délivrance** ou supprimer
- 🛡️ **Anti-spam rspamd** : stats, seuils visuels, historique, top symboles, apprentissage bayésien
- 🚚 **Migration IMAP** (imapsync) en background : presets Gmail / Outlook 365 / OVH, progress bar
- 📋 **Audit logs** (15+ actions admin tracées avec IP/User-Agent/JSON metadata)
- ⚙️ **Paramètres globaux** : toggle inscriptions, quotas défaut/max, sauvegardes auto

### 🔐 Sécurité authentification — brute-force protection
3 axes Redis sur `/login` et `/login/2fa` :

| Compteur | Seuil | Lock |
|---|---|---|
| 🔍 **UNKNOWN(ip)** — POST sur email inexistant | 5 tentatives | 10 min, **× 2 chaque récidive** (cap 24h) |
| 👤 **ACCOUNT(email)** — mauvais password ou TOTP | 3 tentatives | 5 min |
| 🌐 **IP(ip)** — IP avec 3+ échecs sur comptes valides | 3 tentatives | 10 min |

Pre-check avant `verifyPassword`, reset auto sur login OK, hit window 15 min, messages user-friendly avec compte à rebours.

### 🎨 Login / Admin Login dédiés
- **`/login`** : page utilisateur split-screen OWA-style, panneau gauche gradient orange Noliae avec **logo animé** (barres equalizer ondulantes, respect `prefers-reduced-motion`)
- **`/admin/login`** : page dédiée admin, panneau gauche gradient sombre — accès direct au Centre d'administration
- **Logo orga** affiché au-dessus du formulaire si un owner en a uploadé un (`/org` → Branding)
- **Footer customisable** : phrase d'accroche + label/URL — remplace le lien Github par défaut, ou laisse vide pour ne rien afficher

### 🛡️ Couche infrastructure
- 🛡️ Anti-spam **rspamd** + signature **DKIM auto** (opendkim, clé 2048-bit générée au premier boot, persistée dans volume `postfix-dkim`)
- 🚫 **Rate limit outbound** par compte SASL (30/min, 300/h, 1000/j configurables, sliding window Redis)
- 🔒 **TLS automatique** Let's Encrypt (Traefik) + auto-signé fallback pour Postfix
- 📧 **Submission 587** STARTTLS + SASL Dovecot (mail_accounts.password OU tokens SMTP)
- 💾 **Backup tar.gz** programmable (pg_dump + Maildirs + bucket MinIO, rétention 30j)
- 🔄 **Reset nightly optionnel** (`demo:reset`, opt-in via `DEMO_RESET=true` pour les instances de test public — purge users non-admin chaque nuit). Service `scheduler` lance `artisan schedule:run` chaque minute
- 🎯 **3 daemons Python policy** embarqués dans le container Postfix : `queue-daemon.py` (admin quarantaine), `list-policy.py` (scope mailing lists), `rate-limit.py` (per-user)

---

## 🚀 Quickstart

**Prérequis :** un serveur Linux avec Docker (Compose v2), un domaine que tu contrôles, les ports `25/80/143/443/465/587/993` ouverts au public. Idéalement avec un rDNS configuré.

```bash
git clone https://github.com/Noliae/noliae-mail-oss.git
cd noliae-mail-oss
./install.sh         # interactif : domaine, DKIM, secrets, DNS-RECORDS.txt
docker compose up -d
```

Le script `install.sh` te demande ton domaine et génère :
- Un `.env` complet avec secrets aléatoires forts (APP_KEY, DB_PASSWORD, MAIL_MASTER_PASS, MINIO_ROOT_PASSWORD, POSTFIX_QUEUE_TOKEN, RSPAMD_PASSWORD)
- L'`ADMIN_EMAIL` + `ADMIN_PASSWORD` (le compte est créé automatiquement par `php artisan mail:install` au premier boot du container web)
- Une paire de clés DKIM RSA 2048 générée par `opendkim-genkey` au premier démarrage du container Postfix (persistée dans le volume `postfix-dkim`)
- Une **Default Organization** + admin promu **owner** automatiquement
- Un fichier `DNS-RECORDS.txt` avec tous les enregistrements à publier chez ton registrar (A, MX, SPF, DKIM, DMARC, MTA-STS, TLS-RPT, **+ A pour `s3.<domain>`** pour les pièces jointes)

Après `docker compose up -d`, l'admin est prêt — connecte-toi sur `https://${MAIL_DOMAIN}/login` avec `ADMIN_EMAIL` + `ADMIN_PASSWORD`. Le webmail tourne sur `/webmail`, l'espace user sur `/account`, l'admin sur `/admin`, le centre d'admin dédié sur `/admin/login`.

### Récupérer le DKIM TXT à publier en DNS

La clé est générée auto au premier boot, lis-la avec :

```bash
docker compose exec postfix cat /etc/opendkim/keys/${MAIL_DOMAIN}/mail.txt
```

Ou ouvre `/admin/domains/{id}/dns` dans l'admin panel — le record TXT y est affiché formaté.

### Migration depuis un autre serveur

Cf [MIGRATION.md](MIGRATION.md). Plusieurs options :
- **CLI imapsync** : commande directe (`imapsync --host1 ... --host2 ...`)
- **UI admin** : `/admin/migrations` avec presets Gmail / Outlook 365 / OVH, progress bar, lance imapsync en background

---

## 🏗️ Architecture

```
                    ┌─────────┐
   Internet ──443──▶│ Traefik │──┐
   Internet ──25 ───────────────│
   Internet ──587───────────────│
                    └─────────┘  │
                                 ▼
                          ┌───────────┐         ┌──────────┐
                          │ web (PHP) │◀───────▶│ Postgres │
                          │ Inertia   │         └──────────┘
                          │ + Vue     │              ▲
                          └─────┬─────┘              │
                                │                    │
              ┌─────────────────┼─────────────────┐  │
              ▼                 ▼                 ▼  │
      ┌──────────────┐  ┌──────────────┐  ┌──────────┐│
      │ Dovecot IMAP │  │ Postfix SMTP │  │  MinIO   ││
      │     :143/993 │  │ :25/465/587  │  │  (S3)    ││
      │  + LMTP 24   │  │ + opendkim   │  │ s3.dom.. ││
      └───────┬──────┘  │ + 3 daemons  │  └──────────┘│
              │         │  Python TCP  │              │
              │         └──────┬───────┘              │
              └────────┬───────┘                      │
                       ▼                              │
                ┌────────────┐         ┌──────────┐   │
                │   rspamd   │◀────────│  Redis   │───┘
                │            │ history │  (cache  │
                └────────────┘ + bayes │   + rate │
                                       │   limit) │
                                       └──────────┘
                       ┌──────────────┐
                       │  scheduler   │  artisan schedule:run every minute
                       │ (demo:reset, │  (reset nightly, model:prune)
                       │   pruning)   │
                       └──────────────┘
```

9 conteneurs (web · scheduler · postgres · redis · minio · minio-init · postfix · dovecot · rspamd · traefik). ~600 Mo d'images totales. Le webmail démarre en ~10s à froid.

---

## 🔐 Authentification & RBAC

**Pas de SSO**, pas d'OAuth, pas de dépendance à un IdP externe. Tout est local :

- **Côté utilisateur** : login email + mot de passe + 2FA TOTP optionnel sur `https://${MAIL_DOMAIN}/login` → session web + accès IMAP/SMTP avec les mêmes identifiants (BCRYPT compatible Dovecot)
- **Tokens SMTP** : mots de passe applicatifs séparés pour Thunderbird/iPhone/scripts (gérés par l'user dans `/account`)
- **Côté admin** : page dédiée `https://${MAIL_DOMAIN}/admin/login` (Centre d'administration) ; accès basé sur le **rôle RBAC** dans l'organisation (`owner` / `admin` / `support`). Anti-lockout sur le dernier owner.
- **2FA obligatoire** pour tout rôle admin (middleware `Force2faForAdmins`). Sans TOTP activé, redirection forcée vers `/account?force_2fa=1` à chaque tentative d'accès admin.
- **Brute-force protection** : 3 axes Redis (UNKNOWN ip / ACCOUNT email / IP) avec backoff exponentiel sur UNKNOWN.
- **Inscriptions publiques** : désactivées par défaut. Toggle dans `/admin/settings`.

> **Note quota** : quand tu changes le quota d'un compte existant, le webmail affiche immédiatement la nouvelle valeur (lue en direct depuis la DB). Pour que Dovecot applique le quota côté IMAP, l'utilisateur doit se déconnecter/reconnecter (cache `maildirsize`).

---

## 🛡️ Anti-spam & sécurité outbound

Trois couches qui se combinent :

1. **Inbound rspamd milter** scanne tous les mails entrants + filtres standard (SPF/DKIM/DMARC, RBLs, bayes, sieve)
2. **HOLD queue Postfix** : les mails marqués spam (`X-Spam-Flag: YES`) sont mis en quarantaine au lieu d'être rejetés au SMTP → review admin via `/admin/spam` → release ou delete
3. **Rate limit outbound** : daemon Python policy + Redis sliding window. Si un compte SASL envoie trop (>30/min ou >300/h ou >1000/j), retour `450 4.7.0 quota dépassé`. Bloque les comptes compromis sans bouncer l'utilisateur légitime

DKIM signing automatique sur tous les mails sortants via opendkim. SPF + DMARC + MTA-STS + TLS-RPT générés par `install.sh`.

---

## 🔧 Configuration `.env`

| Variable | Défaut | Description |
|---|---|---|
| `MAIL_DOMAIN` | `mail.example.com` | Domaine principal (utilisateurs : `user@MAIL_DOMAIN`) |
| `ADMIN_EMAIL` | `admin@example.com` | Compte admin initial promu owner par `mail:install` |
| `ADMIN_PASSWORD` | (généré si vide) | Password initial admin (affiché dans `docker compose logs web` si vide) |
| `ALLOW_REGISTRATION` | `false` | Autoriser inscriptions publiques (toggle via admin) |
| `DEMO_RESET` | `false` | Si `true`, `demo:reset` tourne chaque nuit (purge users non-admin) — à activer uniquement pour les instances de test public, NE PAS activer en prod |
| `MAIL_QUOTA_BYTES` | `5G` | Quota défaut par boîte |
| `MAIL_IMAP_ENCRYPTION` | vide | "starttls" / "ssl" / vide (plain interne) |
| `MAIL_TRUSTED_NETS` | `127/8 [::1]/128 172.16/12` | Réseaux autorisés à relayer sans auth |
| `MAIL_DELIVERY_MODE` | `auto` | `sync` (direct) ou `queue` (RabbitMQ — pour scale) |
| `AWS_ENDPOINT` | `https://s3.${MAIL_DOMAIN}` | URL publique MinIO/S3 (pièces jointes) |
| `RSPAMD_URL` | `http://rspamd:11334` | Controller rspamd (pour `/admin/rspamd`) |
| `RSPAMD_PASSWORD` | (généré) | Auth controller rspamd |
| `POSTFIX_QUEUE_TOKEN` | (généré) | Auth daemon queue admin |
| `DB_PASSWORD` | (généré) | Postgres |
| `MAIL_MASTER_PASS` | (généré) | Master password Dovecot (webmail impersonation) |
| `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` | `noliae` / (généré) | Accès S3 MinIO |

Voir `.env.example` pour la liste complète.

---

## 🧪 Tester avant de déployer en prod

```bash
# 1. Login admin + créer un compte test
curl https://${MAIL_DOMAIN}/admin/login → admin@... / ADMIN_PASSWORD
/admin/accounts → "Créer" → testuser@${MAIL_DOMAIN}

# 2. Tester envoi de mail
# Depuis le webmail OU via SMTP token :
swaks --server ${MAIL_DOMAIN} --port 587 --tls \
      --auth-user testuser@${MAIL_DOMAIN} --auth-password <token> \
      --to votre-gmail@gmail.com --body "Test depuis Noliae OSS"

# 3. Tester réception
# Envoie un mail depuis Gmail vers testuser@${MAIL_DOMAIN}
# Vérifie qu'il arrive dans la boîte webmail

# 4. Tester l'anti-spam (vrai GTUBE pattern)
echo "Subject: gtube test" | swaks ... \
  --header 'X-Spam-Flag: YES' \
  --to testuser@${MAIL_DOMAIN}
# Doit apparaître dans /admin/spam (quarantaine)

# 5. Tester le 2FA + brute-force protection
/account → Sécurité & 2FA → Activer 2FA → scan QR → confirmer
Log out + re-login → écran TOTP s'affiche
Tente 6 fois avec un faux email → IP bloquée 10 min

# 6. Tester l'autocomplete carnet
/webmail → Compose → tape les 2 premières lettres d'un destinataire connu
→ popup avec avatars + badges (mes contacts / interne / orga)

# 7. Tester l'organisation
/org → Paramètres → upload logo + footer custom
/org/members → ajouter un user en rôle admin → il doit activer 2FA pour entrer
```

---

## 🤝 Contribuer

Pull requests bienvenues sur [github.com/Noliae/noliae-mail-oss](https://github.com/Noliae/noliae-mail-oss). Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour le style et le workflow.

Bugs critiques / sécu : `security@noliae.com` (cf [SECURITY.md](SECURITY.md)).

---

## 📜 License

[AGPL-3.0](LICENSE) — si tu héberges Noliae Mail comme service pour des tiers, tu dois publier tes modifications.

---

## 💛 À propos

Noliae Mail OSS est extrait du webmail propriétaire opéré par [Noliae](https://noliae.com), un écosystème français de services en ligne souverains (mail, search, IA, agenda, drive). La version SaaS hébergée inclut en plus l'IA générative (smart-reply, traduction, résumé, anti-phishing) avec une clé API que tu peux brancher sur cette version OSS via `/admin/settings`.

→ [noliae.com](https://noliae.com) — [opensources.noliae.com](https://opensources.noliae.com)
