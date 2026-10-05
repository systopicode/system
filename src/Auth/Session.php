<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Scope;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as User;

/**
 * The session of the request: who is logged in, what they may do, and the
 * state the panels keep between requests — successor of the static half of
 * `lib/class/user` — which lives on as a shell over this in
 * systopic/system-legacy, for the projects that still use it.
 *
 *   Session::current()              the Identity (user, roles, group rights)
 *   Session::known(), ::id()
 *   Session::hasRole('editor')      fake roles count when set (a superuser "viewing as")
 *   Session::login($user), ::logout()
 *   Session::state()                the panels' state tree, stored at the end of the request
 *
 * The session keys are those of before (`systopic_user_id`,
 * `systopic_user_state`): sessions open at the switch stay logged in.
 *
 * Who it is, in this order: the user of the session, `AUTOLOGON_ID` (CLI, DEV),
 * nobody. When the users table is not there (an empty database, before a
 * restore) the request runs as the recovery user with the role `restore`.
 */
final class Session
{
    private const USER_KEY = 'systopic_user_id';
    private const STATE_KEY = 'systopic_user_state';

    private static ?Identity $current = null;
    private static ?object $state = null;

    public static function current(): Identity
    {
        return self::$current ??= self::load();
    }

    // ------------------------------------------------------------- who

    public static function id(): ?int
    {
        return self::current()->id;
    }

    public static function known(): bool
    {
        return self::current()->known;
    }

    /** Logged in and the account is active. */
    public static function active(): bool
    {
        return self::current()->known && self::current()->isActive;
    }

    public static function isRecovery(): bool
    {
        return self::current()->recovery;
    }

    // ----------------------------------------------------------- roles

    public static function hasRole(string $role, bool $ignoreFakeRoles = false): bool
    {
        $fake = self::fakeRoles();
        if ($fake !== [] && !$ignoreFakeRoles) {
            return in_array($role, $fake, true);
        }
        $identity = self::current();
        return in_array($role, $identity->roleNames, true) || $identity->isSuperuser;
    }

    public static function hasOneRole(string ...$roles): bool
    {
        foreach ($roles as $role) {
            if (self::hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    /** A superuser — and not viewing as a role, unless `$ignoreFakeRoles`. */
    public static function isSuperuser(bool $ignoreFakeRoles = false): bool
    {
        if (!self::current()->isSuperuser) {
            return false;
        }
        return $ignoreFakeRoles || self::fakeRoles() === [];
    }

    /** @return list<string> */
    public static function fakeRoles(): array
    {
        return array_values((array) (self::state()->fakeRoles ?? []));
    }

    /** View the CMS as a role (only one the user holds; a superuser holds every one). */
    public static function addFakeRole(?string $role = null): void
    {
        if ($role === null || !self::hasRole($role, true)) {
            return;
        }
        $state = self::state();
        $roles = self::fakeRoles();
        if (!in_array($role, $roles, true)) {
            $roles[] = $role;
        }
        $state->fakeRoles = $roles;
        self::$current = null;   // rights follow the roles
    }

    public static function removeFakeRole(?string $role = null): void
    {
        self::state()->fakeRoles = array_values(array_diff(self::fakeRoles(), [$role]));
        self::$current = null;
    }

    // ---------------------------------------------------------- rights

    /**
     * May the user read / write a row with `user_id` and `group_id` (legacy
     * records and ORM rows alike)? The owner always; no group: superusers.
     */
    public static function mayRead(object $item): bool
    {
        return self::may($item, self::current()->readGroupIds);
    }

    public static function mayWrite(object $item): bool
    {
        return self::may($item, self::current()->writeGroupIds);
    }

    // ----------------------------------------------------- in and out

    /**
     * Log a user in — an ORM row, a legacy `user` record, or an id. Stamps
     * the first and last login.
     */
    public static function login(object|int $user): void
    {
        $id = is_int($user) ? $user : (int) ($user->id ?? 0);
        $scope = Scope::default();
        $row = $user instanceof User ? $user : UserLookup::byId($scope, $id);
        if ($row === null) {
            return;
        }
        \http::sessionStartOnce();
        $_SESSION[self::USER_KEY] = (int) $row->id;
        $now = new \DateTimeImmutable();
        $row->firstLogin ??= $now;
        $row->lastLogin = $now;
        $scope->save($row);
        self::$current = new Identity($scope, $row);
    }

    /**
     * Name or email and password: the user, or null. An md5 hash of before is
     * replaced by a current one right here — the only moment the password is known.
     */
    public static function check(string $name, string $password): ?User
    {
        $scope = Scope::default();
        $user = UserLookup::byName($scope, $name);
        if ($user === null || !Password::verify($user->passwordHash, $password)) {
            return null;
        }
        if (Password::needsRehash($user->passwordHash)) {
            $user->passwordHash = Password::hash($password);
            $scope->save($user);
        }
        return $user;
    }

    public static function logout(): void
    {
        \http::sessionStartOnce();
        unset($_SESSION[self::USER_KEY]);
        self::$current = self::load();
    }

    /** The recovery user — no database, or a restore under way. @param list<string>|null $roles */
    public static function recover(?array $roles = null): Identity
    {
        // a scope that never connects: the recovery user reads nothing
        return self::$current = Identity::recovery(new Scope(), $roles ?? ['recovery']);
    }

    // ------------------------------------------------------------ state

    /** The panels' state tree, kept in the session; `fakeRoles` lives here too. */
    public static function state(): object
    {
        if (self::$state === null) {
            \http::sessionStartOnce();
            $stored = $_SESSION[self::STATE_KEY] ?? null;
            self::$state = is_object($stored) ? $stored : (object) ['fakeRoles' => []];
            self::$state->fakeRoles ??= [];
        }
        return self::$state;
    }

    /** Written back once per request (app.php), after the panels changed it. */
    public static function storeState(): void
    {
        $_SESSION[self::STATE_KEY] = self::state();
    }

    /** Forget everything of this request — for tests and the CLI. */
    public static function reset(): void
    {
        self::$current = null;
        self::$state = null;
    }

    // ------------------------------------------------------------------

    private static function load(): Identity
    {
        \http::sessionStartOnce();
        if (!\Systopic\Db\Connection::isOpen()) {
            return Identity::recovery(new Scope(), ['restore']);
        }
        $scope = Scope::default();
        $id = (int) ($_SESSION[self::USER_KEY] ?? 0);
        if ($id <= 0 && defined('AUTOLOGON_ID')) {
            $id = (int) AUTOLOGON_ID;
        }
        try {
            if ($id > 0) {
                $user = UserLookup::byId($scope, $id);
                return $user === null ? Identity::anonymous($scope) : new Identity($scope, $user);
            }
            // nobody logged in: is there a users table at all? (empty database → restore)
            $scope->connection->run('SELECT 1 FROM ' . \Systopic\Db\Schema\Schema::tableNameOf(User::class) . ' LIMIT 0');
            return Identity::anonymous($scope);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1146) {   // table missing
                return Identity::recovery($scope, ['restore']);
            }
            throw $e;
        }
    }

    /** @param list<int> $groupIds */
    private static function may(object $item, array $groupIds): bool
    {
        $owner = $item->user_id ?? (method_exists($item, 'relationId') ? $item->relationId('user') : null);
        if ($owner !== null && (int) $owner === self::id()) {
            return true;
        }
        $group = $item->group_id ?? (method_exists($item, 'relationId') ? $item->relationId('group') : null);
        if ($group === null || (int) $group === 0) {
            return self::isSuperuser();
        }
        return in_array((int) $group, $groupIds, true);
    }
}
