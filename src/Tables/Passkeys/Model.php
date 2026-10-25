<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Passkeys;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Users\Model as User;

/** A public WebAuthn credential. No private key or biometric data. */
final class Model extends TableRow
{
    public const TABLE = 'passkeys';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX = 'passkey';

    #[Col(length: 64, index: Index::Plain)]
    public string $project = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 64, index: Index::Unique, name: 'passkey_credential_sha')]
    public string $credentialHash {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public Text $credentialId {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public Text $publicKey {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 253)]
    public string $rpId {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 64)]
    public string $userHandle {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 100)]
    public string $name = 'Passkey' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(unsigned: true)]
    public int $signCount = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $createdAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $lastUsedAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
