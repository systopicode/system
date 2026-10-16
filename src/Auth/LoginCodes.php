<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use DateTimeImmutable;
use Systopic\Db\Scope;
use Systopic\System\Queries\AuthFailures\AuthFailures;
use Systopic\System\Queries\LoginCodes\LoginCodes as CodeQuery;
use Systopic\System\Tables\AuthFailures\Operator as FailureOperator;
use Systopic\System\Tables\LoginCodes\Model as LoginCode;
use Systopic\System\Tables\LoginCodes\Operator;
use Systopic\System\Tables\Users\Model as User;

/**
 * One-time codes: made for a purpose, typed into the app, spent once
 * (`Tables\LoginCodes`). The code in clear exists only as the return value
 * of `create()` - to be shown or mailed - and in the user's hands.
 *
 * Every failed redemption counts against the IP (`Tables\AuthFailures`); a
 * blocked IP gets nothing, not even a check.
 */
final class LoginCodes
{
    public const INVALID     = 'invalid';
    public const BLOCKED     = 'blocked';
    public const UNAVAILABLE = 'unavailable';

    /** Why the last call returned nothing: one of the constants, '' if it did not fail. */
    public static string $error = '';

    /** Codes need `auth.secret` (AuthConfig). */
    public static function available(): bool
    {
        return AuthConfig::secret() !== null;
    }

    /**
     * A new code for `$purpose` (Operator::INVITE …), saved; returns it
     * formatted (`K7Q4-M2XD`) - the only time it exists in clear.
     */
    public static function create(string $purpose, ?User $user, ?int $createdBy, ?string $pollHash = null): string
    {
        $secret = self::secretOrFail();
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        for ($try = 0; ; $try++) {
            $code = Operator::generate();
            $hash = Operator::hash($code, $secret);
            // 2^40 codes: a collision is next to impossible, but the column is unique
            if (CodeQuery::byHash($scope, $hash) === null || $try >= 3) {
                break;
            }
        }
        $scope->save(Operator::created($purpose, $user, $createdBy, $hash, $pollHash, $now));
        return Operator::format($code);
    }

    /**
     * What was typed, if it is a usable code of one of `$purposes` - spent
     * right here. null and `$error` otherwise.
     *
     * @param list<string> $purposes
     */
    public static function redeem(string $input, array $purposes): ?LoginCode
    {
        self::$error = '';
        $code = self::find($input, $purposes);
        if ($code === null) {
            return null;
        }
        Scope::default()->save(Operator::used($code, new DateTimeImmutable()));
        return $code;
    }

    /**
     * Whether what was typed (or came with a link) is a usable code of one of
     * `$purposes` - without spending it. For a page that only shows or carries
     * a code on. A miss counts against the IP like a failed redemption, so
     * peeking is no way around the throttle.
     *
     * @param list<string> $purposes
     */
    public static function peek(string $input, array $purposes): ?LoginCode
    {
        self::$error = '';
        return self::find($input, $purposes);
    }

    /**
     * A screen asks to be paired: a pair_screen code to show, and the secret
     * it polls with (`pollScreen()`), to be kept in a cookie.
     *
     * @return array{code: string, poll: string}
     */
    public static function startScreenPairing(): array
    {
        $poll = bin2hex(random_bytes(32));
        $code = self::create(Operator::PAIR_SCREEN, null, null, hash('sha256', $poll));
        return ['code' => $code, 'poll' => $poll];
    }

    /** A user admin approves the code a screen shows, for the screen account `$screen`. */
    public static function approveScreen(string $input, User $screen, int $approvedBy): bool
    {
        self::$error = '';
        if ($screen->type !== User::SCREEN) {
            throw new \InvalidArgumentException('login code: only a screen account can be paired with a screen code');
        }
        $code = self::find($input, [Operator::PAIR_SCREEN]);
        if ($code === null || $code->approvedAt !== null) {
            self::$error = $code === null ? self::$error : self::INVALID;
            return false;
        }
        Scope::default()->save(Operator::approved($code, $screen, $approvedBy, new DateTimeImmutable()));
        return true;
    }

    /**
     * The screen's poll: once approved, the code is spent and handed back -
     * the caller issues the device for its user. null while it waits;
     * `$error` = INVALID once it is gone or expired.
     */
    public static function pollScreen(string $poll): ?LoginCode
    {
        self::$error = '';
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $code = $poll === '' ? null : CodeQuery::byPollHash($scope, hash('sha256', $poll));
        if (!Operator::usable($code, [Operator::PAIR_SCREEN], $now)) {
            self::$error = self::INVALID;
            return null;
        }
        if ($code->approvedAt === null) {
            return null;
        }
        $scope->save(Operator::used($code, $now));
        return $code;
    }

    /** @param list<string> $purposes */
    private static function find(string $input, array $purposes): ?LoginCode
    {
        $secret = AuthConfig::secret();
        if ($secret === null) {
            self::$error = self::UNAVAILABLE;
            return null;
        }
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $failures = AuthFailures::of($scope, $ip);
        if (FailureOperator::blocked($failures, $now)) {
            self::$error = self::BLOCKED;
            return null;
        }
        $normalized = Operator::normalize($input);
        $code = $normalized === '' ? null : CodeQuery::byHash($scope, Operator::hash($normalized, $secret));
        if (!Operator::usable($code, $purposes, $now)) {
            $scope->save(FailureOperator::failed($failures, $ip, $now));
            self::$error = self::INVALID;
            return null;
        }
        return $code;
    }

    private static function secretOrFail(): string
    {
        return AuthConfig::secret() ?? throw new \RuntimeException('login codes: no "auth.secret" (32+ characters) in the config of this instance');
    }
}
