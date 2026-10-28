<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\LoginCodes\Operator as Codes;

/**
 * A device shows a one-time code and a QR. Someone approves it from a device
 * that is already signed in. This device polls and then signs in as the
 * account that was approved: a screen, or the person who tapped Allow.
 * The poll secret stays in a cookie only this device has.
 *
 * Every fresh page load replaces the code. One that was approved in the
 * meantime signs this device in instead.
 */
final class ScreenPair
{
    public const COOKIE = 'sys_pair';

    /** Kept stable so a screen that already has the cookie keeps polling. */
    public const SESSION = 'spaceticker_screen_pair';

    /**
     * Answer a poll, or on a normal GET start a new code.
     * `$redirect` is where the screen goes once it is paired.
     */
    public static function handle(string $redirect, string $purpose = Codes::PAIR_SCREEN): void
    {
        if (isset($_SERVER['HTTP_X_SCREEN_POLL'])) {
            self::answerPoll($redirect);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        self::begin($redirect, $purpose);
    }

    /** @return array{code: string, expires: int, redirect: string}|null */
    public static function current(): ?array
    {
        \http::sessionStartOnce();
        $pair = $_SESSION[self::SESSION] ?? null;
        return is_array($pair) && ($pair['expires'] ?? 0) > time() ? $pair : null;
    }

    /** The address the QR opens: the account page, which asks before it allows. */
    public static function url(string $code): string
    {
        return \http::$siteUrl . 'cms/user/?screen=' . rawurlencode($code);
    }

    public static function begin(string $redirect, string $purpose = Codes::PAIR_SCREEN): ?array
    {
        \http::sessionStartOnce();
        unset($_SESSION[self::SESSION]);
        if (!LoginCodes::available()) {
            return null;
        }
        $poll = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($poll !== '') {
            if (self::paired($poll)) {
                \http::redirect($redirect);
                return null;
            }
            // The poll next to this load already spent the code. A new one
            // here would throw away a sign-in that is landing right now.
            if (LoginCodes::$error === LoginCodes::SPENT) {
                if (Session::known()) {
                    \http::redirect($redirect);
                }
                return null;
            }
            LoginCodes::cancelScreenPairing($poll);
        }
        $start = LoginCodes::startWaiting($purpose);
        $expires = time() + (Codes::LIFETIMES[$purpose] ?? 600);
        self::cookie($start['poll'], $expires);
        $_SESSION[self::SESSION] = [
            'code' => $start['code'],
            'expires' => $expires,
            'redirect' => $redirect,
        ];
        return $_SESSION[self::SESSION];
    }

    private static function answerPoll(string $redirect): never
    {
        $poll = (string) ($_COOKIE[self::COOKIE] ?? '');
        $stored = self::current();
        $go = is_array($stored) && ($stored['redirect'] ?? '') !== '' ? (string) $stored['redirect'] : $redirect;
        $paired = self::paired($poll);
        if ($paired) {
            $back = Session::takeReturn();
            if (is_string($back) && $back !== '') {
                $go = $back;
            }
        }
        $answer = $paired
            ? ['paired' => true, 'redirect' => $go]
            : ['paired' => false, 'expired' => LoginCodes::$error !== ''];
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($answer);
        exit;
    }

    private static function paired(string $poll): bool
    {
        $code = LoginCodes::pollScreen($poll);
        if ($code === null) {
            // The other poll of this browser won the code and signed in.
            // Signing in again would be a second device.
            return LoginCodes::$error === LoginCodes::SPENT && Session::known();
        }
        $user = UserLookup::byId(\Systopic\Db\Scope::default(), (int) $code->relationId('user'));
        if ($user === null) {
            return false;
        }
        $screen = $code->purpose === Codes::PAIR_SCREEN;
        if ($screen && $user->type !== \Systopic\System\Tables\Users\Model::SCREEN) {
            return false;
        }
        if (!$screen && $user->type === \Systopic\System\Tables\Users\Model::SCREEN) {
            return false;
        }
        Session::login($user);
        Devices::issue($user);
        LoginLog::write((string) $user->name, 'logged in', $screen ? LoginLog::SCREEN : LoginLog::REMOTE);
        self::cookie('', time() - 3600);
        unset($_SESSION[self::SESSION]);
        return true;
    }

    private static function cookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value;
    }
}
