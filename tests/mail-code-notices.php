<?php
namespace Systopic\System\Sys {class LocalTime {const DATETIME='datetime';static function format($value,$format){return '02.11.2026 20:00';}}}
namespace {
require __DIR__.'/mail-theme-fixture.php';
require dirname(__DIR__).'/src/Mail/Letter.php';
// Template-only fixtures: no delivery, database or real access key.
http::$siteUrl = 'https://fixture.test/';
$user=(object)['name'=>'Fixture','fullname'=>'Fixture User','email'=>'fixture@example.test','activationKey'=>'fixture-only','activationKeyExpire'=>'tomorrow'];
$link='https://fixture.test/sign-in'; $code='TEST-CODE'; $expires='24 hours';
foreach (['panels/auth/emailActivation.tpl.php','panels/auth/emailResetPassword.tpl.php','src/Auth/CodeMail.tpl.php'] as $template) {
 foreach (['invite'] as $purpose) {
  ob_start(); require dirname(__DIR__).'/'.$template; $html=ob_get_clean();
  if(substr_count($html,'Do not forward this email or share its code or link.')!==1) throw new \RuntimeException('Missing notice: '.$template);
  echo 'PASS '.$template.' '.$purpose."\n";
 }
}
}
