<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Maintenance\RetainedLogs as Logs;
class fs { public static string $root; public static string $siteRoot; }
$dir=sys_get_temp_dir().'/fluxo-techlog-'.bin2hex(random_bytes(5));
mkdir($dir.'/var/log',0700,true);mkdir($dir.'/var/backups');fs::$root=fs::$siteRoot=$dir;
try {
 foreach(['email','php','backup'] as $kind){
  $suffix=$kind==='email'?"JSON_DATA: {}\n".str_repeat('-',80)."\n":($kind==='backup'?"":"stack trace\n");
  $old='['.date('c',time()-91*86400)."] event\n".$suffix;
  $new='['.date('c')."] event\n".$suffix;
  $path=Logs::path($kind);file_put_contents($path,$old.$old.$new);
  $p=Logs::cleanup($kind,true,1,microtime(true)+2);
  check("$kind preview bounded and read-only",$p['count']===1 && $p['more'] && file_get_contents($path)===$old.$old.$new);
  Logs::cleanup($kind,false,1,microtime(true)+2);
  check("$kind removes complete record only",file_get_contents($path)===$old.$new);
  Logs::cleanup($kind,false,100,microtime(true)+2);
  check("$kind preserves recent records",file_get_contents($path)===$new);
  file_put_contents($path,"undated legacy\n".$new);
  rejects("$kind unknown dates fail closed",fn()=>Logs::cleanup($kind,false,100,microtime(true)+2));
  check("$kind failure preserves source",file_get_contents($path)==="undated legacy\n".$new);
 }
 check('relative date labels never qualify for deletion', Logs::timestamp('[yesterday] test', 'php')===null);
 check('invalid absolute dates are not normalised and deleted', Logs::timestamp('[2020-02-31 10:00:00] test', 'email')===null);
 $message=['recipients'=>['Alice <ALICE@example.org>'],'subject'=>'<script>','textContent'=>'<b>hello</b>'];
 $raw='['.date('c')."] event\nJSON_DATA: ".json_encode($message)."\n".str_repeat('-',80)."\n";
 file_put_contents(Logs::path('email'),$raw);
 check('exact recipient matching with case and display name',count(Logs::emailsFor('alice@example.org'))===1);
 check('different recipient not exposed',Logs::emailsFor('lice@example.org')===[]);
 check('empty address never exposes log',Logs::emailsFor('')===[]);
} finally {foreach(['email','php','backup'] as $kind)@unlink(Logs::path($kind));rmdir($dir.'/var/log');rmdir($dir.'/var/backups');rmdir($dir.'/var');rmdir($dir);}
