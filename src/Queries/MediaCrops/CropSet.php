<?php

declare(strict_types=1);

namespace Systopic\System\Queries\MediaCrops;

use Systopic\System\Media\CropBox;
use Systopic\System\Media\CropResolver;
use Systopic\System\Media\MasterGeometry;
use Systopic\System\Media\Ratio;
use Systopic\System\Media\Variants;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\System\Tables\MediaCrops\Operator;
use Systopic\System\Tables\Medias\Model as MediaRow;

/**
 * A medium with everything that decides how it is cut: the master (crop and
 * look), the crops set by hand, the points of interest.
 *
 * Read once per medium (`MediaCrops::of()`), then asked as often as needed -
 * the renderer for one variant, a page for the sizes of its pictures, the
 * editor for every ratio on its slider. Nothing here touches the database or
 * a file.
 */
final class CropSet
{
    /** @var array<string, Crop> the rows by `media_crop_format` */
    public readonly array $rows;

    private ?MasterGeometry $master = null;
    /** @var list<CropBox>|null */
    private ?array $manual = null;

    /** @param list<Crop> $rows */
    public function __construct(public readonly MediaRow $media, array $rows)
    {
        $byFormat = [];
        foreach ($rows as $row) {
            $byFormat[(string) $row->format] = $row;
        }
        $this->rows = $byFormat;
    }

    /** The row of a ratio, or of the master (`Variants::MASTER`) - null when nothing was set by hand. */
    public function row(string $format): ?Crop
    {
        return $this->rows[$format] ?? null;
    }

    /** The look of the master: brightness, contrast, saturation, angle, mirrored. */
    public function look(): array
    {
        return Operator::look($this->row(Variants::MASTER));
    }

    /**
     * Where the master lies in the original, by the size the medium's row
     * holds. Null when that size is not known yet.
     */
    public function master(): ?MasterGeometry
    {
        if ($this->master !== null) {
            return $this->master;
        }
        $width = (int) $this->media->width;
        $height = (int) $this->media->height;
        return $width > 0 && $height > 0 ? $this->master = $this->masterOf($width, $height) : null;
    }

    /**
     * The same for an original of the given size - the renderer asks with
     * the pixels it actually read, which for a pdf or a video are those of
     * the raster it was drawn at.
     *
     * Without a master crop: the frame whose corners touch all four edges of
     * the turned picture - at a right angle that is the whole picture.
     */
    public function masterOf(int $width, int $height): MasterGeometry
    {
        $look = $this->look();
        $crop = Operator::box($this->row(Variants::MASTER));
        if ($crop === null) {
            $touching = MasterGeometry::touching($width, $height, $look['r'], (bool) $look['f']);
            if ($touching !== null) {
                return $touching;
            }
            // Near the diagonal there is no such frame: the picture's own ratio, as large as it fits.
            $crop = new CropBox(Ratio::ofPixels($width, $height));
        }
        return new MasterGeometry($width, $height, $crop, $look['r'], (bool) $look['f']);
    }

    /**
     * A token that changes whenever anything changes what the pictures of
     * this medium look like: a crop, the look, a point of interest, a new
     * original. It goes into every address (`?v=`) - finished files are
     * cached by browsers for a year, so a new crop has to be a new address.
     */
    public function version(): string
    {
        $state = [(int) $this->media->id, (int) $this->media->width, (int) $this->media->height,
            $this->media->modifiedDate?->getTimestamp(), (string) $this->media->pois];
        foreach ($this->rows as $format => $row) {
            $state[] = $format . ':' . (int) $row->version;
        }
        return dechex(crc32(json_encode($state)));
    }

    /** @return list<CropBox> the crops of ratios set by hand, relative to the master */
    public function manual(): array
    {
        if ($this->manual !== null) {
            return $this->manual;
        }
        $manual = [];
        foreach ($this->rows as $format => $row) {
            $box = $format === Variants::MASTER ? null : Operator::box($row);
            if ($box !== null) {
                $manual[] = $box;
            }
        }
        return $this->manual = $manual;
    }

    /**
     * The points of interest as stored: fractions of the original.
     *
     * @return list<array<string, mixed>>
     */
    public function pois(): array
    {
        $stored = $this->media->pois === null ? null : json_decode((string) $this->media->pois, true);
        return is_array($stored) ? array_values(array_filter($stored, 'is_array')) : [];
    }

    /**
     * The crop of a ratio in the master - set by hand, or computed
     * (`CropResolver`). Null without a master.
     */
    public function crop(Ratio $ratio): ?CropBox
    {
        $master = $this->master();
        return $master === null ? null : $this->cropIn($ratio, $master->width, $master->height);
    }

    /** The same in a master of the given pixels - the renderer asks with the master it holds. */
    public function cropIn(Ratio $ratio, int $masterWidth, int $masterHeight): CropBox
    {
        $pois = $this->pois();
        if ($pois !== []) {
            $pois = ($this->master() ?? MasterGeometry::whole($masterWidth, $masterHeight))->poisToMaster($pois);
        }
        return CropResolver::resolve($ratio, $masterWidth, $masterHeight, $this->manual(), $pois);
    }

    /**
     * Every declared ratio at once, by name - the editor's slider.
     *
     * @return array<string, CropBox>
     */
    public function crops(): array
    {
        $master = $this->master();
        if ($master === null) {
            return [];
        }
        return CropResolver::resolveAll(array_values(Variants::ratios()), $master->width, $master->height, $this->manual(), $master->poisToMaster($this->pois()));
    }
}
