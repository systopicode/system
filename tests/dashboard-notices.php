<?php
namespace Systopic\System\Auth {
    class Session { public static bool $superuser = true; static function isSuperuser():bool{return self::$superuser;} }
}
namespace {
require __DIR__.'/bootstrap.php';
use Systopic\System\Auth\Session;
use Systopic\System\Maintenance\Runner;
class fs {public static string $projectRoot;static function toNative(string $s):string{return $s;}}
class http {public static string $root='/project/';}
$dir=sys_get_temp_dir().'/fluxo-dashboard-notices-'.bin2hex(random_bytes(5));mkdir($dir.'/var',0700,true);fs::$projectRoot=$dir;
$render=static function(){ob_start();include dirname(__DIR__).'/panels/cms/home.notices.tpl.php';return ob_get_clean();};
try {
    Session::$superuser=false;
    check('ordinary users receive no administrative notices',trim($render())==='');
    Session::$superuser=true;
    $missing=$render();
    check('operator hint appears without requiring a visible widget',str_contains($missing,'operator details are not entered')&&!str_contains($missing,'data-widget'));
    check('notice has no dismissal control',!str_contains($missing,'dashHide')&&!str_contains($missing,'data-on_click'));
    define('CMS_OPERATOR_DETAILS','Example operator');
    check('notice disappears when details are supplied and cleanup is healthy',trim($render())==='');
    $fail=true;
    Runner::register('failure','Test task','Test',static function()use(&$fail){if($fail)throw new \RuntimeException('fixture');return ['count'=>0,'more'=>false];});
    Runner::run('manual','failure');
    check('cleanup failure produces an actionable persistent notice',str_contains($render(),'Review cleanup'));
    $fail=false;
    Runner::run('manual','failure');
    check('resolved cleanup failure removes the notice',trim($render())==='');
    $home=file_get_contents(dirname(__DIR__).'/panels/cms/home.tpl.php');
    check('notices are rendered before independently hideable widget list',strpos($home,"include __DIR__ . '/home.notices.tpl.php'")<strpos($home,'<div class="widgets dashWidgets'));
} finally {
    foreach(glob($dir.'/var/maintenance/*')?:[] as $file)unlink($file);
    if(is_dir($dir.'/var/maintenance'))rmdir($dir.'/var/maintenance');rmdir($dir.'/var');rmdir($dir);
}
}
