<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Devices;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Users\Model as User;

/**
 * A device a user is logged in on — `usm_devices`. One row per browser or
 * installed web app (or screen), so a user can be logged in on several at
 * once and each can be logged out alone.
 *
 * The cookie carries `<id>.<secret>`; here is only the sha256 of the secret.
 * It rotates at most once a day, and the one before stays good for a short
 * grace, because several requests can restore at the same time
 * (`Operator::verdict()`). Coming back after that grace is a stolen cookie.
 *
 * `prefs` belong to the device, not the user: Apple Maps on the phone,
 * Google Maps on the desktop.
 */
final class Model extends TableRow
{
    public const TABLE        = 'devices';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'device';

    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** "iPhone · Safari" from the user agent, renamable by the user. */
    #[Col(length: 100)]
    public ?string $name {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** 'browser', or 'app' once the page reported display-mode standalone. */
    #[Col(length: 10)]
    public ?string $client {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 255)]
    public ?string $userAgent {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** sha256 of the cookie's secret. */
    #[Col(length: 64)]
    public ?string $tokenHash {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** The secret before the last rotation — good until `rotatedAt` + grace. */
    #[Col(length: 64)]
    public ?string $prevTokenHash {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $rotatedAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $createdAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $lastSeen {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 45)]
    public ?string $lastIp {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Slides along with use; null: never (a screen). */
    public ?DateTimeImmutable $expiresAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $revokedAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** 'logout', 'user', 'admin', 'reuse' (a rotated secret came back). */
    #[Col(length: 20)]
    public ?string $revokedReason {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** JSON object. */
    public ?Text $prefs {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
