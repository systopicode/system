<?php
require __DIR__ . '/bootstrap.php';
use Systopic\System\Auth\SignInLog;
class fs {public static string $root;static function toNative($s){return $s;}}
fs::$root=sys_get_temp_dir().'/fluxo-signins-'.bin2hex(random_bytes(4));mkdir(fs::$root.'/var/log',0777,true);
$old="01.11.2026-09:00:00 ; Test ; logged in ; 127.0.0.1 ; password\n";
$new="05.11.2026-09:00:00 ; Other ; signed in ; 127.0.0.1 ; code\n05.11.2026-09:01:00 ; Other ; signed out ; 127.0.0.1\n";
file_put_contents(fs::$root.'/var/log/login.log',$old);file_put_contents(fs::$root.'/var/log/signin.log',$new);
$rows=SignInLog::recentSignIns();check('both log names read in chronological order',count($rows)===2&&$rows[0][1]==='Other'&&$rows[1][1]==='Test');
check('legacy success wording normalized', $rows[1][2]==='signed in');
check('limit respected',count(SignInLog::recentSignIns(1))===1);
check('reading leaves both audit files byte-identical',file_get_contents(fs::$root.'/var/log/login.log')===$old&&file_get_contents(fs::$root.'/var/log/signin.log')===$new);
unlink(fs::$root.'/var/log/login.log');unlink(fs::$root.'/var/log/signin.log');rmdir(fs::$root.'/var/log');rmdir(fs::$root.'/var');rmdir(fs::$root);
