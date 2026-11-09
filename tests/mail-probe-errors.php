<?php
namespace Systopic\System\Settings { class EnvironmentFile {const LOCAL='config.local.json';const PROJECT='config.json';static function directory(){return '/fixture';}static function activeFile(){return self::LOCAL;}} }
namespace Systopic\System\Auth {class Session {static function isSuperuser(){return true;}}}
namespace Systopic\System\Panels {class PanelNode {public $action='testMail';public bool $updated=false;function updateView(){ $this->updated=true;}function updateViews($views=[]){ $this->updated=true;}}}
namespace Systopic\System\Mail {class TestDelivery {public static string $error='';static function send(){throw new \RuntimeException(self::$error);}}class Delivery {static function settings(){return ['password'=>'fixture-secret'];}}}
namespace {require __DIR__.'/bootstrap.php';class http {static function dataset($key){return 'config.local.json';}}class message {public static array $errors=[];static function error($e){self::$errors[]=$e;}}
use Systopic\System\Mail\TestDelivery;use Systopic\System\Settings\EnvironmentForm;
foreach (['SMTP: expected 235 got 535 fixture-secret'=>'authentication','SMTP: TLS handshake failed'=>'encrypted','SMTP: no connection (refused)'=>'reached','SMTP: expected 250 got 550 recipient rejected'=>'address'] as $error=>$expected) {
 TestDelivery::$error=$error;$panel=new \Systopic\System\Panels\PanelNode;EnvironmentForm::handle($panel);$text=end(message::$errors);
 check('probe renders '.$expected.' diagnosis',str_contains($text,$expected)&&!str_contains($text,'fixture-secret')&&$panel->updated);
}}
