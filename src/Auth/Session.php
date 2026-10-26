<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Scope;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Setup\Setup;
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
 * nobody. When the database has no account (no users table, or no row but
 * the system's own user 1 — an empty database before a restore) the request
 * runs as the recovery user with the role `restore`; so it does when the
 * database is not reachable, with the role `recovery`. Either only for whoever
 * may set the installation up (Setup::trusts(): a local request, or the setup
 * token) — anyone else is nobody, and a database that is down answers 503
 * (loader.php).
 */
final class Session
{
    private const USER_KEY = 'systopic_user_id';
    private const STATE_KEY = 'systopic_user_state';
    private const RECOVERY_KEY = 'systopic_recovery';
    private const RETURN_KEY = 'systopic_return_to';

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
        // a new id with every login: an id planted before (session fixation)
        // is worth nothing afterwards. The CLI agent keeps its own - it has no
        // cookie anybody could plant.
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            LoginLog::sessionClosed(session_id());
            session_regenerate_id(true);
            LoginLog::sessionOpened(session_id(), (string) $row->name);
        }
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
            SecurityNotice::rehashing(static fn() => $scope->save($user));
        }
        return $user;
    }

    /**
     * Out of the session — and, with `$thisDevice`, out on this device: its
     * row revoked, its cookie gone, so it is not logged in again by
     * Devices::restore(). Switching users (hijack and back) and a device
     * revoked elsewhere pass false.
     */
    public static function logout(bool $thisDevice = true): void
    {
        if ($thisDevice) {
            Devices::logoutHere();
        }
        \http::sessionStartOnce();
        if (session_status() === PHP_SESSION_ACTIVE) {
            LoginLog::sessionClosed(session_id());
        }
        unset($_SESSION[self::USER_KEY]);
        self::$current = self::load();
    }

    /**
     * The page someone without a session asked for - a deep link, a click
     * after the session ran out, a page of a site that is not public. Only
     * the path and query are kept, and only below the site root: the sign-in
     * cannot be sent anywhere else.
     */
    public static function rememberReturn(string $url): void
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $root = rtrim((string) \http::$root, '/') . '/';
        if (!str_starts_with($path, $root) || preg_match('~^(cms/)?auth(/|$)~', substr($path, strlen($root)))) {
            return;
        }
        \http::sessionStartOnce();
        $_SESSION[self::RETURN_KEY] = $path . ($query !== '' ? '?' . $query : '');
    }

    /** Where the sign-in leads, once. */
    public static function takeReturn(): ?string
    {
        \http::sessionStartOnce();
        $url = $_SESSION[self::RETURN_KEY] ?? null;
        unset($_SESSION[self::RETURN_KEY]);
        return is_string($url) ? $url : null;
    }

    /**
     * The database is not reachable: the recovery user — or nobody, for a
     * request that may not set up (isRecovery() tells). @param list<string>|null $roles
     */
    public static function recover(?array $roles = null): Identity
    {
        // a scope that never connects: the recovery user reads nothing
        return self::$current = self::recoveryOrNobody(new Scope(), $roles ?? ['recovery']);
    }

    /**
     * May this request be the recovery user? Whoever may set up (Setup::trusts)
     * — remembered in the session, the token comes with the first url only.
     * The CLI is always local.
     */
    public static function recoveryTrusted(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        \http::sessionStartOnce();
        if (!empty($_SESSION[self::RECOVERY_KEY])) {
            return true;
        }
        $given = (string) ($_POST['setup'] ?? $_GET['setup'] ?? '');
        if (\fs::$projectRoot === null || !Setup::trusts(\fs::toNative((string) \fs::$projectRoot), $_SERVER, $given)) {
            return false;
        }
        $_SESSION[self::RECOVERY_KEY] = true;
        return true;
    }

    // ------------------------------------------------------------ state

    /** The panels' state tree, kept in the session; `fakeRoles` lives here too. */
    public static function state(): object
    {
        if (self::$state === null) {
            \http::sessionStartOnce();
            $stored = $_SESSION[self::STATE_KEY] ?? null;
            self::$state = is_object($stored) ? $stored : (object) ['fakeRoles' => [], 'since' => time()];
            self::$state->fakeRoles ??= [];
            if (self::resetRequested() > (self::$state->since ?? 0)) {
                self::$state = (object) ['fakeRoles' => self::$state->fakeRoles, 'since' => time()];
            }
        }
        return self::$state;
    }

    /** Every panel back to its default state (open panes, folds, filters); fake roles stay. */
    public static function resetState(): void
    {
        self::$state = (object) ['fakeRoles' => self::state()->fakeRoles, 'since' => time()];
    }

    /**
     * Resets the state of another user at their next request: the state sits
     * in their session, out of reach - the account keeps the time instead.
     */
    public static function requestStateReset(\Systopic\System\Tables\Users\Model $user): \Systopic\System\Tables\Users\Model
    {
        $data = json_decode((string) $user->sessiondata?->value, true);
        $data = is_array($data) ? $data : [];
        $data['stateReset'] = time();
        $user->sessiondata = new \Systopic\Db\Type\Text((string) json_encode($data));
        return $user;
    }

    private static function resetRequested(): int
    {
        // Without a database there is nobody whose settings could ask for a
        // reset - and an app without the db package (the project manager in
        // packages/tools) could not even load the user to ask.
        if (!class_exists(\Systopic\Db\Connection::class) || !\Systopic\Db\Connection::isOpen()) {
            return 0;
        }
        $user = self::current()->user;
        $data = $user === null ? null : json_decode((string) $user->sessiondata?->value, true);
        return (int) ($data['stateReset'] ?? 0);
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

    /** The cookie still names a session PHP has thrown away: whose it was goes into the log. */
    private static function noteLapsed(): void
    {
        $sid = PHP_SAPI === 'cli' || isset($_SESSION[self::USER_KEY]) ? '' : (string) ($_COOKIE[session_name()] ?? '');
        if ($sid !== '' && ($name = LoginLog::sessionLapsed($sid)) !== null) {
            LoginLog::write($name, 'logged out', LoginLog::TIMEOUT);
        }
    }

    private static function load(): Identity
    {
        \http::sessionStartOnce();
        if (!\Systopic\Db\Connection::isOpen()) {
            // an app without a database (DB_NAME '') has nothing to restore
            // and runs as the recovery user, as it always did
            return defined('DB_NAME') && DB_NAME !== ''
                ? self::recoveryOrNobody(new Scope())
                : Identity::recovery(new Scope(), ['restore']);
        }
        $scope = Scope::default();
        $id = (int) ($_SESSION[self::USER_KEY] ?? 0);
        if ($id <= 0 && defined('AUTOLOGON_ID')) {
            $id = (int) AUTOLOGON_ID;
        }
        try {
            if ($id > 0 && ($user = UserLookup::byId($scope, $id)) !== null) {
                return new Identity($scope, $user);
            }
            self::noteLapsed();
            // nobody logged in: is there an account at all? Asked by row, not
            // by table — a lookup with the provisioner creates usm_users empty.
            $any = $scope->connection->value('SELECT 1 FROM ' . \Systopic\Db\Schema\Schema::tableNameOf(User::class) . ' WHERE user_id > 1 LIMIT 1');
            return $any === null ? self::recoveryOrNobody($scope) : Identity::anonymous($scope);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1146) {   // table missing
                return self::recoveryOrNobody($scope);
            }
            throw $e;
        }
    }

    /** @param list<string> $roles */
    private static function recoveryOrNobody(Scope $scope, array $roles = ['restore']): Identity
    {
        return self::recoveryTrusted() ? Identity::recovery($scope, $roles) : Identity::anonymous($scope);
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
