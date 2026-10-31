<?php
namespace Systopic\System\Config {
    final class Config {public static array $mail=[];public static function current():object{return (object)['instance'=>new class {public function get($key):array{return Config::$mail;}}];}}
}
namespace {
    require __DIR__.'/bootstrap.php';
    class http {public static string $siteUrl='https://cms.example.test/project/';}
    define('STAGE','LIVE');define('PAGE_TITLE_SEPERATOR',' · ');
    use Systopic\System\Mail\Delivery;
    use Systopic\System\Config\Config;
    $mail=(object)['subject'=>"Account notice\r\n",'to'=>[['owner@example.test','Owner']],'cc'=>[['copy@example.test','Copy']],'bcc'=>[['hidden@example.test','Hidden']],'reply_to'=>[]];
    Delivery::prepare($mail);
    check('all mail subjects use the global separator and installation URL',$mail->subject==='Account notice · cms.example.test');
    Delivery::prepare($mail);
    check('subject formatting is idempotent',substr_count($mail->subject,'cms.example.test')===1);
    Config::$mail=['testMode'=>true,'testRecipient'=>'sink@example.test'];Delivery::prepare($mail);Delivery::prepare($mail);
    check('test prefix precedes subject and URL exactly once',$mail->subject==='Test mail // Account notice · cms.example.test');
    check('forced test routing clears all original recipient lists',$mail->to===[['sink@example.test','Test recipient']] && $mail->cc===[] && $mail->bcc===[]);
}

namespace { use Systopic\System\Config\Config; use Systopic\System\Mail\Delivery; foreach (['https://www.example.com/a'=>'example.com','http://shop.example.com/b'=>'shop.example.com'] as $url=>$host) {http::$siteUrl=$url;$m=(object)['subject'=>'Hello','to'=>[],'cc'=>[],'bcc'=>[]];Config::$mail=[];Delivery::prepare($m);check('compact host '.$host,$m->subject==='Hello · '.$host);} }
