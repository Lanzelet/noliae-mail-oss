# Noliae Mail OSS — Postfix (MTA entrant + relay sortant).
# Lookup utilisateurs/domaines/aliases via Postgres (table mail_accounts).
FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        postfix postfix-pgsql postfix-pcre \
        opendkim opendkim-tools \
        ca-certificates gettext-base rsyslog openssl netcat-openbsd \
        python3 python3-psycopg2 python3-redis \
 && rm -rf /var/lib/apt/lists/*

# Configs templates : seront rendues au démarrage via envsubst (cf entrypoint).
COPY main.cf.tmpl    /etc/postfix/main.cf.tmpl
COPY master.cf.tmpl  /etc/postfix/master.cf.tmpl
COPY pgsql/          /etc/postfix/pgsql/
COPY queue-daemon.py /usr/local/bin/queue-daemon.py
COPY rate-limit.py   /usr/local/bin/rate-limit.py
COPY list-policy.py  /usr/local/bin/list-policy.py
COPY entrypoint.sh   /entrypoint.sh
RUN chmod +x /entrypoint.sh /usr/local/bin/*.py

EXPOSE 25 465 587 10031 10032 10033
ENTRYPOINT ["/entrypoint.sh"]
CMD ["postfix", "start-fg"]
