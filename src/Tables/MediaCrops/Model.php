<?php

declare(strict_types=1);

namespace Systopic\System\Tables\MediaCrops;

use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Decimal;
use Systopic\System\Tables\Medias\Model as Media;

/**
 * One crop of one picture in one format — `cms_media_crops`.
 *
 * Two constants and not one name: the table is `cms_media_crops`, the columns
 * are prefixed `media_crop_`. `TABLE` and `PREFIX` are separate exactly for
 * this, and this is the first class in which they are not the same word.
 *
 * `$auto` is the interesting column: while it is set, the crop follows the
 * format's automatic rule, and the stored coordinates are the result of that
 * rule. The moment somebody drags the frame by hand it goes to 0 and the
 * coordinates become the truth.
 */
final class Model extends TableRow
{
    public const TABLE  = 'media_crops';
    public const PREFIX = 'media_crop';

    public ?Media $media {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** The format from `mediaFormats::add()`, or `original`. */
    public ?string $format = 'original' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 13, scale: 12)]
    public ?Decimal $left {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 13, scale: 12)]
    public ?Decimal $top {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 13, scale: 12)]
    public ?Decimal $width {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 13, scale: 12)]
    public ?Decimal $height {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Still following the format's rule? */
    public ?bool $auto = true {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Bumped when the derived file has to be written again. */
    public ?int $version = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
