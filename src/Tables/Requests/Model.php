<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Requests;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Binary;
use Systopic\System\Tables\Users\Model as User;

/**
 * One logged request — `log_requests`.
 *
 * `TABLE_PREFIX = 'log'`: the log tables are their own group, and they are the
 * one part of the schema that grows without anybody editing anything. Which is
 * why all three foreign keys here are nullable — a request to a path that
 * resolves to nothing still gets written down.
 *
 * `$ip` is `varbinary(16)` and therefore `Binary`, not a string: what is
 * stored is the result of `inet_pton()`, four bytes for IPv4 and sixteen for
 * IPv6. In a `varchar` those bytes would get a collation and would not survive
 * the first charset conversion.
 */
final class Model extends TableRow
{
    public const TABLE        = 'requests';
    public const TABLE_PREFIX = 'log';
    public const PREFIX       = 'request';

    public ?string $type {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The path and the page of a page request — plain ids, not relations: the
     * log is the system's (logins write here too), pages and paths belong to
     * systopic/cms. The foreign keys stay in the database.
     */
    #[Col(unsigned: true)]
    public ?int $rewritepathId {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(unsigned: true)]
    public ?int $pageId {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Packed by `inet_pton()` — 4 bytes for IPv4, 16 for IPv6. */
    #[Col(length: 16)]
    public ?Binary $ip {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $datetime {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $deviceType {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
