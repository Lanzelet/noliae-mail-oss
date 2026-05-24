#!/usr/bin/env python3
"""
Noliae Mail OSS — rate limit outbound per SASL user.

Postfix policy daemon : pour chaque RCPT TO sur la submission (587/465),
on compte les envois de ce SASL_USERNAME via Redis sliding window.
Au-dela des seuils, on rejette REJECT 4.7.0 (temporaire, MUA réessayera).

Postfix main.cf :
  smtpd_data_restrictions = check_policy_service inet:127.0.0.1:10033

(check_policy_service au stage DATA car sasl_username est connu a ce moment)

Variables d'env :
  RATE_PER_MIN     = 30   (defaut)
  RATE_PER_HOUR    = 300
  RATE_PER_DAY     = 1000
  REDIS_URL        = redis://127.0.0.1:6379/0
  RATE_PORT        = 10033

Le compteur est par recipient envoyé : 1 mail à 10 dests = 10 unités.
"""
import os
import sys
import time
import socketserver
import redis

PORT       = int(os.environ.get('RATE_PORT', 10033))
PER_MIN    = int(os.environ.get('RATE_PER_MIN', 30))
PER_HOUR   = int(os.environ.get('RATE_PER_HOUR', 300))
PER_DAY    = int(os.environ.get('RATE_PER_DAY', 1000))
REDIS_URL  = os.environ.get('REDIS_URL', 'redis://127.0.0.1:6379/0')

r = redis.from_url(REDIS_URL, decode_responses=True)


def check_and_increment(user: str) -> tuple[bool, str]:
    """Retourne (allowed, reason)."""
    now = int(time.time())
    # Sliding windows en sorted sets : score = timestamp
    keys = [
        (f'rl:{user}:min',  60,    PER_MIN,  '1 minute'),
        (f'rl:{user}:hour', 3600,  PER_HOUR, '1 heure'),
        (f'rl:{user}:day',  86400, PER_DAY,  '24 heures'),
    ]
    pipe = r.pipeline()
    for key, window, _, _ in keys:
        pipe.zremrangebyscore(key, 0, now - window)
        pipe.zcard(key)
    res = pipe.execute()
    counts = res[1::2]  # zcard returns at indices 1,3,5
    for (key, window, limit, label), count in zip(keys, counts):
        if count >= limit:
            return False, f'{limit} mails/{label}'

    # Sous toutes les limites → enregistre l'envoi
    pipe = r.pipeline()
    for key, window, _, _ in keys:
        pipe.zadd(key, {f'{now}-{os.urandom(2).hex()}': now})
        pipe.expire(key, window)
    pipe.execute()
    return True, ''


def check_policy(attrs: dict) -> str:
    user = (attrs.get('sasl_username') or '').strip().lower()
    if not user:
        # Pas d'auth SASL → on ne touche pas (mails entrants ou mynetworks)
        return 'action=DUNNO'
    allowed, reason = check_and_increment(user)
    if allowed:
        return 'action=DUNNO'
    return (f'action=DEFER_IF_PERMIT 4.7.0 Vous avez depasse votre quota d\'envoi '
            f'({reason}). Reessayez plus tard.')


class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        attrs = {}
        while True:
            raw = self.rfile.readline()
            if not raw:                            # EOF = client a fermé socket
                break
            line = raw.decode('utf-8', errors='replace').rstrip('\r\n')
            if line == '':                         # ligne vide = fin de requete Postfix
                if attrs:
                    result = check_policy(attrs)
                    self.wfile.write((result + '\n\n').encode())
                    self.wfile.flush()
                    attrs = {}
                continue
            if '=' in line:
                k, v = line.split('=', 1)
                attrs[k] = v


class TServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == '__main__':
    server = TServer(('127.0.0.1', PORT), Handler)
    sys.stderr.write(f'[rate-limit] listening on :{PORT} (per_min={PER_MIN} per_hour={PER_HOUR} per_day={PER_DAY})\n')
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        server.shutdown()
