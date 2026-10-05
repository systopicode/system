<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Groups;

use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;

/**
 * A user group — `usm_groups`.
 *
 * Like User: belongs to the `users` module, not to the CMS. Fleshed out only
 * as far as Node needs it as a foreign key for now.
 */
final class Model extends TableRow
{
    public const TABLE  = 'groups';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX = 'group';

    /**
     * Looked up by name, and the database carries a **unique** index for it.
     *
     * Nullable, and that is the unique index talking rather than a preference:
     * two rows may both be NULL, but they may not both be `''`. Declaring this
     * `NOT NULL DEFAULT ''` would turn the two unnamed groups in this
     * installation into two empty strings and collide — 1062, which is exactly
     * how it was found.
     */
    #[Col(index: Index::Unique)]
    public ?string $name = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $icon = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $color = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** `text` in the database, so `Text` and not `string`. */
    public ?Text $description {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
