<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Users;

use DateTimeImmutable;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;

/**
 * A user — `usm_users`.
 *
 * Does not belong to the CMS: `usm_` is a prefix of its own already today, and
 * patchworkkit needs this table just as much. When the modules are split this
 * class moves to `systopic/users`, and the CMS depends on it.
 *
 * Only the columns belonging to the row itself live here. `checkAccess()` does
 * **not** come here: that is not a question about a user but about a relation
 * between user, subject and roles. The test for it: would the method still
 * make sense without the other type? `$user->fullname` yes,
 * `$user->checkAccess($panel)` no.
 *
 * Fleshed out only as far as Node needs it as a foreign key for now.
 */
final class Model extends TableRow
{
    public const TABLE  = 'users';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX = 'user';

    public ?string $name = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $fullname = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $email = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $alternativeEmail {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $languageIso {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The hash, never the password.
     *
     * Declared like any other column and deliberately not hidden: leaving it
     * out of the class does not make it safer, it only meant the audit read
     * the column as orphaned and offered to drop it — on a table where four of
     * five rows have one.
     */
    public ?string $passwordHash {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $activationKey {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $activationKeyExpire {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $firstLogin {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $lastLogin {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $createDate {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * Who created this user — an `int` and not a `?User`.
     *
     * The column is a plain `int` without a foreign key, and it holds ids of
     * users who may since have been deleted. Declaring it as a relation would
     * promise a row that is not always there.
     */
    public ?int $createdBy {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $sessiondata {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $browserSessionKey {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $sessionKey {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?bool $isSuperuser = false {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?bool $isActive = false {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
