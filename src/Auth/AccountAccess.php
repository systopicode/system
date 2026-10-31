<?php

declare(strict_types=1);
namespace Systopic\System\Auth;
use Systopic\System\Tables\Users\Model as User;

final class AccountAccess
{
    public static function sessionValid(User $user, string $key): bool
    {
        return !$user->isBlocked && hash_equals((string) $user->sessionKey, $key);
    }
    public static function suspend(User $user): void
    {
        $user->isBlocked = true;
        $user->sessionKey = bin2hex(random_bytes(32));
        $user->activationKey = null;
        $user->activationKeyExpire = null;
    }
}
