<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Systopic\System\Media\TypePolicy;
use Systopic\System\Tables\Medias\External;
foreach(TypePolicy::FILE_TYPES as $type) {
 check('normal user cannot manually select '.$type,!TypePolicy::mayChoose($type,false));
 check('superuser can manually select '.$type,TypePolicy::mayChoose($type,true));
}
check('external providers stay selectable',TypePolicy::mayChoose('youtube',false)&&TypePolicy::mayChoose('vimeo',false));
check('unknown type never allowed',!TypePolicy::mayChoose('malformed',true));
check('spoofed upload cannot turn image into video',TypePolicy::selected('image/png','a.png','video','',false)==='image');
check('ordinary replacement re-infers actual contents',TypePolicy::selected('application/pdf','a.pdf','image','image',false)==='pdf');
check('external poster replacement retains provider',TypePolicy::selected('image/jpeg','a.jpg','image','youtube',false)==='youtube');
check('normal user may pick provider',TypePolicy::selected('image/jpeg','a.jpg','vimeo','',false)==='vimeo');
check('superuser override retained',TypePolicy::selected('image/jpeg','a.jpg','video','',true)==='video');
foreach([false,true] as $superuser) {
 $html=External::fieldsHtml(['type'=>'image','superuser'=>$superuser]);
 foreach(TypePolicy::FILE_TYPES as $type) {
  preg_match('~<option value="'.$type.'"([^>]*)>~',$html,$m);
  check('rendered option permissions '.$type,isset($m[1])&&str_contains($m[1],'disabled')===!$superuser);
 }
 preg_match('~<option value="youtube"([^>]*)>~',$html,$m);
 check('rendered external option enabled',!str_contains($m[1],'disabled'));
}
