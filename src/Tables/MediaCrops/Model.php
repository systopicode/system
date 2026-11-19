<?php

declare(strict_types=1);

namespace Systopic\System\Tables\MediaCrops;

use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Decimal;
use Systopic\Db\Type\Text;
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

    /**
     * The ratio of the picture this crop makes, a reduced fraction (`16:9`).
     * For the crop of a ratio the same as `$format`; for the master
     * (`$format` = `original`) its own, free one (`753:1364`).
     */
    #[Col(length: 23)]
    public ?string $ratio {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * What alignment and scale are read on (`Media\CropBox`): `fill` - the
     * edge of the picture stays outside, scale 1 = "fill in"; `fit` -
     * background may show, scale 1 = "fit in". Null is `fill`.
     */
    #[Col(length: 4)]
    public ?string $basis = 'fill' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * How the crop is aligned in what it is cut from (the master for the
     * crop of a ratio, the turned original for the master), 0-1: in `fill`
     * as with `background-position` in per cent (0 flush left / top, 1 flush
     * right / bottom), in `fit` from the picture touching the crop on the
     * left / top to touching it on the right / bottom; 0.5 centred in both
     * (`Media\CropBox`). Successor of `$left` / `$top`.
     */
    #[Col(length: 15, scale: 12)]
    public ?Decimal $alignX {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 15, scale: 12)]
    public ?Decimal $alignY {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * Magnification over scale 1 of the basis: "fill in", the largest crop
     * of the ratio without a border, or "fit in". Successor of `$width` and
     * `$height` - one value cannot stretch the picture, two could.
     */
    #[Col(length: 18, scale: 12)]
    public ?Decimal $scale {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The look of the master, JSON: `{"b":100,"c":100,"s":100,"r":0,"f":0}` -
     * brightness, contrast, saturation (100 = as it is), angle in degrees,
     * mirrored. Only in the master's row; null = untouched.
     */
    public ?Text $edit {
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
