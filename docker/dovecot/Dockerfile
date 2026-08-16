FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends \
        dovecot-imapd dovecot-lmtpd dovecot-pgsql dovecot-sieve dovecot-managesieved \
        ca-certificates gettext-base postgresql-client openssl \
 && rm -rf /var/lib/apt/lists/* \
 && useradd -u 5000 -d /var/vmail -s /usr/sbin/nologin vmail \
 && mkdir -p /var/vmail \
 && chown vmail:vmail /var/vmail

# Liste explicite des configs Dovecot (et pas COPY . qui copie le Dockerfile)
COPY dovecot.conf                  /etc/dovecot/dovecot.conf
COPY dovecot-sql.conf.ext          /etc/dovecot/dovecot-sql.conf.ext
COPY dovecot-sql-tokens.conf.ext   /etc/dovecot/dovecot-sql-tokens.conf.ext
COPY sieve/before.sieve            /etc/dovecot/sieve/before.sieve
COPY entrypoint.sh                 /entrypoint.sh
RUN chmod +x /entrypoint.sh \
 && sievec /etc/dovecot/sieve/before.sieve

EXPOSE 143 993 24
ENTRYPOINT ["/entrypoint.sh"]
CMD ["dovecot", "-F"]
