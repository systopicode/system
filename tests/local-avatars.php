<?php
/** Real local renderer and filesystem integration; never bootstraps project data. */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require getenv('FLUXO_DICEBEAR_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
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
 $screenOptions=LocalAvatar::options(LocalAvatar::seed(2,null,true),true,$colors);
 check('screens select simple eyes, mouths and non-glowing tops through DiceBear 10 options', $screenOptions['eyesVariant']===['happy','round'] && $screenOptions['mouthVariant']===['smile01'] && !array_intersect(['glowingBulb01','glowingBulb02','lights'],$screenOptions['topVariant']));
 check('screens disable decorative texture', $screenOptions['textureProbability']===0);
 check('screen shell uses the derived navigation colour', $screenOptions['baseColor']===['b8c9d1']);
 foreach ([
  ['ink'=>'203c48','paper'=>'ffbb00','background'=>'f5e3a9'],
  ['ink'=>'f4f4f4','paper'=>'7545b5','background'=>'25252b'],
  ['ink'=>'143323','paper'=>'28ac70','background'=>'d8eadf'],
  ['ink'=>'777777','paper'=>'777777','background'=>'777777'],
  ['ink'=>'ffffff','paper'=>'ffffff','background'=>'ffffff'],
  ['ink'=>'000000','paper'=>'000000','background'=>'000000'],
 ] as $palette) {
  $selected=LocalAvatar::options($seed,true,$palette);
  check('screen backdrop keeps the existing light tint', $selected['backgroundColor']===[$palette['background']]);
  check('theme variant renders a valid local SVG',LocalAvatar::validSvg(LocalAvatar::render($seed,true,$palette)));
 }

 $personOptions=LocalAvatar::options($seed,false,$colors);
 check('people use a modest scale and upward offset', $personOptions['scale']===[1.12] && $personOptions['translateY']===[-2]);
 check('person SVG scales by a factor rather than a percentage', str_contains($svg,'scale(1.12)') && !str_contains($svg,'scale(112)'));
 $robotXml=new DOMDocument();$robotXml->loadXML($robot);$robotXpath=new DOMXPath($robotXml);
 check('screen face colours contain no unthemed black or white outside masks', $robotXpath->query('//*[@fill="black" or @fill="white" or @stroke="black" or @stroke="white"][not(ancestor::*[local-name()="mask"])]')->length===0);
 check('screen luminance masks retain white coverage', $robotXpath->query('//*[local-name()="mask"]//*[@fill="white"]')->length>0);

 check('people do not inherit screen styling', !isset(LocalAvatar::options($seed,false,$colors)['eyesVariant']));

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
  $generated=Portrait::src(9,'upload@example.test');$stagedFile=$root.'/staged.webp';$image=imagecreatetruecolor(2,2);imagewebp($image,$stagedFile);
  $staging=new class{public bool $cleared=false;public function reset(){$this->cleared=true;}};
  fsUploader::$upload=['tmp_name'=>$stagedFile,'name'=>'photo.webp','uploader'=>$staging];
  check('upload keeps existing WebP upload path and removes generated cache',Portrait::store(9)===null&&Portrait::hasFile(9)&&!is_file($path($generated))&&$staging->cleared);
  check('uploaded WebP remains preferred after an email change',str_contains(Portrait::src(9,'new-email@example.test'),'9.webp?v='));
 }
 $blocked=$root.'/unwritable';file_put_contents($blocked,'not a directory');fs::$siteRoot=$blocked.'/';
 check('storage failure keeps a local duotone fallback',str_starts_with(Portrait::src(73,'storage@example.test'),'data:image/svg+xml,'));fs::$siteRoot=$root.'/';
 $process=proc_open([PHP_BINARY,'-d','disable_functions=gzdecode',__FILE__,'--fallback-fixture'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$result=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 check('generation failure keeps the Phosphor fallback without PHP errors',$exit===0&&$errors===''&&str_starts_with(json_decode($result,true)['src']??'','data:image/svg+xml,'));
} finally {
 $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($files as$f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($root);
}
