<?php
declare(strict_types=1);
namespace Systopic\Db {class Scope{static function default(){return null;}}}
namespace Systopic\System\Tables\Users {class Model{const SCREEN='screen';public string $type='person';}}
namespace Systopic\System\Queries\UserLookup {class UserLookup{static function byId($scope,$id){return new \Systopic\System\Tables\Users\Model;}}}
namespace Systopic\System\Auth {
 class Session{static function known(){return true;}static function id(){return 42;}static function takeReturn(){return null;}}
 class SignInFeedback{static function profile(){return ['name'=>'Fixture'];}}
 class SignInCodes{static function pollDeviceCode($poll){throw new \RuntimeException('must not redeem a second time');}}
}
namespace {
require __DIR__.'/bootstrap.php';
class http {static string $root='/';}
if(($argv[1]??'')==='fixture'){
 $_SERVER['HTTP_X_DEVICE_CODE_POLL']='1';
 \Systopic\System\Auth\DeviceCode::handle();
}
$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' fixture';
exec($command,$lines,$exit);$result=json_decode(implode("\n",$lines),true);
check('an already authenticated poll returns JSON success without redeeming again',$exit===0&&($result['paired']??false)===true);
check('poll success includes normal target and profile',($result['redirect']??null)==='/admin/'&&($result['profile']['name']??null)==='Fixture');
}
