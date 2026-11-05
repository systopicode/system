<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as User;

/**
 * A device that is nobody yet shows a code and a QR. Someone reads it on a
 * device that is signed in and says who this device is: a person or a
 * screen. This device polls and then signs in as that account. The poll
 * secret stays in a cookie only this device has, so seeing the code is not
 * enough to get the sign-in.
 *
 * Every fresh page load replaces the code. One that was approved in the
 * meantime signs this device in instead.
 */
final class DeviceCode
{
    public const COOKIE = 'sys_device_code';

    private const SESSION = 'system_device_code';

    /** Same project boundary as the session and persistent device cookie. */
    public static function cookieName(): string
    {
        $key = \http::projectCookieKey();
        return self::COOKIE . ($key === null ? '' : '_' . $key);
    }

    /** Answer a poll, or on a normal GET start a new code. */
    public static function handle(): void
    {
        if (isset($_SERVER['HTTP_X_DEVICE_CODE_RENEW'])) {
            self::answerRenewal();
        }
        if (isset($_SERVER['HTTP_X_DEVICE_CODE_POLL'])) {
            self::answerPoll();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        self::begin();
    }

    /** @return array{code: string, expires: int}|null */
    public static function current(): ?array
    {
        \http::sessionStartOnce();
        $pair = $_SESSION[self::SESSION] ?? null;
        return is_array($pair) && ($pair['expires'] ?? 0) > time() ? $pair : null;
    }

    /** The address the QR opens: the account page, which asks before it allows. */
    public static function url(string $code): string
    {
        return \http::$siteUrl . 'admin/user/?deviceCode=' . rawurlencode($code);
    }

    /** @return array{code: string, expires: int}|null */
    public static function begin(): ?array
    {
        \http::sessionStartOnce();
        unset($_SESSION[self::SESSION]);
        if (!SignInCodes::available()) {
            return null;
        }
        $poll = (string) ($_COOKIE[self::cookieName()] ?? '');
        if ($poll !== '') {
            $user = self::approved($poll);
            if ($user !== null) {
                \http::redirect(self::target($user));
                return null;
            }
            // The poll next to this load already spent the code. A new one
            // here would throw away a sign-in that is landing right now.
            if (SignInCodes::$error === SignInCodes::SPENT) {
                return null;
            }
            SignInCodes::cancelDeviceCode($poll);
        }
        $start = SignInCodes::startDeviceCode();
        $expires = time() + \Systopic\System\Tables\SignInCodes\Operator::DEVICE_CODE_LIFETIME;
        // A little longer than the code: the renewing load still finds the old row and removes it.
        self::cookie($start['poll'], $expires + 60);
        return $_SESSION[self::SESSION] = ['code' => $start['code'], 'expires' => $expires];
    }

    /** A screen stays on the start page. A person goes where they were headed. */
    private static function target(User $user): string
    {
        if ($user->type === User::SCREEN) {
            return \http::$root;
        }
        $back = Session::takeReturn();
        return is_string($back) && $back !== '' ? $back : \http::$root . 'admin/';
    }

    /** Only this block changes; the card and surrounding document stay in place. */
    private static function answerRenewal(): never
    {
        if (Session::known()) self::answerPoll();
        $authPair = self::begin();
        $qr = $authPair !== null && class_exists('qr')
            ? \qr::svgMarkup(\qr::payload('url', ['url' => self::url($authPair['code'])]), 4, 'M', 'qr-code') : '';
        if ($qr === '') {
            http_response_code(503);
            $answer = ['error' => true];
        } else {
            ob_start();
            include __DIR__ . '/device-code.tpl.php';
            $answer = ['html' => (string) ob_get_clean()];
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($answer);
        exit;
    }

    private static function answerPoll(): never
    {
        $user = Session::known()
            ? UserLookup::byId(\Systopic\Db\Scope::default(), (int) Session::id())
            : self::approved((string) ($_COOKIE[self::cookieName()] ?? ''));
        $answer = $user !== null
            ? ['paired' => true, 'redirect' => self::target($user), 'profile' => SignInFeedback::profile()]
            : ['paired' => false, 'expired' => SignInCodes::$error !== ''];
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($answer);
        exit;
    }

    /** Signs this device in once someone named its account, and says who that is. */
    private static function approved(string $poll): ?User
    {
        $scope = \Systopic\Db\Scope::default();
        $code = SignInCodes::pollDeviceCode($poll);
        if ($code === null) {
            // The other poll of this browser won the code and signed in.
            // Signing in again would be a second device.
            return SignInCodes::$error === SignInCodes::SPENT && Session::known()
                ? UserLookup::byId($scope, (int) Session::id())
                : null;
        }
        $user = UserLookup::byId($scope, (int) $code->relationId('user'));
        if ($user === null || !SignInCodes::canSignIn($user)) {
            return null;
        }
        Session::login($user);
        Devices::issue($user);
        SignInLog::write((string) $user->name, 'signed in', SignInLog::DEVICE_CODE);
        SecurityNotice::deviceSignedIn($user, $code);
        self::cookie('', time() - 3600);
        unset($_SESSION[self::SESSION]);
        return $user;
    }

    private static function cookie(string $value, int $expires): void
    {
        setcookie(self::cookieName(), $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::cookieName()] = $value;
    }
}
