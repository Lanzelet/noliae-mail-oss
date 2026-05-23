# Noliae Mail OSS

> Webmail souverain auto-hébergeable. Open source, sans IA, sans tracking, sans dépendance cloud.

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![Docker](https://img.shields.io/badge/docker-ready-blue.svg)](docker-compose.yml)

**Noliae Mail OSS** est la version open-source du webmail [Noliae](https://noliae.com). Tout ce qui touche au mail (lecture, écriture, recherche, dossiers, labels, brouillons, undo send, schedule send, snooze, vacation, signatures, PGP, dark mode, raccourcis clavier) est inclus. Sont retirés : l'IA générative (smart-reply, traduction, résumé, anti-phishing), le panneau d'administration centralisé, et l'intégration SSO avec le compte Noliae.

## ✨ Fonctionnalités

- 📥 IMAP + SMTP via Postfix/Dovecot (Maildir, quotas par compte)
- 🔍 Recherche full-text dans le sujet/corps/expéditeur
- 🏷️ Labels colorés + étoiles + mode lu/non-lu
- ⏰ **Snooze** (programmer un mail pour qu'il revienne plus tard)
- 📅 **Schedule send** (envoi différé)
- ↩️ **Undo send** (30s pour annuler après envoi)
- 🌴 **Vacation responder** (réponse auto en congés)
- ✉️ **Brouillons** synchronisés IMAP
- 🔐 **PGP** server-side (gpg keyring jetable) — déchiffrement, signature, annuaire de clés publiques
- ⌨️ Raccourcis clavier Gmail-like (`j`/`k`, `r`, `f`, `e`, `#`, etc.)
- 🌙 Dark mode complet
- 🖼️ Proxy d'images sécurisé (HMAC, anti-tracking)
- 📎 Pièces jointes via stockage S3 (MinIO inclus)
- 🛡️ Anti-spam **rspamd** + signature **DKIM** + filtres **Sieve**
- 🔒 TLS automatique via Let's Encrypt (Traefik)
- 🔑 Auth locale (email + mot de passe BCRYPT — compatible Dovecot)

## 🚀 Quickstart

**Prérequis :** un serveur Linux avec docker (compose v2), un domaine que tu contrôles, et les ports `25/80/143/443/465/587/993` ouverts au public. **Idéalement avec un rDNS bien configuré.**

```bash
git clone https://github.com/noliae/mail.git
cd mail
./install.sh
```

Le script te demande ton domaine et génère :
- Un `.env` avec des secrets aléatoires forts
- Une paire de clés DKIM (RSA 2048)
- Un fichier `DNS-RECORDS.txt` avec les enregistrements à publier (A, MX, SPF, DKIM, DMARC, MTA-STS, TLS-RPT)

Une fois les DNS publiés et propagés :

```bash
docker compose up -d
```

Crée ton premier compte sur `https://${MAIL_DOMAIN}/register` puis utilise-le aussi dans ton client mobile/desktop (IMAP `${MAIL_DOMAIN}:993`, SMTP `${MAIL_DOMAIN}:465`).

## 🏗️ Architecture

```
                    ┌─────────┐
   Internet ──443──▶│ Traefik │──┐
                    └─────────┘  │
                                 ▼
                          ┌───────────┐         ┌──────────┐
                          │ web (PHP) │◀───────▶│ Postgres │
                          └─────┬─────┘         └──────────┘
                                │
              ┌─────────────────┼─────────────────┐
              ▼                 ▼                 ▼
      ┌──────────────┐  ┌──────────────┐  ┌──────────┐
      │ Dovecot IMAP │  │ Postfix SMTP │  │  MinIO   │
      │     :143/993 │  │     :25/587  │  │   (S3)   │
      └───────┬──────┘  └──────┬───────┘  └──────────┘
              │                │
              └────────┬───────┘
                       ▼
                ┌────────────┐
                │   rspamd   │ DKIM signing + antispam
                └────────────┘
```

Tous les services tournent dans un seul `docker compose` (8 conteneurs, ~600 Mo image totale).

## 🔧 Configuration

Le `.env` accepte ces variables (voir `.env.example`) :

| Variable | Défaut | Description |
|---|---|---|
| `MAIL_DOMAIN` | `mail.example.com` | Ton domaine mail (utilisateurs : `user@MAIL_DOMAIN`) |
| `ADMIN_EMAIL` | `admin@example.com` | Pour Let's Encrypt + premier compte |
| `ALLOW_REGISTRATION` | `true` | Autoriser la création de comptes via web |
| `MAIL_QUOTA_BYTES` | `5G` | Quota par défaut par boîte |
| `DB_PASSWORD` | (généré) | Mot de passe Postgres |
| `MAIL_MASTER_PASS` | (généré) | Master password Dovecot (pour le webmail) |

## 🔄 Migration depuis un autre webmail

À venir : scripts d'import pour Roundcube, SOGo, et IMAP brut.

## 🤝 Contribuer

Pull requests bienvenues sur [github.com/noliae/mail](https://github.com/noliae/mail). Le projet suit le style Laravel/Vue 3 standard. Pour les bugs critiques ou questions de sécu : `security@noliae.com`.

## 📜 License

[AGPL-3.0](LICENSE) — si tu héberges Noliae Mail comme service pour des tiers, tu dois publier tes modifications.

## 💛 À propos

Noliae Mail OSS est extrait du webmail propriétaire opéré par [Noliae](https://noliae.com), un écosystème français de services en ligne souverains (mail, search, IA, agenda, drive). La version SaaS hébergée inclut en plus l'IA générative, le SSO global, et un support technique.

→ [noliae.com](https://noliae.com) — [demo-mail.noliae.com](https://demo-mail.noliae.com) (démo en ligne) — [opensources.noliae.com](https://opensources.noliae.com)
