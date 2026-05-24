#!/bin/sh
set -e

# Si RSPAMD_PASSWORD défini : hash via rspamadm pw et substitue dans la conf
if [ -n "${RSPAMD_PASSWORD:-}" ]; then
  HASH=$(rspamadm pw -e -p "$RSPAMD_PASSWORD" 2>&1 | tail -1)
  export RSPAMD_PASSWORD="$HASH"
else
  export RSPAMD_PASSWORD=""
fi

# Rend les confs depuis le template (substitue ${RSPAMD_PASSWORD})
mkdir -p /etc/rspamd/local.d
for f in /etc/rspamd/local.d.tmpl/*; do
  name=$(basename "$f")
  envsubst '${RSPAMD_PASSWORD}' < "$f" > "/etc/rspamd/local.d/$name"
done

exec "$@"
