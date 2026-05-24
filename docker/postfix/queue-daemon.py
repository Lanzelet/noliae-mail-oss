#!/usr/bin/env python3
"""
Noliae Mail OSS — daemon TCP qui expose les commandes Postfix queue.

Bind sur 127.0.0.1:10032 (ou autre via env PORT). Le web container
appelle via le bridge docker pour gérer la quarantaine spam.

Protocole : JSON line-delimited.
  Request  : {"action": "hold-list" | "release" | "delete" | "show", "queue_id": "..."}
  Response : {"ok": bool, "data": ... } puis newline

Sécurité : token shared via env QUEUE_TOKEN. Le client doit envoyer
{"token": "..."} avec chaque requête.
"""
import os
import sys
import json
import subprocess
import socketserver

PORT  = int(os.environ.get('PORT', 10032))
TOKEN = os.environ.get('QUEUE_TOKEN', '')

def run(cmd):
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
        return r.returncode, r.stdout, r.stderr
    except Exception as e:
        return 1, '', str(e)

def hold_list():
    code, out, _ = run(['postqueue', '-j'])
    if code != 0: return []
    rows = []
    for line in out.splitlines():
        if not line.strip(): continue
        try:
            j = json.loads(line)
        except Exception:
            continue
        if j.get('queue_name') != 'hold': continue
        rows.append({
            'queue_id': j.get('queue_id', ''),
            'arrival_time': j.get('arrival_time'),
            'sender': j.get('sender', ''),
            'recipients': [r.get('address', '') for r in j.get('recipients', [])],
            'size': j.get('message_size', 0),
        })
    rows.sort(key=lambda x: x.get('arrival_time', 0), reverse=True)
    return rows

def release(qid):
    a, _, _ = run(['postsuper', '-H', qid])
    b, _, _ = run(['postqueue', '-i', qid])
    return a == 0 and b == 0

def delete(qid):
    code, _, _ = run(['postsuper', '-d', qid])
    return code == 0

def show(qid):
    code, out, _ = run(['postcat', '-q', qid])
    return out if code == 0 else None

def handle(req):
    if req.get('token') != TOKEN:
        return {'ok': False, 'error': 'unauthorized'}
    action = req.get('action')
    qid = (req.get('queue_id') or '').strip()
    # Valide queue_id format : alphanumeric hex usually
    if qid and not all(c.isalnum() for c in qid):
        return {'ok': False, 'error': 'invalid queue_id'}

    if action == 'hold-list':
        return {'ok': True, 'data': hold_list()}
    if action == 'release':
        return {'ok': release(qid)}
    if action == 'release-all':
        a, _, _ = run(['postsuper', '-H', 'ALL'])
        b, _, _ = run(['postqueue', '-f'])
        return {'ok': a == 0 and b == 0}
    if action == 'delete':
        return {'ok': delete(qid)}
    if action == 'show':
        out = show(qid)
        return {'ok': out is not None, 'data': out}
    return {'ok': False, 'error': 'unknown action'}


class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        try:
            line = self.rfile.readline().decode('utf-8', errors='replace')
            if not line.strip():
                return
            req = json.loads(line)
            resp = handle(req)
            self.wfile.write((json.dumps(resp) + '\n').encode())
        except Exception as e:
            self.wfile.write((json.dumps({'ok': False, 'error': str(e)}) + '\n').encode())


class TServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == '__main__':
    if not TOKEN:
        sys.stderr.write('FATAL: QUEUE_TOKEN env required\n')
        sys.exit(1)
    # BIND_ADDR defaut 127.0.0.1 ; passer a 172.18.0.1 (bridge docker) UNIQUEMENT
    # via env BIND_ADDR si le web container est dans un autre namespace réseau.
    # Ne JAMAIS exposer publiquement (le token n'est qu'un garde-fou secondaire).
    bind_addr = os.environ.get('BIND_ADDR', '127.0.0.1')
    server = TServer((bind_addr, PORT), Handler)
    sys.stderr.write(f'[queue-daemon] listening on {bind_addr}:{PORT}\n')
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        server.shutdown()
