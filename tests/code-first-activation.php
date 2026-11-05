<?php
declare(strict_types=1);
namespace Systopic\Db {class Scope {static $instance; public array $saved=[]; static function default(){return self::$instance??=new self;} function save($user){$this->saved[]=$user;}}}
namespace Systopic\System\Tables\Users {
 class Model {const SCREEN='screen'; public int $id=30; public bool $isActive=false,$isBlocked=false; public string $type='screen',$name='fixture'; public $activationKey='old',$activationKeyExpire=null;}
}
namespace Systopic\System\Queries\UserLookup {class UserLookup {static $user;static function byId($scope,$id){return self::$user;}}}
namespace Systopic\System\Auth {
 class Session {static $user;static function login($user){self::$user=$user;}static function known(){return false;}}
 class Devices {static function issue($user){}}
 class SignInLog {const DEVICE_CODE='device';static function write(...$args){}}
 class SecurityNotice {static function deviceSignedIn(...$args){}}
}
namespace {
require __DIR__.'/bootstrap.php';
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Tables\Users\Model;
$user=new Model;
check('an invited account may be signed in through an authorised code',SignInCodes::canSignIn($user));
$user->isBlocked=true;
check('a blocked invited account remains excluded',!SignInCodes::canSignIn($user));
$user->isBlocked=false;$user->id=1;
check('the system account remains excluded',!SignInCodes::canSignIn($user));
}
