<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Medias;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Decimal;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Users\Model as User;

/**
 * A media file or a folder — `cms_medias`.
 *
 * Both at once, as the table has always had it: `media_type = 'folder'` marks
 * the folder, everything else is a file. The tree structure does not live here
 * but in `cms_nodes`, the same way as for pages — which is why a folder needs
 * no columns of its own.
 *
 * The four `livearea_*` values describe which part of the picture must stay
 * visible when a format crops. They are `decimal(13,12)` and therefore
 * `Decimal` and not `float`: 0 to 1 with twelve decimal places is exactly the
 * case where binary rounding would show.
 */
final class Model extends TableRow
{
    public const TABLE  = 'medias';
    public const PREFIX = 'media';

    public ?Node $node {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** `folder` or the kind of file. */
    #[Col(length: 100)]
    public ?string $type = 'folder' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $mimetype {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** The group from `settings.php` — `main`, `gallery`. */
    #[Col(length: 100)]
    public ?string $group {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $label {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?int $width = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?int $height = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 13, scale: 12)]
    public ?Decimal $liveareaLeft {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 13, scale: 12)]
    public ?Decimal $liveareaTop {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 13, scale: 12)]
    public ?Decimal $liveareaWidth {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 13, scale: 12)]
    public ?Decimal $liveareaHeight {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $backgroundType {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $filename {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $url {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $keywords {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?User $createdUser {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?User $modifiedUser {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $createdDate {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $modifiedDate {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
