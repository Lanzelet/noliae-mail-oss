FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends \
        rspamd ca-certificates redis-tools \
 && rm -rf /var/lib/apt/lists/*
COPY . /etc/rspamd/local-overrides/
RUN cp -r /etc/rspamd/local-overrides/local.d/* /etc/rspamd/local.d/ 2>/dev/null || true
EXPOSE 11332 11333 11334
CMD ["rspamd", "-f", "-u", "_rspamd", "-g", "_rspamd"]
