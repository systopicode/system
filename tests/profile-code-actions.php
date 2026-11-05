<?php
declare(strict_types=1);
namespace Systopic\System\Auth {
class SignInCodes {
 public const BLOCKED='blocked';public static string $error='';public static array $offers=[];
 static function available(){return true;}static function offer($account,$by){self::$offers[]=[$account->id,$by];return ['user'=>$account->id,'code'=>'DEMO-ONLY','expires'=>time()+600];}
 static function approve($code,$account,$me){return $code!=='EXPI-RED1';}
}
class CodeMail {static function link($code){return '/auth/?code='.$code;}}
class SignInGrant {static function account($scope,$me,$panel,$id){return in_array($id,[42,43],true)?(object)['id'=>$id,'fullname'=>'Fixture User','name'=>'Fixture','type'=>$id===43?'screen':'person']:null;}}
}
namespace Systopic\System\Panels\Root\Admin { class Panel {} }
namespace {
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/lib/class/http/http.php';
require dirname(__DIR__).'/panels/admin/user/panel.php';
use Systopic\System\Auth\SignInCodes;
class message {
 static array $errors=[];static string $text='';
 static function error($t){self::$errors[]=$t;self::$text=$t;return new self;}
 static function confirm($t){self::$text=$t;return new self;}
 function target($t){return $this;}static function flush($t){$s=self::$text;self::$text='';return $s;}
}
class qr {static function payload($type,$data){return 'fixture';}static function svgMarkup(...$args){return '<svg></svg>';}}
class client {static function addCommand($type){return new class {function selector($v){return $this;}function attr($v){return $this;}function value($v){return $this;}};}}
class clientDocument {static array $changes=[];static function replaceInner($selector,$html){self::$changes[]=[$selector,$html];}}
class ActionFixture {
 public $scope=null;public object $me;public bool $deviceAllowed=false;public array $updates=[];public string $href='/admin/user/';public ?string $renderStyle=null;
 function __construct(public string $action,public string $authKind){$this->me=(object)['id'=>42];}
 function __get($key){if($key==='scanFeedback')return (new \Systopic\System\Panels\Root\Admin\User\Panel)->scanFeedback;throw new RuntimeException($key);}
 function updateView($view){$this->updates[]=$view;}
 function run(){include dirname(__DIR__).'/panels/admin/user/actions.php';$this->renderStyle=$renderStyle??null;}
}
function runAction($action,$post=[],$form=[],$kind='self'){
 if ($post !== []) $post['kind'] ??= $kind; if ($form !== []) $form['kind'] ??= $kind;
 http::$dataset=(object)[];http::$get=(object)[];http::$post=(object)$post;http::$form=(object)$form;message::$errors=[];message::$text='';clientDocument::$changes=[];SignInCodes::$offers=[];
 $p=new ActionFixture($action,$kind);$p->run();return $p;
}
foreach(['createDeviceCode','approveDeviceCode'] as $action){$p=runAction($action);check('empty follow-up is not a new '.$action,message::$errors===[]&&SignInCodes::$offers===[]&&$p->updates===[]&&clientDocument::$changes===[]);}
$p=runAction('createDeviceCode',['account'=>42]);check('POST fields create code for selected self',SignInCodes::$offers===[[42,42]]&&message::$errors===[]);
check('code creation updates only the open result fragment', $p->updates===[] && $p->renderStyle==='none' && str_ends_with(clientDocument::$changes[0][0], '[data-create-result]'));
$p=runAction('createDeviceCode',[],['account'=>43],'screen');check('AJAX PUT form fields retain selected screen',SignInCodes::$offers===[[43,42]]&&message::$errors===[]);
$p=runAction('createDeviceCode',[],['account'=>99]);check('unauthorised account is still rejected',message::$errors===['That account cannot be signed in.']&&SignInCodes::$offers===[]);
$p=runAction('approveDeviceCode',[],['account'=>42,'deviceCode'=>'DEMO-ONLY']);check('AJAX PUT approval keeps valid account and open feedback',$p->deviceAllowed&&$p->updates===[]&&message::$errors===[]&&str_contains(clientDocument::$changes[0][1],'data-code-approved'));
$p=runAction('approveDeviceCode',[],['account'=>43,'deviceCode'=>'DEMO-ONLY'],'screen');check('screen approval uses explicit read-only refresh endpoint',str_contains(clientDocument::$changes[0][1],'/admin/user/refreshScreenDevices'));
$p=runAction('approveDeviceCode',[],['account'=>42,'deviceCode'=>'EXPI-RED1']);check('expired code reports code error rather than account error',message::$errors===['That code is wrong or has expired.']);
runAction('approveDeviceCode',[],['account'=>42,'deviceCode'=>'EXPI-RED1','scanInstance'=>str_repeat('a',32)]);check('late error is scoped to the originating open dialog',str_contains(clientDocument::$changes[0][0],'[data-scan-instance="'.str_repeat('a',32).'"]:not([hidden])'));
$p=runAction('createDeviceCode');check('reopening after success does not create an account error',message::$errors===[]&&SignInCodes::$offers===[]);
}
