# Controller HTTP rspamd (port 11334) — utilisé par /admin/rspamd.
# Le password vient de RSPAMD_PASSWORD (entrypoint le substitue) ; si vide,
# on enable un mode "secure_ip" qui n'exige pas de password pour le réseau
# Docker interne (10.0/8, 172.16/12, 192.168/16, 127/8, [::1]).
bind_socket = "0.0.0.0:11334";
password = "${RSPAMD_PASSWORD}";
secure_ip = "127.0.0.1";
secure_ip = "::1";
secure_ip = "10.0.0.0/8";
secure_ip = "172.16.0.0/12";
secure_ip = "192.168.0.0/16";
