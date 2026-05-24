# Migration vers Noliae Mail OSS

Guide pour importer une base existante depuis un autre serveur mail.

## 📦 Migration IMAP brute (universel)

Marche pour **n'importe quel serveur IMAP** (Gmail, Outlook, ProtonMail Bridge, OVH, Roundcube backend, SOGo backend…). On copie les mails dossier par dossier.

### Pré-requis

```bash
sudo apt install imapsync   # Debian/Ubuntu
brew install imapsync       # macOS
```

### Synchroniser un compte

```bash
imapsync \
  --host1 imap.ancien-serveur.com --user1 user@ancien.com --password1 'xxx' \
  --host2 mail.exemple.com         --user2 user@exemple.com  --password2 'yyy' \
  --ssl1 --ssl2 --automap
```

`imapsync` est **idempotent** : tu peux relancer en boucle sans dupliquer. À chaque passage il ne copie que les nouveaux mails.

### Migration en masse

```bash
# liste.csv : user1@ancien;pw1;user1@nouveau;pw2
while IFS=';' read -r u1 p1 u2 p2; do
  imapsync --host1 imap.ancien.com --user1 "$u1" --password1 "$p1" \
           --host2 mail.exemple.com --user2 "$u2" --password2 "$p2" \
           --ssl1 --ssl2 --automap --no-modulesversion
done < liste.csv
```

## 🐘 Migration Roundcube (TODO)

Le projet Roundcube ne stocke que des préférences/carnet d'adresses en MySQL ; les mails sont déjà dans IMAP — utilise donc la procédure « IMAP brute » ci-dessus. À venir : un script Python pour importer les contacts depuis `roundcube.contacts` vers Noliae Mail OSS (table à créer côté OSS).

## 🦉 Migration SOGo (TODO)

Idem : les mails sont déjà dans le backend IMAP du SOGo (Cyrus/Dovecot). Migration mail = imapsync. Migration des calendriers/contacts CalDAV/CardDAV = à venir (Noliae Mail OSS ne fait que du mail aujourd'hui, pas d'agenda).

## ☁️ Migration depuis Gmail / Google Workspace

1. Active les "mots de passe d'application" sur le compte Google
2. `imapsync --host1 imap.gmail.com --port1 993 --user1 ... --password1 <app_password> --host2 mail.exemple.com ...`
3. Quota : Gmail garde les libellés sous forme de dossiers IMAP (`[Gmail]/Sent`, etc.). imapsync les mappe automatiquement vers `Sent`, `Drafts`, `Trash`, `Spam`.

## 📤 Migration depuis Outlook / Microsoft 365

1. Active IMAP dans les paramètres du compte
2. `imapsync --host1 outlook.office365.com --port1 993 --authmech1 PLAIN --user1 ... --password1 <password> --host2 mail.exemple.com ...`

## 🔁 Bascule DNS

Après migration des mails :

1. Mettre l'ancien serveur en **lecture seule** (pour ne plus recevoir)
2. Modifier le **MX** : `MX 10 mail.exemple.com.` (au lieu de l'ancien serveur)
3. Attendre propagation DNS (TTL — typiquement 5-30 min)
4. Relancer un dernier `imapsync` une fois la propagation faite pour récupérer les mails arrivés sur l'ancien dans l'intervalle
5. Surveiller `journalctl -u postfix` pour confirmer la réception sur le nouveau

## 🆘 Aide

Tu rencontres un blocage de migration ? Ouvre une issue [github.com/Noliae/noliae-mail-oss/issues](https://github.com/Noliae/noliae-mail-oss/issues) avec le serveur source + un échantillon de logs.
