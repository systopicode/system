<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use DateTimeImmutable;
use Systopic\Db\Scope;
use Systopic\System\Queries\AuthFailures\AuthFailures;
use Systopic\System\Tables\AuthFailures\Operator as FailureOperator;

/**
 * What sends a mail to a typed address, registration and password reset,
 * counts against the IP like a failed sign-in. Otherwise anybody could fill
 * inboxes and the user table from a script.
 */
final class Throttle
{
    /** false: this IP is blocked. Otherwise the attempt is counted. */
    public static function spend(): bool
    {
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $failures = AuthFailures::of($scope, $ip);
        if (FailureOperator::blocked($failures, $now)) {
            return false;
        }
        $scope->save(FailureOperator::failed($failures, $ip, $now));
        return true;
    }
}
