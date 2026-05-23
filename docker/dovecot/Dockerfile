FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends \
        dovecot-imapd dovecot-lmtpd dovecot-pgsql dovecot-sieve dovecot-managesieved \
        ca-certificates gettext-base \
 && rm -rf /var/lib/apt/lists/*
COPY . /etc/dovecot/
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && mkdir -p /var/vmail && useradd -u 5000 -d /var/vmail -s /usr/sbin/nologin vmail && chown vmail:vmail /var/vmail
EXPOSE 143 993 24
ENTRYPOINT ["/entrypoint.sh"]
CMD ["dovecot", "-F"]
