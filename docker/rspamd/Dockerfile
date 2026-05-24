FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends \
        rspamd ca-certificates gettext-base redis-tools \
 && rm -rf /var/lib/apt/lists/*

# Copie uniquement les fichiers de conf, pas le Dockerfile lui-meme
COPY local.d /etc/rspamd/local.d.tmpl

# Entrypoint qui substitue RSPAMD_PASSWORD dans worker-controller.inc
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 11332 11333 11334
ENTRYPOINT ["/entrypoint.sh"]
CMD ["rspamd", "-f", "-u", "_rspamd", "-g", "_rspamd"]
