require ["fileinto"];

# Rspamd ajoute X-Spam-Status: Yes/No selon le score du milter (cf.
# docker/rspamd/local.d/actions.conf, seuil add_header = 6). "before" script :
# s'applique a tous les comptes avant tout filtre personnel, donc aucun
# message indesirable ne peut atterrir dans la boite de reception.
if header :contains "X-Spam-Status" "Yes" {
    fileinto "Junk";
    stop;
}
