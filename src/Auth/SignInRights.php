<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\Db\Type\Text;
use Systopic\System\Queries\UserRights\UserRights;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Users\Model as User;

/**
 * Two permissions, kept as groups so a role can be given either of them.
 * A superuser may do both. Signing in as another superuser still takes a
 * superuser, even when authPersons is held.
 */
final class SignInRights
{
    public const PERSONS = 'authPersons';
    public const SCREENS = 'authScreens';

    /** @var array<string, string> */
    private const TEXT = [
        self::PERSONS => 'May sign a device in as another person. Signing in as a superuser still takes a superuser.',
        self::SCREENS => 'May sign a device in as a screen.',
    ];

    /** @var array<string, string> */
    private const COLOR = [
        self::PERSONS => '#2f6f8f',
        self::SCREENS => '#3f6f4a',
    ];

    private static bool $ready = false;

    /** @var array<string, int> */
    private static array $ids = [];

    public static function ensure(Scope $scope): void
    {
        if (self::$ready) {
            return;
        }
        $table = Schema::tableNameOf(Group::class);
        $rows = $scope->connection->all(
            "SELECT group_id, group_name FROM $table WHERE group_name IN (?, ?)",
            [self::PERSONS, self::SCREENS],
        );
        foreach ($rows as $row) {
            self::$ids[(string) $row['group_name']] = (int) $row['group_id'];
        }
        foreach (self::TEXT as $name => $text) {
            if (isset(self::$ids[$name])) {
                continue;
            }
            $group = new Group();
            $scope->save($group);
            $group->name = $name;
            $group->description = new Text($text);
            $group->color = self::COLOR[$name];
            $scope->save($group);
            self::$ids[$name] = (int) $group->id;
        }
        self::$ready = true;
    }

    /** Read or write on the permission, or being a superuser. */
    public static function allows(Scope $scope, User $user, string $name): bool
    {
        if ($user->isBlocked) return false;
        if (!isset(self::TEXT[$name])) {
            return false;
        }
        if ($user->isSuperuser) {
            return true;
        }
        self::ensure($scope);
        $group = self::$ids[$name] ?? 0;
        if ($group <= 0 || (int) $user->id <= 1) {
            return false;
        }
        $roleIds = array_map(static fn($role): int => (int) $role->id, UserRights::roles($scope, (int) $user->id));
        $rights = UserRights::groups($scope, $roleIds);
        return in_array($group, $rights['read'], true) || in_array($group, $rights['write'], true);
    }

    /**
     * Other active persons this account may sign a device in as.
     * Superusers are included only when `$actor` is a superuser.
     *
     * @return list<array<string, mixed>>
     */
    public static function persons(Scope $scope, User $actor): array
    {
        if (!self::allows($scope, $actor, self::PERSONS)) {
            return [];
        }
        $table = Schema::tableNameOf(User::class);
        $sql = "SELECT user_id, user_name, user_fullname, user_email FROM $table
            WHERE user_id > 1 AND user_id <> ? AND user_is_active = 1 AND COALESCE(user_is_blocked, 0) = 0
            AND (user_type = ? OR user_type IS NULL OR user_type = '')";
        $args = [(int) $actor->id, User::PERSON];
        if (!$actor->isSuperuser) {
            $sql .= ' AND (user_is_superuser = 0 OR user_is_superuser IS NULL)';
        }
        $sql .= ' ORDER BY user_name';
        try {
            return $scope->connection->all($sql, $args);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Active screens.
     *
     * @return list<array<string, mixed>>
     */
    public static function screens(Scope $scope, User $actor): array
    {
        if (!self::allows($scope, $actor, self::SCREENS)) {
            return [];
        }
        $table = Schema::tableNameOf(User::class);
        try {
            return $scope->connection->all(
                "SELECT user_id, user_name, user_fullname, user_email FROM $table
                WHERE user_type = ? AND user_id > 1 AND user_is_active = 1 AND COALESCE(user_is_blocked, 0) = 0 ORDER BY user_name",
                [User::SCREEN],
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
