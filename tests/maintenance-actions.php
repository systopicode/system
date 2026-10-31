<?php

declare(strict_types=1);
namespace Systopic\System\Auth { class Session {public static bool $super=false;public static function isSuperuser():bool{return self::$super;}} }
namespace {
require __DIR__ . '/bootstrap.php';
use Systopic\System\Maintenance\Runner;
class fs {public static string $projectRoot;static function toNative(string $s):string{return $s;}}
class http {public static string $method='POST';static function dataset(string $name){return $name==='job'?'probe':null;}}
class message {public static array $errors=[];static function error($s){self::$errors[]=$s;}static function confirm($s){}static function warning($s){}}
$root=sys_get_temp_dir().'/fluxo-maintenance-actions-'.bin2hex(random_bytes(5));mkdir($root);fs::$projectRoot=$root;
$count=0;Runner::register('probe','Probe','Test',static function(bool $preview)use(&$count):array{if(!$preview)$count++;return ['count'=>1];});
$panel=new class {public string $action='run';public ?string $webcronKey=null;public ?array $cleanupPreview=null;public bool $forced=false;function updateView($view,$force=false){$this->forced=$force;}function invoke(){include dirname(__DIR__).'/panels/cms/090_tools/040_cleanup/040_automatic/actions.php';}};
try{
 $panel->invoke();check('non-superuser cannot start cleanup', $count===0 && message::$errors!==[]);
 \Systopic\System\Auth\Session::$super=true;http::$method='GET';$panel->invoke();check('GET cannot start cleanup', $count===0);
 http::$method='PUT';$panel->action='previewCleanup';$panel->invoke();check('AJAX PUT preview is non-mutating and returns a visible result', $count===0 && $panel->cleanupPreview['results']['probe']['count']===1);
 check('preview forces an AJAX view response', $panel->forced);
 http::$method='POST';
 $panel->action='run';$panel->invoke();check('authorised POST runs one batch', $count===1);
 $panel->action='rotateWebcron';$panel->invoke();check('key shown through transient field', strlen($panel->webcronKey??'')===64 && \Systopic\System\Maintenance\Webcron::accepts($panel->webcronKey));
 \Systopic\System\Auth\Session::$super=false;$panel->action='disableWebcron';$panel->invoke();check('non-superuser cannot revoke webcron', \Systopic\System\Maintenance\Webcron::enabled());
}finally{$remove=static function($dir)use(&$remove){foreach(scandir($dir)as $name){if($name==='.'||$name==='..')continue;$p=$dir.'/'.$name;is_dir($p)?$remove($p):unlink($p);}rmdir($dir);};$remove($root);}
}
