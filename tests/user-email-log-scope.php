<?php
declare(strict_types=1);
namespace Systopic\System\Auth { class Session {public static bool $super=false; public static function isSuperuser():bool{return self::$super;}} }
namespace {
require __DIR__.'/bootstrap.php';
use Systopic\System\Maintenance\RetainedLogs as Logs;
class fs {public static string $root;}
$dir=sys_get_temp_dir().'/fluxo-email-scope-'.bin2hex(random_bytes(5));mkdir($dir.'/var/log',0700,true);fs::$root=$dir;
$record=fn($email)=>'[2026-11-01T12:00:00+00:00] Mail'."\nJSON_DATA: ".json_encode(['recipients'=>[$email],'subject'=>'Fixture'])."\n".str_repeat('-',80)."\n";
$a=$record('Alice <ALICE@example.test>');$b=$record('bob@example.test');$unknown="legacy unrecognised\n";
try {
 file_put_contents(Logs::path('email'),$a.$b.$a.$unknown);
 rejects('non-superuser cannot clear user email logs',fn()=>Logs::clearEmailsFor('alice@example.test'));
 check('rejected clear preserves every byte',file_get_contents(Logs::path('email'))===$a.$b.$a.$unknown);
 \Systopic\System\Auth\Session::$super=true;
 check('exact recipient clear removes only matching records',Logs::clearEmailsFor('alice@example.test')===2);
 check('other users and unknown legacy data preserved',file_get_contents(Logs::path('email'))===$b.$unknown);
 check('empty recipient does not clear anything',Logs::clearEmailsFor('')===0);
 check('nonmatching substring does not clear anything',Logs::clearEmailsFor('ob@example.test')===0);
 check('other user remains readable',count(Logs::emailsFor('bob@example.test'))===1);
} finally {unlink(Logs::path('email'));rmdir($dir.'/var/log');rmdir($dir.'/var');rmdir($dir);}
}
