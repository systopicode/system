<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Systopic\System\Maintenance\LogRetention;
class fs {public static string $root;}
$dir=sys_get_temp_dir().'/fluxo-retention-'.bin2hex(random_bytes(5));mkdir($dir.'/var/log',0700,true);fs::$root=$dir;
$file=$dir.'/var/log/login.log';
$old=date('d.m.Y-H:i:s',time()-366*86400).' ; old ; sign-in ; 127.0.0.1 ; password' . "\n";
$fresh=date('d.m.Y-H:i:s').' ; active ; sign-in ; 127.0.0.1 ; passkey' . "\n";
try{
 file_put_contents($file,$old.$old.$fresh);
 $p=LogRetention::cleanupLogin(true,1,microtime(true)+2);
 check('retention preview is non-mutating and bounded',$p['count']===1 && $p['more'] && file_get_contents($file)===$old.$old.$fresh);
 $r=LogRetention::cleanupLogin(false,1,microtime(true)+2);
 check('retention removes only one expired entry per bounded batch',$r['count']===1 && $r['more'] && file_get_contents($file)===$old.$fresh);
 LogRetention::cleanupLogin(false,100,microtime(true)+2);
 check('valid recent entries survive cleanup',file_get_contents($file)===$fresh);
 file_put_contents($file,$old.$fresh);$lock=fopen($file,'r+');flock($lock,LOCK_EX);
 rejects('concurrent writer lock prevents cleanup',static fn()=>LogRetention::cleanupLogin(false,100,microtime(true)+2));
 flock($lock,LOCK_UN);fclose($lock);check('busy log remains unchanged',file_get_contents($file)===$old.$fresh);
 file_put_contents($file,"unrecognised historical record\n".$fresh);
 rejects('unknown log dates prevent destructive rewriting',static fn()=>LogRetention::cleanupLogin(false,100,microtime(true)+2));
 check('unrecognised log content is preserved',file_get_contents($file)==="unrecognised historical record\n".$fresh);
 file_put_contents($file,$old.$fresh);$r=LogRetention::cleanupLogin(false,100,microtime(true)-1);
 check('expired time budget leaves source unchanged',$r['count']===0 && $r['more'] && file_get_contents($file)===$old.$fresh);
}finally{unlink($file);rmdir($dir.'/var/log');rmdir($dir.'/var');rmdir($dir);}
