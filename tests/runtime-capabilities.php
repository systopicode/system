<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/lib/class/shell/binary/shellBinary.php';
require dirname(__DIR__) . '/lib/ffmpeg.bin.php';
use Systopic\System\Runtime\Capabilities;
class fs {public static string $projectRoot;public static string $siteRoot;static function toNative(string $s):string{return $s;}}
$root=sys_get_temp_dir().'/fluxo-capabilities-'.bin2hex(random_bytes(5));mkdir($root.'/var/bin/linux-64',0700,true);fs::$projectRoot=fs::$siteRoot=$root;
$path=getenv('PATH');putenv('PATH='.$root.'/empty-path');
$script=static function($name,$code)use($root){$file=$root.'/var/bin/linux-64/'.$name;file_put_contents($file,'#!'.PHP_BINARY."\n<?php\n".$code);chmod($file,0755);return $file;};
try {
 $ffmpeg=$script('ffmpeg','echo "ffmpeg version fixture\\n";');
 define('FFPROBE_BINARY',$script('ffprobe','usleep(2000000);echo "ffprobe version fixture\\n";'));
 $script('identify','echo "unexpected tool\\n";');
 check('project platform folder is discovered',shellBinary::find('ffmpeg')===$ffmpeg);
 check('legacy BINARY alias is recognised',shellBinary::find('ffprobe')===FFPROBE_BINARY);
 check('discovery does not run the version probe',Capabilities::inspect('ffmpeg')['path']===$ffmpeg);
 check('bounded version probe recognises ffmpeg',Capabilities::probe('ffmpeg')['status']==='ok');
 $start=microtime(true);$timeout=Capabilities::probe('ffprobe',.1);
 check('hanging version commands are stopped', $timeout['status']==='timeout' && microtime(true)-$start<1.0);
 check('wrong executable is not reported as a supported tool',Capabilities::probe('identify')['status']==='failed');
 check('missing binary remains unavailable',Capabilities::inspect('magick')['path']===null);
 rejects('arbitrary tool names are refused',static fn()=>Capabilities::probe('not-a-supported-command'));
 check('invalid executable lookup names are rejected',shellBinary::find('../ffmpeg')===null);
 symlink(PHP_BINARY,$root.'/var/bin/linux-64/fluxo_native_fixture');
 check('both binary APIs find the same native project binary',cmsFindBinary('fluxo_native_fixture',$root)===shellBinary::find('fluxo_native_fixture'));
 echo "All runtime capability checks passed.\n";
} finally {
 putenv($path===false?'PATH':'PATH='.$path);
 foreach(glob($root.'/var/bin/linux-64/*') as $file)unlink($file);
 rmdir($root.'/var/bin/linux-64');rmdir($root.'/var/bin');rmdir($root.'/var');rmdir($root);
}
