<?php
namespace Systopic\System\Auth {
 class Passkeys {static function config(){return ['origin'=>'https://fixture.test'];}}
 class Session { static function known(): bool { return false; } }
 class PasskeyChallenge {static function token(){return 'fixture-only';}}
}
namespace {
 class http {static string $root='/';static function sessionStartOnce(){}static function get($key,$default=''){return $default;}static function post($key,$default=''){return $default;}}
 class message {static function flush(...$args){return '';}}
 require __DIR__ . '/bootstrap.php';
 $view=$argv[1]??'password';
 if ($view === 'setPassword') {
  $context = new class {
   public $keyUser;
   function render() { include dirname(__DIR__).'/panels/admin/auth/setPassword.tpl.php'; }
  };
  $context->keyUser = new \Systopic\System\Tables\Users\Model();
  $context->keyUser->activationKey = 'fixture';
  $context->keyUser->activationKeyExpire = new \DateTimeImmutable('+1 hour');
  $context->render(); return;
 }
 if(in_array($view,['password','passkey','signedIn'],true)) {
  $authMethod=$view;$authPasskey=$view==='passkey';$authCodes=false;$authEndpoint='/admin/auth/';$authForgot='?view=resetPassword';$authForgotClass='ajax';
  include dirname(__DIR__).'/src/Auth/login.card.tpl.php';
 } elseif(in_array($view,['resetPassword','resetCode','activationCode','setPassword','register','loggedOut'],true)) include dirname(__DIR__).'/panels/admin/auth/'.$view.'.tpl.php';
 else throw new \RuntimeException('Unknown fixture');
}
