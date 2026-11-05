<?php

declare(strict_types=1);

namespace Systopic\System\Tables\SignInCodes;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\System\Tables\Users\Model as User;

/**
 * A one-time code — `usm_signincodes`. Typed in (a link or QR code only fills
 * the field), so it also works in an installed iOS web app, which does not
 * share cookies with Safari.
 *
 * Only the HMAC of the code is stored, keyed with `auth.secret` from the
 * config: a code is looked up by it, and a leaked table alone gives nothing.
 *
 * A code has no purpose. It names an account from the start, or it is a
 * device code (`pollHash`, see `Auth\DeviceCode`) and gets its account when
 * someone approves it.
 */
final class Model extends TableRow
{
    public const TABLE        = 'signincodes';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'signincode';

    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 64, index: Index::Unique)]
    public ?string $codeHash {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** sha256 of the secret a device polls with. Only a device code has one. */
    #[Col(length: 64, index: Index::Plain)]
    public ?string $pollHash {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** A plain id, as `usm_users.user_created_by`: the creator may be gone. */
    #[Col(unsigned: true)]
    public ?int $createdBy {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $createdAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $expiresAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $usedAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Device code: when someone named its account, and who. */
    public ?DateTimeImmutable $approvedAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(unsigned: true)]
    public ?int $approvedBy {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Device code: what can be read about the device that asks, for the one who approves. */
    #[Col(length: 45)]
    public ?string $ip {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 255)]
    public ?string $userAgent {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
