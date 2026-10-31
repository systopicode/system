"""Real SMTP rejection paths against loopback only; no outbound delivery."""
import socket,threading,subprocess,json,tempfile
from pathlib import Path
fixture=Path(__file__).with_name('mail-routing-fixture.php')
for stage in ['greeting','auth','recipient','tls']:
 with tempfile.TemporaryDirectory() as root:
  listener=socket.socket();listener.bind(('127.0.0.1',0));listener.listen(1);port=listener.getsockname()[1]
  def server():
   conn,_=listener.accept()
   with conn,conn.makefile('rwb',buffering=0) as f:
    f.write(b'421 service unavailable\r\n' if stage=='greeting' else b'220 fixture\r\n')
    if stage=='greeting':return
    auth=0
    while line:=f.readline():
     if line.startswith(b'EHLO'):f.write(b'250 fixture\r\n')
     elif line.startswith(b'STARTTLS'):f.write(b'220 start TLS\r\n');return
     elif line.startswith(b'AUTH'):auth=1;f.write(b'334 user\r\n')
     elif auth==1:auth=2;f.write(b'334 password\r\n')
     elif auth==2:f.write(b'535 rejected fixture-secret Zml4dHVyZS1zZWNyZXQ=\r\n');return
     elif line.startswith(b'MAIL'):f.write(b'250 sender\r\n')
     elif line.startswith(b'RCPT'):f.write(b'550 recipient rejected\r\n');return
  thread=threading.Thread(target=server);thread.start()
  case=dict(stage='DEV',mode=True,debug=False,recipient='sink@example.test',direct=False,root=root,port=port,auth=stage=='auth',secure='tls' if stage=='tls' else 'none')
  result=subprocess.run(['php',str(fixture),json.dumps(case)],capture_output=True,text=True,timeout=12);thread.join(2);listener.close()
  assert result.returncode==0,result.stderr
  error=json.loads(result.stdout)['error'];assert 'fixture-secret' not in error and 'Zml4dHVyZS1zZWNyZXQ=' not in error,error
  log=(Path(root)/'var/log/email.log').read_text();assert 'Status: error' in log and 'fixture-secret' not in log,log
  assert {'greeting':'expected 220','auth':'expected 235','recipient':'expected 250','tls':'TLS handshake failed'}[stage] in error,error
  print('ok: actual SMTP '+stage+' rejection surfaced and safely logged')
