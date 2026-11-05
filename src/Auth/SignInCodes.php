<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use DateTimeImmutable;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Queries\AuthFailures\AuthFailures;
use Systopic\System\Queries\SignInCodes\SignInCodes as CodeQuery;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\AuthFailures\Operator as FailureOperator;
use Systopic\System\Tables\SignInCodes\Model as SignInCode;
use Systopic\System\Tables\SignInCodes\Operator;
use Systopic\System\Tables\Users\Model as User;

/**
 * One-time codes, spent once (`Tables\SignInCodes`). The code in clear exists
 * only as the return value of `create()` - to be shown or mailed - and in
 * the user's hands.
 *
 * There are two kinds, and no purposes. What an account may do is in its
 * roles, not in the code.
 *
 *   code         made on a trusted device for one account, redeemed on the
 *                new device, which becomes that account
 *   device code  shown by a device that is nobody yet. Whoever reads it on a
 *                trusted device names the account; the device polls and
 *                signs in. See `Auth\DeviceCode`.
 *
 * Every failed redemption counts against the IP (`Tables\AuthFailures`); a
 * blocked IP gets nothing, not even a check.
 */
final class SignInCodes
{
    public const INVALID     = 'invalid';
    public const BLOCKED     = 'blocked';
    public const UNAVAILABLE = 'unavailable';

    /** The code was spent by the request that won the race. Not a failure. */
    public const SPENT       = 'spent';

    /** Why a code was made. Only the log reads this. */
    public const INVITATION = 'invitation';
    public const DEVICE     = 'device';
    public const INSTALL    = 'install';

    private const SESSION = 'profile_signin_code';

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
        echo json_encode(['alive' => $alive, 'used' => Session::known() && self::consumed($raw)]);
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
        return Operator::usable($row, new DateTimeImmutable());
    }

    /** Distinguish successful consumption from expiry for the authorizing dialog. */
    public static function consumed(string $input): bool
    {
        $secret = AuthConfig::secret();
        $normalized = Operator::normalize($input);
        if ($secret === null || $normalized === '' || strlen($input) > 32) return false;
        try {
            $row = CodeQuery::byHash(Scope::default(), Operator::hash($normalized, $secret));
            return $row?->usedAt !== null;
        } catch (\Throwable) { return false; }
    }

    /** Codes need `auth.secret`, which `AuthConfig` makes when there is none. */
    public static function available(): bool
    {
        return AuthConfig::secret() !== null;
    }

    /**
     * Someone asked for a code for `$account` to show: it stays in the session
     * until it is spent or runs out, so the page can still show it after the
     * redirect. Only the click makes one, never the page.
     *
     * @return array{code: string, expires: int, user: int}
     */
    public static function offer(User $account, int $by): array
    {
        \http::sessionStartOnce();
        $code = self::create($account, $by);
        return $_SESSION[self::SESSION] = [
            'user' => (int) $account->id,
            'code' => $code,
            'expires' => time() + Operator::LIFETIME,
        ];
    }

    /**
     * The code `offer()` left for this session, while it is good.
     *
     * @return array{code: string, expires: int, user: int}|null
     */
    public static function shown(): ?array
    {
        \http::sessionStartOnce();
        $held = $_SESSION[self::SESSION] ?? null;
        if (!is_array($held) || (int) ($held['expires'] ?? 0) <= time() || self::live((string) ($held['code'] ?? '')) === false) {
            unset($_SESSION[self::SESSION]);
            return null;
        }
        return $held;
    }

    /** The code on show leaves the page. It stays good until it runs out. */
    public static function dropOffer(): void
    {
        \http::sessionStartOnce();
        unset($_SESSION[self::SESSION]);
    }

    /**
     * A new code for `$user`, saved; returns it formatted (`K7Q4-M2XD`) - the
     * only time it exists in clear. `$by` is who asked for it.
     */
    public static function create(User $user, ?int $by, string $reason = self::DEVICE, int $lifetime = Operator::LIFETIME): string
    {
        $code = self::saved(static fn(string $hash, DateTimeImmutable $now): SignInCode => Operator::created($user, $by, $hash, null, $lifetime, $now));
        $target = self::label($user);
        $actor = self::actor();
        $what = $reason === self::INVITATION ? 'invitation' : ($reason === self::INSTALL ? 'install code' : 'code');
        SignInLog::write($actor !== '' ? $actor : ($target !== '' ? $target : '–'), 'created ' . $what . ' for ' . $target);
        return $code;
    }

    /** What was typed, if it is a usable code that names an account - spent right here. */
    public static function redeem(string $input): ?SignInCode
    {
        self::$error = '';
        self::purge();
        $code = self::find($input, false);
        if ($code === null || !self::claim($code, new DateTimeImmutable())) {
            if ($code !== null) {
                self::$error = self::INVALID;
            }
            return null;
        }
        $who = UserLookup::byId(Scope::default(), (int) $code->relationId('user'));
        $name = self::label($who);
        if ($name !== '') {
            SignInLog::write($name, 'redeemed code for ' . $name, SignInLog::CODE);
        }
        return $code;
    }

    /**
     * Whether what was typed (or came with a link) is a device code that
     * waits for someone - without spending it. A miss counts against the IP
     * like a failed redemption, so peeking is no way around the throttle.
     */
    public static function peekDeviceCode(string $input): ?SignInCode
    {
        self::$error = '';
        $code = self::find($input, true);
        if ($code !== null && $code->approvedAt !== null) {
            self::$error = self::INVALID;
            return null;
        }
        return $code;
    }

    /** Whether a scanned code of either kind is good. Reading a QR is not signing in. */
    public static function known(string $input): bool
    {
        self::$error = '';
        return self::find($input, null) !== null;
    }

    /**
     * A device asks to be let in: a device code to show, and the secret it
     * polls with, to be kept in a cookie.
     *
     * @return array{code: string, poll: string}
     */
    public static function startDeviceCode(): array
    {
        $poll = bin2hex(random_bytes(32));
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $agent = substr(DeviceName::requestAgent(), 0, 255);
        $code = self::saved(static fn(string $hash, DateTimeImmutable $now): SignInCode => Operator::created(
            null,
            null,
            $hash,
            hash('sha256', $poll),
            Operator::DEVICE_CODE_LIFETIME,
            $now,
            $ip,
            $agent,
        ));
        return ['code' => $code, 'poll' => $poll];
    }

    /**
     * A device gives its code up - it shows a new one (a reload). The code is
     * deleted, so nobody can approve it any more; one that was approved
     * meanwhile stays for `pollDeviceCode()`, which the caller asks first.
     */
    public static function cancelDeviceCode(string $poll): void
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

    /**
     * The signed-in `$by` says who the waiting device is: a person or a
     * screen. Their own session stays as it is. May `$by` name that account
     * is `SignInGrant`'s to say; this only keeps out what can never sign in.
     */
    public static function approve(string $input, User $account, User $by): bool
    {
        self::$error = '';
        if (!self::canSignIn($account) || (int) $by->id <= 1 || $by->type === User::SCREEN || !$by->isActive || $by->isBlocked) {
            self::$error = self::INVALID;
            return false;
        }
        $code = self::find($input, true);
        if ($code === null || $code->approvedAt !== null) {
            self::$error = $code === null ? self::$error : self::INVALID;
            return false;
        }
        Scope::default()->save(Operator::approved($code, $account, (int) $by->id, new DateTimeImmutable()));
        SignInLog::write(self::label($by), 'approved sign-in as ' . self::label($account), SignInLog::DEVICE_CODE);
        return true;
    }

    /** An account a code may sign in: active, not blocked, not the system's own. */
    public static function canSignIn(User $account): bool
    {
        return (int) $account->id > 1 && $account->isActive && !$account->isBlocked;
    }

    /**
     * The device's poll: once approved, the code is spent and handed back -
     * the caller issues the device for its user. null while it waits.
     * `$error` is SPENT when another request of this same code already won,
     * INVALID once it is gone or expired.
     */
    public static function pollDeviceCode(string $poll): ?SignInCode
    {
        self::$error = '';
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $code = $poll === '' ? null : CodeQuery::byPollHash($scope, hash('sha256', $poll));
        if ($code !== null && $code->usedAt !== null) {
            self::$error = self::SPENT;
            return null;
        }
        if (!Operator::usable($code, $now)) {
            self::$error = self::INVALID;
            return null;
        }
        if ($code->user?->isBlocked) {
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

    /** Hash, save, hand back the code in clear. `$row` builds the line. */
    private static function saved(\Closure $row): string
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
        $scope->save($row($hash, $now));
        return Operator::format($code);
    }

    /**
     * Spend the code only if nobody else has. Two polls of one approval used
     * to both see it unused and both create a device.
     */
    private static function claim(SignInCode $code, DateTimeImmutable $now): bool
    {
        $scope = Scope::default();
        $table = Schema::tableNameOf(SignInCode::class);
        $spent = $scope->connection->run(
            "UPDATE $table SET signincode_used_at = ? WHERE signincode_id = ? AND signincode_used_at IS NULL",
            [$now->format('Y-m-d H:i:s'), (int) $code->id],
        );
        if ($spent->rowCount() !== 1) {
            return false;
        }
        $code->usedAt = $now;
        return true;
    }

    /** `$deviceCode`: true only device codes, false only codes for an account, null either. */
    private static function find(string $input, ?bool $deviceCode): ?SignInCode
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
        $kind = $code === null || $deviceCode === null || Operator::isDeviceCode($code) === $deviceCode;
        if (!Operator::usable($code, $now) || !$kind || $code?->user?->isBlocked) {
            $scope->save(FailureOperator::failed($failures, $ip, $now));
            self::$error = self::INVALID;
            return null;
        }
        return $code;
    }

    private static function secretOrFail(): string
    {
        return AuthConfig::secret() ?? throw new \RuntimeException('sign-in codes: no "auth.secret" (32+ characters) in the config of this instance');
    }

    /** Used and expired rows go. The log stays. The table is created by the first save, so a missing one is skipped. */
    private static function purge(): void
    {
        try {
            $scope = Scope::default();
            $table = Schema::tableNameOf(SignInCode::class);
            $here = $scope->connection->value(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table],
            );
            if ((int) $here === 0) {
                return;
            }
            $scope->connection->run(
                "DELETE FROM $table WHERE signincode_used_at IS NOT NULL OR signincode_expires_at < ?",
                [(new DateTimeImmutable())->format('Y-m-d H:i:s')],
            );
        } catch (\Throwable $e) {
            error_log('sign-in codes purge: ' . $e->getMessage());
        }
    }

    private static function actor(): string
    {
        return Session::active() ? trim((string) Session::current()->name) : '';
    }

    private static function label(?User $user): string
    {
        if ($user === null || $user->isBlocked) {
            return '';
        }
        return trim((string) ($user->name ?: $user->email));
    }
}
