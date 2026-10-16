<?php

declare(strict_types=1);

namespace Systopic\System\Tables\AuthFailures;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;

/**
 * Failed code entries per IP — `usm_auth_failures`. A wrong code finds no
 * row (codes are looked up by their HMAC), so guessing can only be slowed
 * where it comes from: too many failures in a window block the IP for a
 * while (`Operator`).
 */
final class Model extends TableRow
{
    public const TABLE        = 'auth_failures';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'authfail';

    #[Col(length: 45, index: Index::Unique)]
    public ?string $ip {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(unsigned: true)]
    public ?int $count = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Start of the current window. */
    public ?DateTimeImmutable $since {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $blockedUntil {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
