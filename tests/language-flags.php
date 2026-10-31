<?php
namespace Systopic\System\Http { class Assets { public static function href(string $href,string $path):string{return $href.'?t='.filemtime($path);} } }
namespace {
$sys=dirname(__DIR__);require $sys.'/tests/bootstrap.php';
class fs {public static string $siteRoot;public static string $sysRoot;public static function toNative(string $p):string{return $p;}public static function toInternal(string $p):string{return $p;}}
class httpHelper {public static function filepathToHref(string $p):string{return str_starts_with($p,fs::$siteRoot)?'/project/'.substr($p,strlen(fs::$siteRoot)):'/assets/'.substr($p,strlen(fs::$sysRoot.'public/'));}}
use Systopic\System\Settings\LanguageFlags as F;
$dir=sys_get_temp_dir().'/fluxo-flags-unit-'.bin2hex(random_bytes(5));mkdir($dir);fs::$siteRoot=$dir.'/';fs::$sysRoot=$sys.'/';
try {
 foreach(glob($sys.'/public/img/flags/*.svg') as $path)check('shipped flag is safe: '.basename($path),F::cleanSvg(file_get_contents($path))!==null);
 foreach(['en'=>'gb','ja'=>'jp','sv'=>'se','de'=>'de'] as $code=>$asset)check('local alias '.$code,str_contains(F::href($code),'/'.$asset.'.svg?'));
 check('unknown flag has no external fallback',F::href('zz')===''&&!str_contains(F::html('zz'),'<img'));
 foreach(['../../bad','https://example.org/x','de/../x','de.svg','<svg>'] as $code)check('bad code rejected',F::code($code)===null);
 $good='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 3"><rect width="4" height="3" fill="#123456"/></svg>';
 check('project SVG saved',F::save('en',$good));$href=F::href('en');check('project code overrides alias',str_contains($href,'/project/overrides/img/flags/en.svg'));
 check('rapid replacement succeeds',F::save('en',str_replace('#123456','#654321',$good)));check('content version changes even in same second',F::href('en')!==$href);
 $before=file_get_contents($dir.'/overrides/img/flags/en.svg');
 foreach([
 '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
 '<svg><script>alert(1)</script></svg>', '<svg><foreignObject/></svg>',
 '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg><title>&x;</title></svg>',
 '<?xml-stylesheet href="https://example.org/x"?><svg/>',
 '<svg><image href="https://example.org/x"/></svg>',
 '<svg><use href="https://example.org/x"/></svg>',
 '<svg><path fill="url(https://example.org/x)"/></svg>',
 '<svg><path style="fill:url(&#104;ttps://example.org/x)"/></svg>',
 '<svg><style>@import "https://example.org/x";</style></svg>',
 '<svg><animate attributeName="href"/></svg>',
 str_repeat('x',F::MAX_BYTES+1), '<svg><path></svg>'
 ] as $bad){check('unsafe/malformed SVG rejected',!F::save('en',$bad));check('rejection preserves previous flag',file_get_contents($dir.'/overrides/img/flags/en.svg')===$before);}
 check('safe local gradient reference accepted',F::cleanSvg('<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="a"><stop offset="0" stop-color="#fff"/></linearGradient></defs><path d="M0 0h1v1z" fill="url(#a)"/></svg>')!==null);
 check('reset project flag',F::reset('en'));check('reset restores local alias',str_contains(F::href('en'),'/gb.svg?'));
 check('reset cannot remove system flag',!F::reset('de')&&is_file($sys.'/public/img/flags/de.svg'));
 $config=$dir.'/global.settings.json';
 $data=['languages'=>['DEFAULT_LANG'=>'en','LANGUAGES'=>['en'=>['label'=>'English','code'=>'en','activated'=>true]]],'custom'=>['keep'=>true]];
 \Systopic\System\Settings\ProjectJson::put($config,$data);
 check('country-code editor uses existing settings writer',\Systopic\System\Settings\ProjectFile::setField($config,'define:LANGUAGES','en','code','us')===true);
 $changed=json_decode(file_get_contents($config),true);$data['languages']['LANGUAGES']['en']['code']='us';
 check('country-code edit preserves language key, label, activation and other settings',$changed===$data);
 ob_start();
 F::preview('en','English','en');F::control('en','English','en');
 F::preview('en','English regional','en-GB');F::control('en','English regional','en-GB');
 $markup=ob_get_clean();$doc=new DOMDocument();@$doc->loadHTML($markup);$ids=[];$targets=[];
 foreach($doc->getElementsByTagName('input') as $input) if($input->getAttribute('type')==='file') { $ids[]=$input->getAttribute('id');check('upload input has explicit tooltip',$input->getAttribute('title')!==''&&$input->getAttribute('accept')==='.svg,image/svg+xml'); }
 foreach($doc->getElementsByTagName('button') as $button) if($button->hasAttribute('data-flag-input'))$targets[]=$button->getAttribute('data-flag-input');
 check('preview targets its own SVG input',$ids===$targets&&count($ids)===2);
 check('shared flag codes retain unique row input IDs',count(array_unique($ids))===2);
 check('missing CSRF rejected',!F::checkToken(''));$token=F::token();check('session token accepted',F::checkToken($token));check('wrong CSRF rejected',!F::checkToken(str_repeat('a',64)));
} finally { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($dir); }
}
