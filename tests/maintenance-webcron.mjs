import {mkdtemp, writeFile, readFile, readdir, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawn} from 'node:child_process';
import net from 'node:net';
import assert from 'node:assert/strict';
const root=await mkdtemp(join(tmpdir(),'fluxo-webcron-'));
const phpQuote=s=>"'"+s.replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";
const bootstrap=fileURLToPath(new URL('./bootstrap.php',import.meta.url));
const router=join(root,'router.php');
await writeFile(router,`<?php
require ${phpQuote(bootstrap)};
class fs {public static string $projectRoot=${phpQuote(root)};static function toNative(string $s):string{return $s;}}
class http {public static string $root='/project/';public static string $protocol='https';}
$_SERVER['HTTPS']=isset($_GET['insecure'])?'off':'on';
use Systopic\\System\\Maintenance\\Runner;
use Systopic\\System\\Maintenance\\Webcron;
Runner::register('probe','Probe','Test only',static function(bool $preview):array {
 $file=fs::$projectRoot.'/counter';$n=is_file($file)?(int)file_get_contents($file):0;
 if(!$preview)file_put_contents($file,(string)($n+1));return ['count'=>0];
});
if(PHP_SAPI==='cli') {
 if(($argv[1]??'')==='reset-status') {foreach(glob(Runner::directory().'/state-*.json')?:[] as $file) unlink($file);exit;}
 if(($argv[1]??'')==='disable') {Webcron::disable();exit;}
 echo Webcron::rotate();exit;
}
Runner::boot();
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/project/fatal/') fluxo_test_missing_function();
http_response_code(404);echo '{}';
`);
const command=(args)=>new Promise((res,rej)=>{const child=spawn('php',args,{stdio:['ignore','pipe','pipe']});let out='',err='';child.stdout.on('data',d=>out+=d);child.stderr.on('data',d=>err+=d);child.on('error',rej);child.on('close',code=>code?rej(Error(err)):res(out.trim()));});
const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));
const server=spawn('php',['-S',`127.0.0.1:${port}`,router],{stdio:['ignore','ignore','pipe']});let errors='';server.stderr.on('data',d=>errors+=d);
const endpoint=`http://127.0.0.1:${port}/project/_maintenance/`;
try {
 for(let i=0;i<50;i++){try{await fetch(endpoint);break;}catch{await new Promise(r=>setTimeout(r,50));}}
 assert.equal((await fetch(endpoint)).status,403,'GET cannot run maintenance');
 assert.equal((await fetch(endpoint,{method:'POST'})).status,403,'disabled endpoint refuses POST');
 const key=await command([router]);
 assert.equal((await fetch(endpoint,{method:'POST',headers:{'X-Maintenance-Key':'bad'}})).status,403);
 assert.equal((await fetch(endpoint+'?insecure=1',{method:'POST',headers:{'X-Maintenance-Key':key}})).status,403,'plain HTTP refused');
 const valid=await fetch(endpoint,{method:'POST',headers:{'X-Maintenance-Key':key}});
 assert.equal(valid.status,200);const result=await valid.json();assert.equal(result.results.probe.ok,true);
 assert.equal(await readFile(join(root,'counter'),'utf8'),'1');
 assert.equal((await fetch(endpoint,{method:'POST',headers:{'X-Maintenance-Key':key}})).status,429,'immediate repeated run throttled');
 const next=await command([router]);
 assert.equal((await fetch(endpoint,{method:'POST',headers:{'X-Maintenance-Key':key}})).status,403,'old key revoked');
 await command([router,'disable']);
 assert.equal((await fetch(endpoint,{method:'POST',headers:{'X-Maintenance-Key':next}})).status,403,'disabled key revoked');
 await command([router,'reset-status']);
 const normal=`http://127.0.0.1:${port}/project/page/`;
 await (await fetch(normal,{method:'POST'})).text();
 assert.equal(await readFile(join(root,'counter'),'utf8'),'1','POST does not trigger request cleanup');
 await (await fetch(`http://127.0.0.1:${port}/project/fatal/`)).text();
 assert.equal(await readFile(join(root,'counter'),'utf8'),'1','fatal request does not trigger cleanup');
 await (await fetch(normal)).text();
 assert.equal(await readFile(join(root,'counter'),'utf8'),'2','GET performs a shutdown batch');
 await (await fetch(normal)).text();
 assert.equal(await readFile(join(root,'counter'),'utf8'),'2','immediate GET is throttled');
 const stateFile=(await readdir(join(root,'var/maintenance'))).find(n=>n.startsWith('state-'));
 const state=JSON.parse(await readFile(join(root,'var/maintenance',stateFile),'utf8'));
 assert.ok(state.runs.request.ok);assert.equal(state.runs.cli,undefined,'request does not masquerade as CLI');
 console.log('ok: real HTTP request fallback runs only eligible GETs; skips POST/fatal requests and throttles repeats');
 console.log('ok: real HTTP endpoint enforces POST, key, HTTPS, rate limit, rotation and disable; valid run returns clean JSON');
} finally {server.kill();await new Promise(r=>server.once('exit',r));await rm(root,{recursive:true,force:true});}
