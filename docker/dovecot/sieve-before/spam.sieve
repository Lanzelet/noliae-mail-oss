require ["fileinto", "mailbox", "envelope"];

# 1. Rapports techniques (TLS-RPT, DMARC, ARC) → dossier Rapports, hors INBOX
if anyof (
    address :is "from" ["noreply-smtp-tls-reporting@google.com",
                        "noreply-dmarc-support@google.com",
                        "noreply-tlsrpt@gmail.com",
                        "tlsrpt@noliae.net",
                        "dmarc-noreply@yahoo.com",
                        "postmaster-noreply@outlook.com"],
    address :contains "from" ["dmarc", "tlsrpt", "smtp-tls-reporting", "mailer-daemon"],
    header :contains "Subject" ["Report Domain:", "TLS Report", "DMARC Aggregate", "Aggregate Report"]
) {
    fileinto :create "Rapports";
    stop;
}

# 2. Spam (rspamd) → Indésirables
if anyof (
    header :contains "X-Spam-Flag" "YES",
    header :contains "X-Spam" "Yes",
    header :contains "Subject" "[SPAM]"
) {
    fileinto :create "Junk";
    stop;
}
