#!/usr/bin/env python3
"""
Noliae Mail OSS — Postfix policy daemon : enforce mailing list scopes.

Postfix appelle ce daemon avant d'accepter un RCPT TO=<list@domain>.
On lit la liste cible en Postgres, on regarde son scope et l'expediteur :
  - scope=internal         : sender doit appartenir au MEME domaine que la liste
  - scope=internal_domains : sender doit appartenir a un domaine actif dans mail_domains
  - scope=any              : ouvert

Si refuse, on renvoie REJECT avec un message explicite (le sender voit le 5xx).
Sinon DUNNO (= Postfix passe a la regle suivante, n'impose pas).

Installation Postfix (main.cf) :
    smtpd_recipient_restrictions =
        permit_mynetworks,
        check_policy_service { inet:127.0.0.1:10031 max_idle=120 }
        reject_unauth_destination,
        ...

Demarrer ce script en daemon (systemd unit fourni en commentaire bas).
"""
import os
import socket
import socketserver
import sys
import psycopg2
from urllib.parse import urlparse

PORT = int(os.environ.get('LIST_POLICY_PORT', 10031))
DB_DSN = os.environ.get('DATABASE_URL') or (
    f"host={os.environ.get('DB_HOST', 'postgres')} "
    f"port={os.environ.get('DB_PORT', '5432')} "
    f"dbname={os.environ.get('DB_NAME', 'noliaemail')} "
    f"user={os.environ.get('DB_USER', 'noliae')} "
    f"password={os.environ.get('DB_PASSWORD', '')}"
)


def check_policy(sender: str, recipient: str) -> str:
    """Retourne la reponse Postfix pour ce couple sender->recipient."""
    sender = (sender or '').strip().lower()
    recipient = (recipient or '').strip().lower()
    if not recipient or '@' not in recipient:
        return 'action=DUNNO'

    try:
        conn = psycopg2.connect(DB_DSN, connect_timeout=3)
        cur = conn.cursor()
        cur.execute(
            "SELECT scope FROM mail_lists WHERE address = %s AND active = true",
            (recipient,)
        )
        row = cur.fetchone()
        if not row:
            # Pas une liste : on laisse Postfix gerer.
            return 'action=DUNNO'
        scope = row[0]

        # Postmaster local toujours OK (sender = '' aussi = bounce).
        if not sender or sender == 'postmaster':
            return 'action=DUNNO'

        if scope == 'any':
            return 'action=DUNNO'

        sender_domain = sender.split('@', 1)[1] if '@' in sender else ''
        recip_domain = recipient.split('@', 1)[1]

        if scope == 'internal':
            if sender_domain == recip_domain:
                return 'action=DUNNO'
            return (f'action=REJECT 5.7.1 Cette liste ({recipient}) est reservee '
                    f'aux membres du domaine {recip_domain}.')

        if scope == 'internal_domains':
            cur.execute(
                "SELECT 1 FROM mail_domains WHERE name = %s AND active = true",
                (sender_domain,)
            )
            if cur.fetchone():
                return 'action=DUNNO'
            return (f'action=REJECT 5.7.1 Cette liste ({recipient}) est reservee '
                    f'aux domaines internes du serveur.')

        # Defaut : laisse passer (scope inconnu)
        return 'action=DUNNO'

    except Exception as e:
        # En cas d'erreur DB, on laisse passer pour ne pas casser le mail.
        sys.stderr.write(f'[list-policy] ERROR: {e}\n')
        return 'action=DUNNO'
    finally:
        try:
            cur.close(); conn.close()
        except Exception:
            pass


class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        attrs = {}
        while True:
            raw = self.rfile.readline()
            if not raw:                            # EOF reseau
                break
            line = raw.decode('utf-8', errors='replace').rstrip('\r\n')
            if line == '':                         # fin de requete Postfix
                if attrs:
                    sender = attrs.get('sender', '')
                    recipient = attrs.get('recipient', '')
                    result = check_policy(sender, recipient)
                    self.wfile.write((result + '\n\n').encode())
                    self.wfile.flush()
                    attrs = {}
                continue
            if '=' in line:
                k, v = line.split('=', 1)
                attrs[k] = v


class ThreadedTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == '__main__':
    server = ThreadedTCPServer(('127.0.0.1', PORT), Handler)
    sys.stderr.write(f'[list-policy] Listening on 127.0.0.1:{PORT}\n')
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        server.shutdown()

# ─── systemd unit (à coller dans /etc/systemd/system/oss-list-policy.service) ───
#
# [Unit]
# Description=Noliae Mail OSS — list scope policy daemon
# After=network.target postgresql.service
#
# [Service]
# Type=simple
# Environment=DB_HOST=127.0.0.1
# Environment=DB_NAME=noliaemail
# Environment=DB_USER=noliae
# Environment=DB_PASSWORD=changeme
# ExecStart=/usr/bin/python3 /opt/noliae/list-policy.py
# Restart=on-failure
# User=nobody
#
# [Install]
# WantedBy=multi-user.target
