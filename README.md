# Noliae Mail OSS

> Webmail souverain auto-hébergeable. Open source, sans IA imposée, sans tracking, sans dépendance cloud.

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![Docker](https://img.shields.io/badge/docker-ready-blue.svg)](docker-compose.yml)
[![Demo](https://img.shields.io/badge/demo-oss.noliae.com-FF4D2E.svg)](https://oss.noliae.com)

**Noliae Mail OSS** est la version open-source du webmail [Noliae](https://noliae.com). L'objectif : remplacer Google Workspace / Microsoft 365 par une stack mail complète, contrôlable, qu'on peut installer chez soi en une heure. Tout le code utile (webmail, MTA, IMAP, anti-spam, admin) est dans ce repo.

> 🌐 **Démo en ligne** : https://oss.noliae.com — admin: `admin@oss.noliae.com` (mot de passe régénéré chaque nuit, demande sur la démo)

---

## ✨ Fonctionnalités

### Webmail utilisateur
- 📥 IMAP + SMTP via Postfix/Dovecot (Maildir, quotas par compte)
- 🔍 Recherche full-text dans le sujet/corps/expéditeur avec opérateurs (`from:`, `subject:`, `is:unread`…)
- 🏷️ Labels, étoiles, mode lu/non-lu, mode conversation
- ⏰ **Snooze** + **Schedule send** + **Undo send** (30s) + **Envoyer maintenant** pour skip
- 🌴 **Vacation responder**
- ✉️ **Brouillons IMAP** synchronisés + **signatures**
- ⌨️ Raccourcis clavier Gmail-like (`j`/`k`, `r`, `f`, `e`, `#`)
- 🌙 **Dark mode** complet
- 🔐 **PGP server-side** (gpg keyring jetable, auto-import inbound)
- 🖼️ **Proxy d'images** signé HMAC anti-tracking
- 📎 **Pièces jointes S3** (MinIO inclus, URLs signées 7 jours)

### Espace utilisateur `/account`
- 🔑 **Changer son mot de passe** (BCRYPT compatible Dovecot)
- 🔒 **2FA TOTP** (Google Authenticator / Authy / 1Password / Bitwarden) avec 8 codes de récupération one-shot
- 📨 **Tokens SMTP** (mots de passe app pour Thunderbird/iPhone Mail/scripts, révocables)
- 🔄 **Identity switcher** style Outlook 365 (basculer entre boîte perso et boîtes partagées)

### Admin panel `/admin`
- 📊 **Tableau de bord** (stats domaines/comptes)
- 🌐 **Multi-domaines** (CRUD + page DNS records personnalisés)
- 👤 **Comptes** (créer, suspendre, supprimer, reset password, **quota éditable inline**)
- 🔁 **Forwards / aliases** (multi-destination, on/off, design moderne)
- 📬 **Listes de diffusion** 3 scopes (interne / domaines internes / public, enforcement Postfix policy daemon)
- 👥 **Boîtes partagées** avec ACL multi-utilisateurs (rôles read / send / manage)
- 📥 **Quarantaine spam** : voir les mails en HOLD Postfix, prévisualiser, **forcer la délivrance** ou supprimer
- 🛡️ **Anti-spam rspamd** : stats, seuils visuels, historique, top symboles, apprentissage bayésien
- 🚚 **Migration IMAP** (imapsync) en background : presets Gmail / Outlook 365 / OVH, progress bar, polling temps réel
- ⚙️ **Paramètres globaux** : toggle inscriptions, quotas défaut/max, sauvegardes auto, IA Noliae optionnelle
- 📋 **Audit logs** (15 actions admin tracées avec IP/User-Agent/JSON metadata)

### Couche infrastructure
- 🛡️ Anti-spam **rspamd** + signature **DKIM auto** (opendkim) + filtres **header_checks**
- 🚫 **Rate limit outbound** par compte SASL (30/min, 300/h, 1000/j configurables, sliding window Redis)
- 🔒 **TLS automatique** Let's Encrypt (Traefik)
- 📧 **Submission 587** SASL avec mail_accounts.password OU tokens SMTP
- 💾 **Backup tar.gz** programmable (pg_dump + Maildirs + bucket MinIO, rétention 30j)

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
- Un fichier `DNS-RECORDS.txt` avec tous les enregistrements à publier chez ton registrar (A, MX, SPF, DKIM, DMARC, MTA-STS, TLS-RPT, **+ A pour `s3.<domain>`** pour les pièces jointes)

Après `docker compose up -d`, l'admin est prêt — connecte-toi sur `https://${MAIL_DOMAIN}/login` avec `ADMIN_EMAIL` + `ADMIN_PASSWORD`. Le webmail tourne sur `/webmail`, l'espace user sur `/account`, l'admin sur `/admin`.

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
                          └─────┬─────┘         └──────────┘
                                │                     ▲
              ┌─────────────────┼─────────────────┐   │
              ▼                 ▼                 ▼   │
      ┌──────────────┐  ┌──────────────┐  ┌──────────┐│
      │ Dovecot IMAP │  │ Postfix SMTP │  │  MinIO   ││
      │     :143/993 │  │ :25/465/587  │  │  (S3)    ││
      │  + LMTP 24   │  │              │  │ s3.dom.. ││
      └───────┬──────┘  └──────┬───────┘  └──────────┘│
              │                │                       │
              └────────┬───────┘                       │
                       ▼                               │
                ┌────────────┐         ┌──────────┐    │
                │   rspamd   │◀────────│  Redis   │────┘
                │ + opendkim │ history │  (cache) │
                └────────────┘ + bayes └──────────┘
                                            ▲
       ┌────────────────┐                   │
       │ rate-limit.py  │───────────────────┘
       │ list-policy.py │  policy daemons (Python TCP)
       │ queue-daemon.py│
       └────────────────┘
```

8 conteneurs principaux + 3 daemons système. ~600 Mo d'images totales. Le webmail démarre en ~10s à froid.

---

## 🔐 Authentification & administration

**Pas de SSO**, pas d'OAuth, pas de dépendance à un IdP externe. Tout est local :

- **Côté utilisateur** : login email + mot de passe + 2FA TOTP optionnel sur `https://${MAIL_DOMAIN}` → session web + accès IMAP/SMTP avec les mêmes identifiants (BCRYPT compatible Dovecot)
- **Tokens SMTP** : mots de passe applicatifs séparés pour Thunderbird/iPhone/scripts (gérés par l'user dans `/account`)
- **Côté admin** : le compte dont l'email = `admin_email` (settings DB ou `ADMIN_EMAIL` env) accède à `https://${MAIL_DOMAIN}/admin`
- **Inscriptions publiques** : désactivées par défaut. L'admin peut les réactiver dans `/admin/settings`

L'admin peut depuis `/admin` :
- Multi-domaines + page DNS records par domaine
- CRUD comptes (avec quota inline, anti-lockout admin)
- Forwards/aliases multi-destination
- Mailing lists avec 3 scopes (internal / internal_domains / any) enforced par policy daemon Postfix
- Boîtes partagées + ACL par rôle
- Quarantaine spam (HOLD queue Postfix) avec preview + release/delete
- Vue rspamd : stats, seuils visuels, historique, top symboles, apprentissage bayésien
- Migration IMAP via UI (imapsync background)
- Settings : inscriptions, quotas, sauvegardes auto, **intégration IA Noliae optionnelle** (clé API requise)
- Journal d'audit (15 actions tracées avec IP/UA/metadata JSON)

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
| `ADMIN_EMAIL` | `admin@example.com` | Compte admin initial (override via `/admin/settings` ensuite) |
| `ALLOW_REGISTRATION` | `false` | Autoriser inscriptions publiques (toggle via admin) |
| `MAIL_QUOTA_BYTES` | `5G` | Quota défaut par boîte |
| `MAIL_IMAP_ENCRYPTION` | vide | "starttls" / "ssl" / vide (plain interne) |
| `MAIL_TRUSTED_NETS` | `127/8 [::1]/128 172.16/12` | Réseaux autorisés à relayer sans auth |
| `MAIL_DELIVERY_MODE` | auto | `sync` (direct) ou `queue` (RabbitMQ — pour scale) |
| `AWS_ENDPOINT` | `https://s3.${MAIL_DOMAIN}` | URL publique MinIO/S3 (pièces jointes) |
| `RSPAMD_URL` | `http://rspamd:11334` | Controller rspamd (pour `/admin/rspamd`) |
| `RSPAMD_PASSWORD` | (généré) | Auth controller rspamd |
| `POSTFIX_QUEUE_TOKEN` | (généré) | Auth daemon queue admin |
| `DB_PASSWORD` | (généré) | Postgres |
| `MAIL_MASTER_PASS` | (généré) | Master password Dovecot (webmail impersonation) |

Voir `.env.example` pour la liste complète.

---

## 🧪 Tester avant de déployer en prod

```bash
# 1. Login admin + créer un compte test
curl https://${MAIL_DOMAIN}/login → admin@... / mot de passe
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

# 5. Tester le 2FA
/account → Activer 2FA → scan QR → confirmer
Log out + re-login → écran TOTP s'affiche
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

→ [noliae.com](https://noliae.com) — [oss.noliae.com](https://oss.noliae.com) (démo live) — [opensources.noliae.com](https://opensources.noliae.com)
