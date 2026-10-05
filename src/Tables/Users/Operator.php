<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Users;

use DateTimeImmutable;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\UsersAtRoles\Model as UserAtRole;

/**
 * What can be done to users — nothing here saves.
 */
final class Operator
{
    /** Columns the user list can be sorted by: request key => property. */
    public const SORTABLE = ['id' => 'id', 'name' => 'name', 'email' => 'email', 'last_login' => 'lastLogin'];

    /**
     * A new user, created by `$createdBy`, holding `$role` if one is given.
     *
     * The name carries the id, which exists only after the insert: save what
     * this returns, then save `named()` of the first row.
     *
     * @return list<Model|UserAtRole> the user first
     */
    public static function create(?int $createdBy, ?Role $role): array
    {
        $user = new Model();
        $user->name = '##New User';
        $user->createdBy = $createdBy;
        $user->createDate = new DateTimeImmutable();
        if ($role === null) {
            return [$user];
        }
        $link = new UserAtRole();
        $link->user = $user;
        $link->role = $role;
        return [$user, $link];
    }

    /** The placeholder name of a new user, now that it has an id. */
    public static function named(Model $user): Model
    {
        $user->name = "##New User #{$user->id}";
        return $user;
    }

    /**
     * The users in the order of one of the `SORTABLE` keys; an unknown key is
     * the id. NULL sorts first ascending.
     *
     * @param list<Model> $users
     * @return list<Model>
     */
    public static function sort(array $users, string $key, bool $desc): array
    {
        $property = self::SORTABLE[$key] ?? 'id';
        usort($users, static function (Model $a, Model $b) use ($property): int {
            $x = $a->$property;
            $y = $b->$property;
            return is_string($x) && is_string($y) ? strcasecmp($x, $y) : $x <=> $y;
        });
        return $desc ? array_reverse($users) : $users;
    }

    /** The fields the user editor writes. */
    public const EDITABLE = ['name', 'fullname', 'email'];

    /** One field from the editor — only those in `EDITABLE`. */
    public static function assign(Model $user, string $field, string $value): Model
    {
        if (!in_array($field, self::EDITABLE, true)) {
            throw new \InvalidArgumentException("user field $field is not editable");
        }
        $user->$field = trim($value);
        return $user;
    }

    /** A fresh activation key, valid for `$days` days. */
    public static function newActivationKey(Model $user, int $days = 1): Model
    {
        $user->activationKey = bin2hex(random_bytes(16));
        $user->activationKeyExpire = new DateTimeImmutable("+$days day");
        return $user;
    }

    /** Last seen now — at logout. */
    public static function stamped(Model $user): Model
    {
        $user->lastLogin = new DateTimeImmutable();
        return $user;
    }

    /** Two letters for the signet: first and last name, else the user name. */
    public static function initials(Model $user): string
    {
        $parts = preg_split('~\s+~', trim((string) $user->fullname), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) > 1) {
            return mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1);
        }
        return mb_strtoupper(mb_substr((string) $user->name, 0, 2));
    }

    /** The stored form of a password — `Auth\Password` (password_hash; md5 of before upgraded at login). */
    public static function hash(string $password): string
    {
        return \Systopic\System\Auth\Password::hash($password);
    }

    /**
     * What is wrong with a new password — empty if nothing. `$strict` adds
     * the complexity rules (live stage).
     *
     * @return list<string>
     */
    public static function passwordProblems(string $password, string $repeat, bool $strict): array
    {
        if ($password === '') {
            return ['password empty'];
        }
        if ($password !== $repeat) {
            return ["Passwords don't match."];
        }
        if (!$strict) {
            return [];
        }
        $rules = [
            '~.{5}~'          => 'too short (5 chars or more)',
            '~[A-Z]~'         => 'no uppercase letter found',
            '~[0-9]~'         => 'no number found',
            '~[^A-Za-z0-9]~'  => 'no special char found',
        ];
        return array_values(array_filter($rules, static fn(string $rule): bool => !preg_match($rule, $password), ARRAY_FILTER_USE_KEY));
    }

    public static function setPassword(Model $user, string $password): Model
    {
        $user->passwordHash = self::hash($password);
        return $user;
    }

    /** Whether the key of an activation or reset mail is still good. */
    public static function keyValid(Model $user): bool
    {
        return $user->activationKey !== null && $user->activationKeyExpire !== null && $user->activationKeyExpire > new DateTimeImmutable();
    }

    /**
     * A password set through the key of a mail: the key is used up, the
     * account is active.
     */
    public static function activate(Model $user, string $password): Model
    {
        self::setPassword($user, $password);
        $user->activationKey = null;
        $user->activationKeyExpire = null;
        $user->isActive = true;
        return $user;
    }

    /**
     * A self-registered account: named after the part of the address before
     * the @, or the whole address if that name is taken.
     */
    public static function register(string $email, bool $nameTaken): Model
    {
        $user = new Model();
        $user->email = $email;
        $user->name = $nameTaken ? $email : strtolower(strstr($email, '@', true) ?: $email);
        $user->createDate = new DateTimeImmutable();
        return self::newActivationKey($user);
    }

    /** The very first account of an installation — a superuser. */
    public static function firstSuperuser(string $name, string $password): Model
    {
        $user = new Model();
        $user->name = $name;
        $user->isSuperuser = true;
        $user->isActive = true;
        $user->createDate = new DateTimeImmutable();
        return self::setPassword($user, $password);
    }

    /**
     * Make a user superuser or take it back. Nobody takes it from themselves:
     * the last superuser would lock everybody out.
     *
     * @return list<Model> what to save
     */
    public static function superuser(Model $user, bool $on, ?int $actingId): array
    {
        if (!$on && (int) $user->id === (int) $actingId) {
            return [];
        }
        $user->isSuperuser = $on;
        return [$user];
    }
}
