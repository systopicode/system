<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Controlmap\Access;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as User;

/**
 * Whom a signed-in person may sign a device in as. Always asked on the
 * trusted device: the one who authorizes says who the other device is.
 *
 * Everybody may sign in themselves. Other people take the right `authPersons`,
 * screens `authScreens`, both of the controlmap (`panels/panel.config.json`).
 * Only a superuser signs in a superuser.
 */
final class SignInGrant
{
    public const PERSONS = 'authPersons';
    public const SCREENS = 'authScreens';

    public static function mayPersons(PanelNode $node): bool
    {
        return Access::rightNamed($node, self::PERSONS);
    }

    public static function mayScreens(PanelNode $node): bool
    {
        return Access::rightNamed($node, self::SCREENS);
    }

    /**
     * What `$me` can choose from, themselves first.
     *
     * @return list<array{id: int, label: string, self: bool}>
     */
    public static function accounts(Scope $scope, User $me, PanelNode $node): array
    {
        $table = Schema::tableNameOf(User::class);
        $mine = [['id' => (int) $me->id, 'label' => self::label($me->fullname, $me->name), 'self' => true]];
        $where = 'user_id > 1 AND user_id <> ? AND user_is_active = 1 AND COALESCE(user_is_blocked, 0) = 0';
        $rows = [];
        if (self::mayPersons($node)) {
            $persons = "$where AND (user_type = ? OR user_type IS NULL OR user_type = '')";
            $persons .= $me->isSuperuser ? '' : ' AND COALESCE(user_is_superuser, 0) = 0';
            $rows = $scope->connection->all("SELECT user_id, user_name, user_fullname FROM $table WHERE $persons ORDER BY user_name", [(int) $me->id, User::PERSON]);
        }
        if (self::mayScreens($node)) {
            $rows = array_merge($rows, $scope->connection->all("SELECT user_id, user_name, user_fullname FROM $table WHERE $where AND user_type = ? ORDER BY user_name", [(int) $me->id, User::SCREEN]));
        }
        foreach ($rows as $row) {
            $mine[] = ['id' => (int) $row['user_id'], 'label' => self::label($row['user_fullname'], $row['user_name']), 'self' => false];
        }
        return $mine;
    }

    /** The account with this id, if `$me` may sign a device in as it. */
    public static function account(Scope $scope, User $me, PanelNode $node, int $id): ?User
    {
        $account = $id === (int) $me->id ? $me : UserLookup::byId($scope, $id);
        if ($account === null || !SignInCodes::canSignIn($account)) {
            return null;
        }
        if ((int) $account->id === (int) $me->id) {
            return $me->type === User::SCREEN ? null : $account;
        }
        if ($account->type === User::SCREEN) {
            return self::mayScreens($node) ? $account : null;
        }
        return self::mayPersons($node) && (!$account->isSuperuser || $me->isSuperuser) ? $account : null;
    }

    /** "Browser · System · IP · 14:32": what can be read about a device that asks. */
    public static function asking(\Systopic\System\Tables\SignInCodes\Model $code): string
    {
        $bits = [DeviceName::from((string) $code->userAgent), (string) $code->ip];
        if ($code->createdAt !== null) {
            $bits[] = \Systopic\System\Sys\LocalTime::format($code->createdAt, \Systopic\System\Sys\LocalTime::DATETIME);
        }
        return implode(' · ', array_filter($bits, static fn(string $bit): bool => $bit !== ''));
    }

    private static function label(mixed $fullname, mixed $name): string
    {
        $full = trim((string) $fullname);
        return $full !== '' ? $full : trim((string) $name);
    }
}
