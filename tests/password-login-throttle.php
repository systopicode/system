<?php
declare(strict_types=1);
namespace Systopic\Db {
    class Scope {
        public static ?self $instance = null;
        public $failures = null;
        public static function default(): self { return self::$instance ??= new self(); }
        public function save($row): void { $this->failures = $row; }
    }
}
namespace Systopic\System\Queries\AuthFailures {
    class AuthFailures { public static function of($scope, $ip) { return $scope->failures; } }
}
namespace Systopic\System\Queries\UserLookup {
    class UserLookup {
        public static $user;
        public static int $calls = 0;
        public static function byName($scope, $name) { self::$calls++; return self::$user; }
    }
}
namespace {
    require __DIR__ . '/bootstrap.php';
    use Systopic\System\Auth\Session;
    use Systopic\System\Auth\Password;
    use Systopic\System\Queries\UserLookup\UserLookup;
    use Systopic\System\Tables\Users\Model;
    use Systopic\Db\Scope;
    $_SERVER['REMOTE_ADDR'] = '192.0.2.123';
    $user = new Model(); $user->isBlocked = false; $user->passwordHash = Password::hash('a long passphrase');
    UserLookup::$user = $user;
    check('valid password accepted before throttling', Session::check('fixture','a long passphrase') === $user);
    UserLookup::$user = null;
    for ($i=0; $i<10; $i++) check('failed attempt rejected', Session::check('missing','wrong') === null);
    check('ten failures set existing IP block', Scope::default()->failures->blockedUntil > new DateTimeImmutable());
    UserLookup::$user = $user; $calls = UserLookup::$calls;
    check('blocked IP cannot sign in even with correct credentials', Session::check('fixture','a long passphrase') === null);
    check('blocked attempt stops before account lookup', UserLookup::$calls === $calls);
    Scope::default()->failures->blockedUntil = new DateTimeImmutable('-1 second');
    check('sign in available after block expires', Session::check('fixture','a long passphrase') === $user);
}
