milter = yes;
timeout = 120s;
# Bind 0.0.0.0 pour que le container postfix joigne le milter via réseau intnet.
bind_socket = "*:11332";
upstream "local" {
  default = yes;
  self_scan = yes;
}
