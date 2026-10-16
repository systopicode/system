<?php

declare(strict_types=1);

namespace Systopic\System\Tables\LoginCodes;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\System\Tables\Users\Model as User;

/**
 * A one-time code — `usm_login_codes`. Typed into the app (a link or QR code
 * only fills the field), so it also works in an installed iOS web app, which
 * does not share cookies with Safari.
 *
 * Only the HMAC of the code is stored, keyed with `auth.secret` from the
 * config: a code is looked up by it, and a leaked table alone gives nothing.
 *
 *   invite       a user admin invites a person, 24 h
 *   recover      back in after the app was deleted, from an admin or by mail, 10 min
 *   pair         "add a device" on a device that is logged in, 10 min
 *   pair_screen  a screen shows it, a user admin approves it, 10 min; the
 *                screen polls with its own secret (`pollHash`) and has no
 *                user until the approval names one
 */
final class Model extends TableRow
{
    public const TABLE        = 'login_codes';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'logincode';

    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 12)]
    public ?string $purpose {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 64, index: Index::Unique)]
    public ?string $codeHash {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** sha256 of the secret a pairing screen polls with. */
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

    /** pair_screen: when a user admin approved it, and who. */
    public ?DateTimeImmutable $approvedAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(unsigned: true)]
    public ?int $approvedBy {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
