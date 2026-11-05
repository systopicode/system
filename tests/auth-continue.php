<?php
namespace Systopic\System\Auth {
 class Session {static bool $active=true;static function active(){return self::$active;}static function check($name,$password){return (object)['name'=>'Test'];}}
 class Passkeys {static int $calls=0;static int $offers=0;static function offerSetup($user){self::$offers++;return true;}static function completeSetup(){self::$calls++;return '/frontend/original/';}}
}
namespace {
require __DIR__.'/bootstrap.php';
class http {static array $fields=[];static function posted($key,$default=null){return self::$fields[$key]??$default;}}
$panel=new class {
 public string $action='continueWithoutPasskey';public string $form='passkeySetup';public array $redirect=[];
 function updateView($view) {check('updates auth main only',$view==='main');}
 function signIn($user,$method){}
 function enterCms($delay,$target=null){$this->redirect=[$delay,$target];}
 function run(){include dirname(__DIR__).'/panels/auth/actions.php';}
};
$panel->run();
check('skip displays success instead of password form',$panel->form==='signedIn');
check('success waits 2 seconds and retains frontend return',$panel->redirect===[2,'/frontend/original/']);
\Systopic\System\Auth\Session::$active=false;$panel->redirect=[];$panel->run();
check('inactive skip never grants access',$panel->form==='login'&&$panel->redirect===[]&&\Systopic\System\Auth\Passkeys::$calls===1);
$panel->action='doSignIn';$panel->redirect=[];http::$fields=['passkey_supported'=>'0'];$panel->run();
check('unsupported browser finishes password login without setup offer',$panel->form==='signedIn'&&$panel->redirect===[2,null]&&\Systopic\System\Auth\Passkeys::$offers===0);
$panel->redirect=[];http::$fields=['passkey_supported'=>'1'];$panel->run();
check('support is checked per request without an account preference',$panel->form==='passkeySetup'&&$panel->redirect===[]&&\Systopic\System\Auth\Passkeys::$offers===1);

}
