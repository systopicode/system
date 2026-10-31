<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Backup\Preflight;
$dir=sys_get_temp_dir().'/fluxo-preflight-'.bin2hex(random_bytes(6));mkdir($dir);
$write=static function($name,$info,$entries=[])use($dir){$zip=new ZipArchive();$zip->open($dir.'/'.$name.'-0.zip',ZipArchive::CREATE);$zip->addFromString('info.txt',serialize($info));foreach($entries as $key=>$value)$zip->addFromString($key,$value);$zip->close();};
try {
 $info=['format'=>1,'taskList'=>[['action'=>'addMysqldump'],['action'=>'finishBackup']]];
 $write('valid',$info,['mysqldump_all.sql'=>'SELECT 1;']);
 check('valid archive passes read-only structure check',Preflight::inspect($dir,'valid',$dir.'/tmp')['errors']===[]);
 $write('missing',$info);
 check('missing database dump blocks restore',Preflight::inspect($dir,'missing',$dir.'/tmp')['errors']!==[]);
 $write('unsafe',$info,['mysqldump_all.sql'=>'x','../escape'=>'x']);
 check('unsafe archive path blocked',Preflight::inspect($dir,'unsafe',$dir.'/tmp')['errors']!==[]);
 $write('future',['format'=>2,'taskList'=>[]]);
 check('newer format blocked',Preflight::inspect($dir,'future',$dir.'/tmp')['errors']!==[]);
 file_put_contents($dir.'/bad-0.zip','not a ZIP');
 check('corrupt archive blocked',Preflight::inspect($dir,'bad',$dir.'/tmp')['errors']!==[]);
 copy($dir.'/valid-0.zip',$dir.'/valid-2.zip');
 check('missing middle part blocked',Preflight::inspect($dir,'valid',$dir.'/tmp')['errors']!==[]);
 check('no extraction occurred',!file_exists($dir.'/tmp')&&!file_exists(dirname($dir).'/escape'));
}finally{foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
echo "Backup preflight checks passed.\n";
