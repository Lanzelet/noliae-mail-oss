# Noliae Mail OSS — Postfix (MTA entrant + relay sortant).
# Lookup utilisateurs/domaines/aliases via Postgres (table mail_accounts).
FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        postfix postfix-pgsql ca-certificates gettext-base rsyslog \
 && rm -rf /var/lib/apt/lists/*

# Configs templates : seront rendues au démarrage via envsubst (cf entrypoint).
COPY main.cf.tmpl   /etc/postfix/main.cf.tmpl
COPY master.cf.tmpl /etc/postfix/master.cf.tmpl
COPY pgsql/         /etc/postfix/pgsql/
COPY entrypoint.sh  /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 25 465 587
ENTRYPOINT ["/entrypoint.sh"]
CMD ["postfix", "start-fg"]
