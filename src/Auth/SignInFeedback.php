<?php
declare(strict_types=1);
namespace Systopic\System\Auth;

/** Only returned after authentication; shared by rendered and asynchronous logins. */
final class SignInFeedback
{
    public static function profile(): array
    {
        if (!Session::known()) return [];
        $identity = Session::current();
        $user = $identity->user;
        return [
            'screen' => $user !== null && $user->type === \Systopic\System\Tables\Users\Model::SCREEN,
            'name' => trim($identity->fullname) ?: $identity->name,
            'portrait' => $user === null ? '' : Portrait::src((int) $user->id, $user->email, $user->type === \Systopic\System\Tables\Users\Model::SCREEN),
        ];
    }
}
