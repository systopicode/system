<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use DateTimeImmutable;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Queries\AuthFailures\AuthFailures;
use Systopic\System\Queries\LoginCodes\LoginCodes as CodeQuery;
use Systopic\System\Queries\UserLookup\UserLookup;
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

    /** The code was spent by the request that won the race. Not a failure. */
    public const SPENT       = 'spent';

    /** Why the last call returned nothing: one of the constants, '' if it did not fail. */
    public static string $error = '';

    /**
     * Header `X-Code-Live` with the code in the body: is it still unused?
     * The lightbox that is showing it asks, and closes once the answer is no.
     * A spent check is not a failed sign-in.
     */
    public static function answerLive(): void
    {
        if (PHP_SAPI === 'cli' || !isset($_SERVER['HTTP_X_CODE_LIVE'])) {
            return;
        }
        $raw = (string) ($_POST['code'] ?? '');
        $alive = Session::known() && strlen($raw) <= 32 && self::live($raw) !== false;
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode(['alive' => $alive]);
        exit;
    }

    /**
     * Still unused and unexpired. Null when the table cannot be asked, so a
     * blip does not throw the code away or close the lightbox.
     */
    public static function live(string $input): ?bool
    {
        $secret = AuthConfig::secret();
        if ($secret === null) {
            return false;
        }
        $normalized = Operator::normalize($input);
        if ($normalized === '') {
            return false;
        }
        try {
            $row = CodeQuery::byHash(Scope::default(), Operator::hash($normalized, $secret));
        } catch (\Throwable) {
            return null;
        }
        return Operator::usable($row, [
            Operator::INVITE,
            Operator::RECOVER,
            Operator::PAIR,
            Operator::PAIR_USER,
            Operator::PAIR_SCREEN,
        ], new DateTimeImmutable());
    }

    /** Codes need `auth.secret` (AuthConfig). */
    public static function available(): bool
    {
        return AuthConfig::secret() !== null;
    }

    /**
     * The sign-in code this account is showing right now. The same one is
     * reused until it is spent or about to run out. A spent code must not
     * stay on the page: the next scan, still inside the ten minutes, would
     * be told the code is invalid.
     *
     * @return array{code: string, expires: int}|null
     */
    public static function shown(User $user): ?array
    {
        if (!self::available() || (int) $user->id <= 1) {
            return null;
        }
        \http::sessionStartOnce();
        $held = $_SESSION['profile_signin_code'] ?? null;
        if (
            is_array($held)
            && (int) ($held['user'] ?? 0) === (int) $user->id
            && (int) ($held['expires'] ?? 0) > time() + 15
            && is_string($held['code'] ?? null)
            && $held['code'] !== ''
            && self::live((string) $held['code']) !== false
        ) {
            return ['code' => $held['code'], 'expires' => (int) $held['expires']];
        }
        $code = self::create(Operator::RECOVER, $user, (int) $user->id);
        $expires = time() + Operator::LIFETIMES[Operator::RECOVER];
        $_SESSION['profile_signin_code'] = [
            'user' => (int) $user->id,
            'code' => $code,
            'expires' => $expires,
        ];
        return ['code' => $code, 'expires' => $expires];
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
        self::purge();
        for ($try = 0; ; $try++) {
            $code = Operator::generate();
            $hash = Operator::hash($code, $secret);
            // 2^40 codes: a collision is next to impossible, but the column is unique
            if (CodeQuery::byHash($scope, $hash) === null || $try >= 3) {
                break;
            }
        }
        $scope->save(Operator::created($purpose, $user, $createdBy, $hash, $pollHash, $now));
        self::wrote($purpose, $user);
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
        self::purge();
        $code = self::find($input, $purposes);
        if ($code === null || !self::claim($code, new DateTimeImmutable())) {
            if ($code !== null) {
                self::$error = self::INVALID;
            }
            return null;
        }
        $scope = Scope::default();
        $who = UserLookup::byId($scope, (int) $code->relationId('user'));
        $name = self::label($who);
        if ($name !== '') {
            LoginLog::write($name, match ($code->purpose) {
                Operator::INVITE => 'redeemed invitation for ' . $name,
                Operator::RECOVER => 'redeemed recovery code for ' . $name,
                Operator::PAIR => 'redeemed device code for ' . $name,
                default => 'redeemed code for ' . $name,
            }, match ($code->purpose) {
                Operator::INVITE => LoginLog::INVITATION,
                Operator::RECOVER => LoginLog::RECOVERY,
                Operator::PAIR => LoginLog::DEVICE,
                default => '',
            });
        }
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
    /**
     * A device asks to be let in: a pair_screen or pair_user code to show,
     * and the secret it polls with, to be kept in a cookie.
     *
     * @return array{code: string, poll: string}
     */
    public static function startWaiting(string $purpose): array
    {
        if ($purpose !== Operator::PAIR_SCREEN && $purpose !== Operator::PAIR_USER) {
            throw new \InvalidArgumentException('login code: ' . $purpose . ' is not a waiting code');
        }
        $poll = bin2hex(random_bytes(32));
        $code = self::create($purpose, null, null, hash('sha256', $poll));
        return ['code' => $code, 'poll' => $poll];
    }

    /** @return array{code: string, poll: string} */
    public static function startScreenPairing(): array
    {
        return self::startWaiting(Operator::PAIR_SCREEN);
    }

    /**
     * A screen gives its code up - it shows a new one (a reload). The code is
     * deleted, so nobody can approve it any more; one that was approved
     * meanwhile stays for `pollScreen()`, which the caller asks first.
     */
    public static function cancelScreenPairing(string $poll): void
    {
        if ($poll === '') {
            return;
        }
        $scope = Scope::default();
        $code = CodeQuery::byPollHash($scope, hash('sha256', $poll));
        if ($code !== null && $code->approvedAt === null) {
            $scope->remove($code);
        }
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
        LoginLog::write(self::actor(), 'approved screen sign-in as ' . self::label($screen), LoginLog::SCREEN);
        return true;
    }

    /** The signed-in person allows a remote login. The other device becomes them, not anyone else. */
    public static function approveSelf(string $input, User $me): bool
    {
        self::$error = '';
        if ((int) $me->id <= 1 || $me->type === User::SCREEN) {
            self::$error = self::INVALID;
            return false;
        }
        $code = self::find($input, [Operator::PAIR_USER]);
        if ($code === null || $code->approvedAt !== null) {
            self::$error = $code === null ? self::$error : self::INVALID;
            return false;
        }
        Scope::default()->save(Operator::approved($code, $me, (int) $me->id, new DateTimeImmutable()));
        $name = self::label($me);
        LoginLog::write($name, 'approved sign-in as ' . $name, LoginLog::REMOTE);
        return true;
    }

    /**
     * The screen's poll: once approved, the code is spent and handed back -
     * the caller issues the device for its user. null while it waits.
     * `$error` is SPENT when another request of this same code already won,
     * INVALID once it is gone or expired.
     */
    public static function pollScreen(string $poll): ?LoginCode
    {
        self::$error = '';
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $code = $poll === '' ? null : CodeQuery::byPollHash($scope, hash('sha256', $poll));
        if ($code !== null && $code->usedAt !== null) {
            self::$error = self::SPENT;
            return null;
        }
        if (!Operator::usable($code, [Operator::PAIR_SCREEN, Operator::PAIR_USER], $now)) {
            self::$error = self::INVALID;
            return null;
        }
        if ($code->approvedAt === null) {
            return null;
        }
        if (!self::claim($code, $now)) {
            self::$error = self::SPENT;
            return null;
        }
        return $code;
    }

    /**
     * Spend the code only if nobody else has. Two polls of one approval used
     * to both see it unused and both create a device.
     */
    private static function claim(LoginCode $code, DateTimeImmutable $now): bool
    {
        $scope = Scope::default();
        $table = Schema::tableNameOf(LoginCode::class);
        $spent = $scope->connection->run(
            "UPDATE $table SET logincode_used_at = ? WHERE logincode_id = ? AND logincode_used_at IS NULL",
            [$now->format('Y-m-d H:i:s'), (int) $code->id],
        );
        if ($spent->rowCount() !== 1) {
            return false;
        }
        $code->usedAt = $now;
        return true;
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

    /** Used and expired rows go. The login log stays. The table is created by the first save, so a missing one is skipped. */
    private static function purge(): void
    {
        try {
            $scope = Scope::default();
            $table = Schema::tableNameOf(LoginCode::class);
            $here = $scope->connection->value(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table],
            );
            if ((int) $here === 0) {
                return;
            }
            $scope->connection->run(
                "DELETE FROM $table WHERE logincode_used_at IS NOT NULL OR logincode_expires_at < ?",
                [(new DateTimeImmutable())->format('Y-m-d H:i:s')],
            );
        } catch (\Throwable $e) {
            error_log('login codes purge: ' . $e->getMessage());
        }
    }

    private static function wrote(string $purpose, ?User $user): void
    {
        $target = self::label($user);
        $action = match ($purpose) {
            Operator::INVITE => 'created invitation for ' . $target,
            Operator::RECOVER => 'created recovery code for ' . $target,
            Operator::PAIR => 'created device code for ' . $target,
            Operator::PAIR_USER => 'created remote login',
            Operator::PAIR_SCREEN => 'created screen pairing',
            default => 'created code',
        };
        $actor = self::actor();
        LoginLog::write($actor !== '' ? $actor : ($target !== '' ? $target : '–'), $action);
    }

    private static function actor(): string
    {
        return Session::active() ? trim((string) Session::current()->name) : '';
    }

    private static function label(?User $user): string
    {
        if ($user === null) {
            return '';
        }
        $name = trim((string) ($user->name ?: $user->email));
        return $name;
    }
}
