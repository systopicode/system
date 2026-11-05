<?php
declare(strict_types=1);
namespace Systopic\System\Auth { class SignInCodes { static function shown(){return null;} } }
namespace {
require __DIR__.'/bootstrap.php';
class http { static function posted($key,$default=null){return ['deviceCode'=>'TEST-CODE'][$key]??$default;} static function ajax(){return true;} }
class message {static function flush($target){return '';}}
class DialogFixture {
 public string $authKind='screen', $action='approveDeviceCode', $href='/admin/user/';public bool $deviceAllowed=false;
 function render(?string $mode=null):string {
  $accountsOf=['self'=>[['id'=>42,'label'=>'Self']], 'screen'=>[['id'=>43,'label'=>'Screen']], 'person'=>[['id'=>44,'label'=>'Person']]];
  $fixedDialog=$mode ? ['kind'=>'screen','mode'=>$mode,'base'=>'/admin/user/','close'=>'/admin/access/screens/'] : null;
  if($mode) $accountsOf=['self'=>[], 'screen'=>[['id'=>43,'label'=>'Screen']], 'person'=>[]];
  $me=(object)['fullname'=>'Self','name'=>'Self'];$pending=null;$deviceCode='TEST-CODE';$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES);
  ob_start(); include dirname(__DIR__).'/panels/admin/user/authDialogs.tpl.php'; return ob_get_clean();
 }
}
$fixture=new DialogFixture;$first=$fixture->render();$second=$fixture->render();
check('unchanged dialog render does not invalidate the active DOM', $first===$second);
$dom=new DOMDocument;@$dom->loadHTML($first);$xp=new DOMXPath($dom);
$forms=$xp->query('//form[@data-code-form]');check('all three scan flows share the same form', $forms->length===3);
foreach($forms as $form){
 check('all scan flows approve directly without rebuilding the profile', str_ends_with($form->getAttribute('action'), '/approveDeviceCode'));
 check('each scan flow explicitly identifies the chosen account', $xp->query('.//input[@name="account"]',$form)->length===1);
 check('manual scan form uses existing PUT action transport', $form->getAttribute('method')==='put');
 check('typing a complete code never requests automatic submission', !$form->hasAttribute('data-code-send'));
 check('manual approval has an explicit submit button', $xp->query('.//button[@type="submit"]',$form)->length===1);
}
check('three creation gates contain no real QR codes', $xp->query('//div[@data-create-gate]')->length===3 && $xp->query('//div[@data-create-gate]//svg')->length===0);
check('scan controls start inert behind the confirmation', $xp->query('//div[@data-scan-content and @inert and @aria-hidden="true"]')->length===3);
check('every scan gate has an explicit acknowledgement', $xp->query('//button[@data-scan-understood]')->length===3);
foreach(['scan','create','hidden'] as $mode) {
 $fixed=new DOMDocument;@$fixed->loadHTML($fixture->render($mode));$fx=new DOMXPath($fixed);
 check('fixed ' . $mode .' uses one shared scan and create dialog', $fx->query('//div[contains(@class,"deviceBox")]')->length===2);
 check('fixed ' . $mode .' has no account selector', $fx->query('//select')->length===0);
 check('fixed ' . $mode .' retains server-validated account id', $fx->query('//form[@data-code-form]//input[@name="account" and @value="43"]')->length===1);
 check('fixed ' . $mode .' opens only the requested dialog', $fx->query('//div[contains(@class,"deviceBox") and not(@hidden)]')->length===($mode==='hidden'?0:1));
 check('fixed ' . $mode .' does not show a stale code error', !str_contains($fixture->render($mode),'That code is wrong'));
}
check('an AJAX action does not reopen a scan dialog', $xp->query('//div[contains(@class,"scanCodeBox") and not(@hidden)]')->length===0);
}
