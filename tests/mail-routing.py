#!/usr/bin/env python3
"""Exercise the real SMTP implementation against a loopback-only sink. No real mail."""
import json, socket, subprocess, tempfile, threading
from email import message_from_string
from email.header import decode_header, make_header
from pathlib import Path
fixture = Path(__file__).with_name('mail-routing-fixture.php')
def run(stage, mode=False, debug=False, recipient='sink@example.test', direct=False):
    with tempfile.TemporaryDirectory() as root, socket.socket() as server:
        server.bind(('127.0.0.1', 0)); server.listen(); server.settimeout(1)
        captured = {'rcpt': [], 'data': ''}
        def sink():
            try: conn, _ = server.accept()
            except socket.timeout: return
            with conn:
                conn.settimeout(3)
                stream = conn.makefile('rb'); conn.sendall(b'220 fixture ready\r\n')
                data = False
                while True:
                    line = stream.readline()
                    if not line: break
                    if data:
                        if line.rstrip() == b'.': data = False; conn.sendall(b'250 accepted\r\n')
                        else: captured['data'] += line.decode(errors='replace')
                    elif line.startswith(b'DATA'): data = True; conn.sendall(b'354 send data\r\n')
                    elif line.startswith(b'QUIT'): conn.sendall(b'221 bye\r\n'); break
                    else:
                        if line.startswith(b'RCPT TO:'): captured['rcpt'].append(line.decode().strip())
                        conn.sendall(b'250 ok\r\n')
        thread = threading.Thread(target=sink); thread.start()
        case = dict(stage=stage, mode=mode, debug=debug, recipient=recipient, direct=direct, root=root, port=server.getsockname()[1])
        result = subprocess.run(['php', str(fixture), json.dumps(case)], capture_output=True, text=True, timeout=8)
        thread.join(4)
        assert result.returncode == 0, result.stderr
        outcome = json.loads(result.stdout)
        if recipient == 'invalid':
            assert 'invalid test recipient' in outcome.get('error','') and not captured['rcpt'], outcome
        else:
            assert outcome.get('ok') is True, outcome
            routed = stage.startswith('DEV') or mode
            expected = [f"RCPT TO: <{recipient or 'testmail@fluxo.systems'}>"] if routed else [f'RCPT TO: <real-{kind}@example.test>' for kind in ('to','cc','bcc')]
            assert captured['rcpt'] == expected, captured
            if routed:
                assert 'real-' not in captured['data'], captured
                assert str(make_header(decode_header(message_from_string(captured['data'])['Subject']))) == 'Test mail // Fixture', captured
            log = Path(root) / 'var/log/email.log'
            if log.exists(): assert 'FAKE_SECRET_FIXTURE' not in log.read_text()
        print(f'ok: {stage} mode={mode} debug={debug} direct={direct} recipient={recipient or "default"}')
for stage, mode, debug in [('DEV',False,False),('DEV',False,True),('DEV',True,False),('STAGING',False,False),('STAGING',True,False),('LIVE',False,True),('LIVE',True,False)]:
    run(stage, mode, debug)
run('DEV', direct=True)
run('DEV', recipient='')
run('DEV', recipient='invalid')
