<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\GroupPolicy as Policy;
use Systopic\System\Settings\ProjectFile;
require_once dirname(__DIR__) . '/src/Settings/ProjectFile.php';
require_once dirname(__DIR__) . '/lib/class/media/format/mediaFormats.php';
require_once dirname(__DIR__) . '/lib/class/media/image/mediaImage.php';
$groups=['images'=>['label'=>'Images','file_extension'=>'jpeg, png'],'all'=>['file_extension'=>''],'broken'=>['file_extension'=>'mov']];
check('canonical extensions match aliases',Policy::parse('JPEG; .PNG tiff jpg')===['jpg','png']);
check('picker includes JPEG alias',Policy::accept($groups,'images')==='.jpg,.png,.jpeg');
check('group accepts JPEG',Policy::error($groups,'images','photo.JPEG')===null);
check('disallowed file has group-specific feedback',str_contains(Policy::error($groups,'images','movie.mp4'),'Allowed: JPG / JPEG, PNG'));
check('unknown group is rejected',Policy::error($groups,'missing','a.jpg')!==null);
check('unsupported old types rejected even without group restriction',Policy::error($groups,'all','movie.mov')!==null);
check('unknown-only config cannot become unrestricted',Policy::allowed($groups,'broken')===[]);
foreach (['tif','tiff','wav','ogg','aac','webm','doc'] as $removed) check('removed upload type rejected: '.$removed, Policy::error($groups,'all','file.'.$removed)!==null);
check('raster share images supported',Policy::shareImageAllowed('photo.jpg','image/jpeg')&&Policy::shareImageAllowed('photo.png','image/png')&&Policy::shareImageAllowed('photo.webp','image/webp'));
check('SVG, PDF and spoofed image types rejected for share images',!Policy::shareImageAllowed('a.svg','image/svg+xml')&&!Policy::shareImageAllowed('a.pdf','application/pdf')&&!Policy::shareImageAllowed('a.jpg','video/mp4'));
$rules=file_get_contents(dirname(__DIR__).'/scaffold/public/.htaccess');
preg_match('~RewriteCond.*!\^\.\*\\\\\.\(([^\n]+)\)\$~',$rules,$m);
$pattern='~^(?:'.($m[1]??'').')$~i';
foreach(array_keys(Policy::TYPES) as $ext) check('public var serves '.$ext,(bool)preg_match($pattern,$ext));
$dir=sys_get_temp_dir().'/fluxo-media-policy-'.bin2hex(random_bytes(5));mkdir($dir);
try {
 file_put_contents($dir.'/upload',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6ZVQAAAAASUVORK5CYII='));
 check('PNG content accepted',Policy::contentError($dir.'/upload','a.png')===null);
 check('renaming contents does not bypass MIME check',Policy::contentError($dir.'/upload','a.jpg')!==null);
 file_put_contents($dir.'/plain', 'column,value' . "\n" . 'one,two');
 check('TXT and CSV content accepted',Policy::contentError($dir.'/plain','notes.txt')===null&&Policy::contentError($dir.'/plain','data.csv')===null);
 foreach (['docx'=>'word/document.xml','xlsx'=>'xl/workbook.xml','pptx'=>'ppt/presentation.xml'] as $ext=>$entry) {
  $zip=new ZipArchive();$zip->open($dir.'/office', ZipArchive::CREATE|ZipArchive::OVERWRITE);
  $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types/>');$zip->addFromString($entry,'<?xml version="1.0"?><document/>');$zip->close();
  check('Office container accepted: '.$ext,Policy::contentError($dir.'/office','sample.'.$ext)===null);
  $wrong=$ext==='docx'?'xlsx':'docx';check('renamed Office container rejected',Policy::contentError($dir.'/office','sample.'.$wrong)!==null);
 }
 check('JPEG storage name matches delivery rules',Policy::storageName('Photo.JPEG')==='Photo.jpg');
 mediaFormats::add(['testMP'=>['megapixels'=>2,'width'=>5,'height'=>5],'testRatio'=>['megapixels'=>3,'aspect_ratio'=>'16:9'],'testHeight'=>['height'=>900],'testWidth'=>['width'=>800]]);
 $GLOBALS['image_formats']=mediaFormats::get();
 [$w,$h]=mediaImage::getCanvasSize(4000,2000,'testMP');check('rendering obeys megapixels over dimensions',abs($w*$h-2000000)<1000);
 [$w,$h]=mediaImage::getCanvasSize(2000,4000,'testRatio');check('MP ratio sets output and budget regardless of source',abs($w/$h-16/9)<.001 && abs($w*$h-3000000)<1000);
 check('MP free ignores stale pixel ratio',mediaFormats::fixedRatio(['megapixels'=>2,'width'=>5,'height'=>5])===null);
 check('MP label includes ratio',mediaFormats::sizeLabel(['megapixels'=>3,'aspect_ratio'=>'16:9'])==='3 MP · 16:9');
 check('height-only render remains proportional',mediaImage::getCanvasSize(4000,2000,'testHeight')===[1800.0,900.0]);
 check('width-only render remains proportional',mediaImage::getCanvasSize(4000,2000,'testWidth')===[800.0,400.0]);
 check('labels use same precedence',mediaFormats::sizeLabel(['megapixels'=>2,'width'=>5,'height'=>5])==='2 MP');
} finally {foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
echo "All media policy and image sizing checks passed.\n";

check("format label falls back to key",mediaFormats::label("legacy",[])==="legacy");
check("format label displayed",mediaFormats::label("bg",["label"=>"Background"])==="Background");

mediaFormats::add(["labelTest"=>["label"=>"Background image","megapixels"=>2]]);
check("WYSIWYG format map uses label",str_starts_with(mediaFormats::editorImages()["labelTest"],"Background image ("));

mediaFormats::add(["zOrder"=>["label"=>"First","megapixels"=>1],"aOrder"=>["label"=>"Second","megapixels"=>1]]);
check("configured image order preserved",array_search("zOrder",array_keys(mediaFormats::editorImages())) < array_search("aOrder",array_keys(mediaFormats::editorImages())));
if(!defined("VIDEO_FORMATS"))define("VIDEO_FORMATS",["zClip"=>["label"=>"Preview","max_width"=>1280],"aClip"=>["max_width"=>640]]);
check("video labels and legacy fallback",mediaFormats::editorVideos()===["zClip"=>"Preview (1280 px)","aClip"=>"aClip (640 px)"]);
