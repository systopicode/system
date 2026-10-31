<?php
/** Real local renderer and filesystem integration; never bootstraps project data. */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Auth\LocalAvatar;
use Systopic\System\Auth\Portrait;
class fs {public static string $siteRoot;public static function toNative($p){return $p;}}
class http {public static string $siteUrl='https://fixture.test/public/';}
class fsUploader {public static array $upload=[];public static function postedFile($name){return self::$upload;}}
$root=sys_get_temp_dir().'/fluxo-avatar-check-'.bin2hex(random_bytes(6));mkdir($root);fs::$siteRoot=$root.'/';
$colors=['ink'=>'203c48','paper'=>'ffbb00','background'=>'eef2f4'];
(new ReflectionProperty(Portrait::class,'colors'))->setValue(null,$colors);
$child=($argv[1]??'')==='--fallback-fixture';
$path=static fn(string $url)=>$root.'/var/avatars/'.basename(parse_url($url,PHP_URL_PATH));
try {
 if($child){echo json_encode(['src'=>Portrait::src(71,'offline@example.test')]);return;}
 $seed=LocalAvatar::seed(1,' Same@Example.test ',false);
 check('normalised email gives stable SHA-256 seed',$seed===LocalAvatar::seed(99,'same@example.test',false));
 $svg=LocalAvatar::render($seed,false,$colors);$robot=LocalAvatar::render(LocalAvatar::seed(2,null,true),true,$colors);
 check('both styles produce local SVGs',LocalAvatar::validSvg($svg)&&LocalAvatar::validSvg($robot)&&$svg!==$robot);
 check('SVG output carries artwork credits',str_contains($svg,'Zoish')&&str_contains($robot,'Pablo Stanley'));
 check('raw email and seed are not embedded in SVG',!str_contains($svg,'same@example.test')&&!str_contains($svg,$seed));
 check('repeat renders are byte-identical',$svg===LocalAvatar::render($seed,false,$colors));
 rejects('arbitrary seed is not accepted',fn()=>LocalAvatar::render('email@example.test',false,$colors));
 rejects('arbitrary options/colour data is not accepted',fn()=>LocalAvatar::render($seed,false,['ink'=>'<script>','paper'=>'ffbb00','background'=>'eef2f4']));
 check('reordered colours preserve cache identity',LocalAvatar::fingerprint($seed,false,$colors)===LocalAvatar::fingerprint($seed,false,['background'=>'eef2f4','paper'=>'ffbb00','ink'=>'203c48']));
 check('invalid and entity-bearing SVGs are rejected',!LocalAvatar::validSvg('<svg>broken')&&!LocalAvatar::validSvg('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'));
 $url=Portrait::src(7,'same@example.test');$file=$path($url);
 check('generated SVG uses the real avatar folder',is_file($file)&&str_contains($url,'/var/avatars/7.generated-')&&str_ends_with($url,'.svg'));
 check('generated avatar is not treated as an uploaded photo',!Portrait::hasFile(7));
 touch($file,time()-200);clearstatcache();$mtime=filemtime($file);
 check('cached avatar is reused without rewriting',Portrait::src(7,'same@example.test')===$url&&filemtime($file)===$mtime);
 $other=Portrait::src(8,'same@example.test');check('same email across accounts has the same SVG contents',file_get_contents($path($other))===file_get_contents($file));
 $new=Portrait::src(7,'changed@example.test');check('email change creates new avatar and clears old cache',$new!==$url&&is_file($path($new))&&!file_exists($file));
 file_put_contents($path($new),'broken');check('damaged cache regenerates safely',Portrait::src(7,'changed@example.test')===$new&&LocalAvatar::validSvg(file_get_contents($path($new))));
 file_put_contents($root.'/var/avatars/7.png','test upload');check('own upload takes precedence',str_contains(Portrait::src(7,'changed@example.test'),'7.png?v=')&&Portrait::hasFile(7));
 Portrait::remove(7);check('removal clears uploaded and generated files',!Portrait::hasFile(7)&&glob($root.'/var/avatars/7.*')===[]);
 if(function_exists('imagewebp')) {
  $generated=Portrait::src(9,'upload@example.test');$stagedFile=$root.'/staged.webp';$image=imagecreatetruecolor(2,2);imagewebp($image,$stagedFile);imagedestroy($image);
  $staging=new class{public bool $cleared=false;public function reset(){$this->cleared=true;}};
  fsUploader::$upload=['tmp_name'=>$stagedFile,'name'=>'photo.webp','uploader'=>$staging];
  check('upload keeps existing WebP upload path and removes generated cache',Portrait::store(9)===null&&Portrait::hasFile(9)&&!is_file($path($generated))&&$staging->cleared);
  check('uploaded WebP remains preferred after an email change',str_contains(Portrait::src(9,'new-email@example.test'),'9.webp?v='));
 }
 $blocked=$root.'/unwritable';file_put_contents($blocked,'not a directory');fs::$siteRoot=$blocked.'/';
 check('storage failure keeps a local duotone fallback',str_starts_with(Portrait::src(73,'storage@example.test'),'data:image/svg+xml,'));fs::$siteRoot=$root.'/';
 $process=proc_open([PHP_BINARY,'-d','disable_functions=gzdecode',__FILE__,'--fallback-fixture'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$result=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 check('generation failure keeps the Solar fallback without PHP errors',$exit===0&&$errors===''&&str_starts_with(json_decode($result,true)['src']??'','data:image/svg+xml,'));
 $reference=getenv('FLUXO_DICEBEAR_REFERENCE');
 if($reference){
  require $reference.'/vendor/autoload.php';
  $cases=0;
  foreach([false,true] as $screen){$name=$screen?'bottts':'notionists';$raw=file_get_contents(dirname($reference).'/'.$name.'.json');$style=\DiceBear\Style::fromJson($raw);
   foreach([$colors,['ink'=>'f2f3f4','paper'=>'66b5ff','background'=>'172024']]as$palette)foreach(range(1,12)as$i){
    $s=LocalAvatar::seed($i,'reference-'.$i.'@example.test',$screen);
    $expected=(string)new \DiceBear\Avatar($style,LocalAvatar::options($s,$screen,$palette));
    check('upstream SVG equivalence '.$name.' '.(++$cases),$expected===LocalAvatar::render($s,$screen,$palette));
   }
  }
 }else echo "SKIP: upstream comparison requires FLUXO_DICEBEAR_REFERENCE.\n";
} finally {
 $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($files as$f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($root);
}
