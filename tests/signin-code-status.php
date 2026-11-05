<?php
declare(strict_types=1);
namespace Systopic\Db {class Scope {static function default(){return null;}}}
namespace Systopic\System\Auth {class AuthConfig {static function secret(){return str_repeat('a',64);}}}
namespace Systopic\System\Queries\SignInCodes {class SignInCodes {static mixed $row=null;static bool $fail=false;static function byHash($scope,$hash){if(self::$fail)throw new \RuntimeException('offline');return self::$row;}}}
namespace {
require __DIR__.'/bootstrap.php';
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Queries\SignInCodes\SignInCodes as Query;
Query::$row=(object)['usedAt'=>null];check('unused or expired code is not reported as successful',!SignInCodes::consumed('K7Q4-M2XD'));
Query::$row=(object)['usedAt'=>new DateTimeImmutable];check('spent code is reported as consumed',SignInCodes::consumed('K7Q4-M2XD'));
Query::$row=null;check('unknown code is not reported as successful',!SignInCodes::consumed('K7Q4-M2XD'));
Query::$fail=true;check('lookup failure does not invent success',!SignInCodes::consumed('K7Q4-M2XD'));
}
