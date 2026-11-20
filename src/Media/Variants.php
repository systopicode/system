<?php

declare(strict_types=1);

namespace Systopic\System\Media;

/**
 * What a picture is made in: the aspect ratios and the sizes of a project,
 * and the names the files of those variants carry.
 *
 * From `packages.system.media` in config.json (`ratios`, `sizes`, with
 * the lists below as their defaults in the system's config.schema.json), or
 * from `Variants::configure()` in a `settings.php`. Without a config (a
 * test, the setup page) the lists below.
 *
 * The format names a template asks for with `setFormat()` are aliases of a
 * ratio and a size: `packages.system.media.aliases`, added to `mediaFormats`
 * by `addAliases()` (a settings.php may still add its own).
 *
 *   ratios   the crops an editor can set and a template can ask for
 *   sizes    megapixels; every ratio (and the master) exists in each of
 *            them, as far as the picture has the pixels
 *
 * Only what is declared here is ever rendered - an address is checked
 * against these lists, so nobody fills the disk by counting ratios up in a
 * url. The master's own ratio is free, and is in no address.
 *
 * File names: `<name>--<variant>.<type>` in one folder per medium.
 *
 *   --master        the master, its own pixels
 *   --2mp           the master, about 2 megapixels
 *   --16by9         the crop 16:9, its own pixels
 *   --2mp16by9      the crop 16:9, about 2 megapixels
 *   --original      the untouched original at screen size (the editor's)
 */
final class Variants
{
    /**
     * Wide to narrow - the order of the editor's slider, top to bottom.
     * The same as the default of config.schema.json.
     */
    public const RATIOS = ['2:1', '16:9', '3:2', '4:3', '1:1', '3:4', '2:3', '9:16', '1:2'];

    /** Megapixels. The smallest is the preview: made at once, kept in backups. */
    public const SIZES = [0.5, 1, 2, 5, 10];

    /** `media_crop_format` of the master's row. */
    public const MASTER = 'original';

    /**
     * What config.json says under `packages.system.media`: `ratios`, `sizes`,
     * `aliases` - with the defaults of the schema. Empty without a config.
     *
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        $media = \Systopic\System\Config\Config::current()?->package('system')['media'] ?? null;
        return is_array($media) ? $media : [];
    }

    /** A list of the config, also when it was typed as one value ("16:9"). */
    private static function listOf(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        return is_scalar($value) && (string) $value !== '' ? [$value] : null;
    }

    /**
     * The format names of config.json into `mediaFormats`, beside the
     * system's and those a settings.php added (which win). Run once by
     * loader.php.
     */
    public static function addAliases(): void
    {
        $aliases = self::config()['aliases'] ?? null;
        if (!is_array($aliases) || $aliases === [] || !class_exists('mediaFormats')) {
            return;
        }
        $formats = [];
        foreach ($aliases as $name => $row) {
            if (is_string($name) && $name !== '' && is_array($row)) {
                $formats[$name] = $row + ['cms_display' => 'image'];
            }
        }
        \mediaFormats::add($formats);
    }

    /**
     * The fixed formats: pictures of exact pixels, one file each
     * (`<name>--shareImage.jpg`), with a crop of their own - computed from
     * the crops of the ratios until one is set (`CropSet::formatCrop()`).
     * `packages.system.media.formats`, by default the share image.
     *
     * @return array<string, array{name: string, label: string, width: int, height: int, type: string, ratio: Ratio}>
     */
    public static function formats(): array
    {
        return self::$formats ??= self::readFormats(self::config()['formats'] ?? self::FORMATS);
    }

    /** The fixed formats from a list like the config's - a settings.php, a test. An empty list: none. */
    public static function configureFormats(array $formats): void
    {
        self::$formats = self::readFormats($formats);
    }

    /** What reads as a fixed format; the rest is left out, not guessed at. */
    private static function readFormats(mixed $config): array
    {
        $formats = [];
        foreach (is_array($config) ? $config : [] as $name => $row) {
            $width = is_array($row) && is_numeric($row['width'] ?? null) ? (int) $row['width'] : 0;
            $height = is_array($row) && is_numeric($row['height'] ?? null) ? (int) $row['height'] : 0;
            // A letter first: a name is never read as a variant (2mp16by9, master).
            if (!is_string($name) || !preg_match('~^[a-z][a-zA-Z0-9]*$~D', $name) || in_array($name, [self::MASTER, 'master'], true)
                || $width <= 0 || $height <= 0 || $width * $height > self::MAX_MEGAPIXELS * 1e6) {
                continue;
            }
            $type = is_string($row['type'] ?? null) && isset(VariantRenderer::TYPES[$row['type']]) ? $row['type'] : 'jpg';
            $label = trim((string) ($row['label'] ?? ''));
            $formats[$name] = ['name' => $name, 'label' => $label !== '' ? $label : $name, 'width' => $width, 'height' => $height,
                'type' => $type, 'ratio' => Ratio::ofPixels($width, $height)];
        }
        return $formats;
    }

    /** The fixed format of that name - null for any other. */
    public static function format(string $name): ?array
    {
        return self::formats()[$name] ?? null;
    }

    /** Without a config: the share image (the system's default of `formats`). */
    private const FORMATS = ['shareImage' => ['label' => 'Share image', 'width' => 1200, 'height' => 630, 'type' => 'jpg']];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $formats = null;

    /** A size above this is a typing error, not a wish. */
    private const MAX_MEGAPIXELS = 100;

    /** @var array<string, Ratio>|null */
    private static ?array $ratios = null;
    /** @var list<float>|null */
    private static ?array $sizes = null;

    /**
     * Null keeps what is set, an empty list goes back to the default. What
     * does not read as a ratio or a size is left out, not guessed at.
     *
     * @param array<mixed>|null $ratios `16:9`, `1.91:1` …
     * @param array<mixed>|null $sizes megapixels
     */
    public static function configure(?array $ratios, ?array $sizes = null): void
    {
        if ($ratios !== null) {
            $declared = [];
            foreach ($ratios as $value) {
                $ratio = is_scalar($value) ? Ratio::of((string) $value) : null;
                if ($ratio !== null) {
                    $declared[$ratio->name] = $ratio;
                }
            }
            uasort($declared, static fn(Ratio $a, Ratio $b): int => Ratio::compare($b, $a));
            self::$ratios = $declared !== [] ? $declared : null;
        }
        if ($sizes !== null) {
            $declared = [];
            foreach ($sizes as $value) {
                if (is_numeric($value) && $value > 0 && $value <= self::MAX_MEGAPIXELS) {
                    $declared[self::megapixels((float) $value)] = (float) $value;
                }
            }
            sort($declared);
            self::$sizes = $declared !== [] ? array_values($declared) : null;
        }
    }

    /** @return array<string, Ratio> by name, wide to narrow */
    public static function ratios(): array
    {
        if (self::$ratios === null) {
            self::configure(self::listOf(self::config()['ratios'] ?? null) ?? self::RATIOS);
        }
        if (self::$ratios === null) {
            self::configure(self::RATIOS);
        }
        return self::$ratios;
    }

    /** The declared ratio of that name, in any spelling `Ratio::of()` reads - else null. */
    public static function ratio(string $name): ?Ratio
    {
        $ratio = Ratio::of($name);
        return $ratio === null ? null : (self::ratios()[$ratio->name] ?? null);
    }

    /** @return list<float> megapixels, small to large */
    public static function sizes(): array
    {
        if (self::$sizes === null) {
            self::configure(null, self::listOf(self::config()['sizes'] ?? null) ?? self::SIZES);
        }
        if (self::$sizes === null) {
            self::configure(null, self::SIZES);
        }
        return self::$sizes;
    }

    /** The preview size - what every medium has from the start. */
    public static function smallest(): float
    {
        return self::sizes()[0];
    }

    /**
     * Width and height of a ratio at a size: the same number of pixels for
     * every ratio, so no crop data is needed to know them.
     *
     * @return array{0: int, 1: int}
     */
    public static function pixels(Ratio $ratio, float $megapixels): array
    {
        $width = sqrt($megapixels * 1e6 * $ratio->value);
        return [max(1, (int) round($width)), max(1, (int) round($width / $ratio->value))];
    }

    /**
     * The sizes a picture of the given pixels can be made in - never larger
     * than itself. Always at least the smallest, so there is a preview of a
     * tiny picture too (in its own pixels then).
     *
     * @return list<float>
     */
    public static function sizesWithin(int $width, int $height): array
    {
        $own = $width * $height / 1e6;
        $fitting = array_values(array_filter(self::sizes(), static fn(float $size): bool => $size <= $own));
        return $fitting !== [] ? $fitting : [self::smallest()];
    }

    /** The variant part of a file name: `2mp16by9`, `16by9`, `2mp`, `master`. */
    public static function suffix(?Ratio $ratio = null, ?float $megapixels = null): string
    {
        $suffix = ($megapixels !== null ? self::megapixels($megapixels) . 'mp' : '') . ($ratio?->suffix ?? '');
        return $suffix !== '' ? $suffix : 'master';
    }

    /**
     * Reads the variant part of a file name back. Null for everything that
     * is not declared - an unknown ratio, a size not on the list, text.
     *
     * @return array{ratio: ?Ratio, megapixels: ?float, original: bool}|null
     *         ratio null = the master, megapixels null = its own pixels
     */
    public static function parse(string $suffix): ?array
    {
        if ($suffix === 'master' || $suffix === 'original') {
            return ['ratio' => null, 'megapixels' => null, 'original' => $suffix === 'original'];
        }
        if ($suffix === '' || !preg_match('~^(?:(\d+(?:\.\d+)?)mp)?(?:(\d+)by(\d+))?$~D', $suffix, $m)) {
            return null;
        }
        $megapixels = null;
        if (($m[1] ?? '') !== '') {
            foreach (self::sizes() as $size) {
                if (self::megapixels($size) === $m[1]) {
                    $megapixels = $size;
                }
            }
            if ($megapixels === null) {
                return null;
            }
        }
        $ratio = null;
        if (($m[2] ?? '') !== '') {
            // Only the one spelling that suffix() writes: 32by18 is not a second name for 16by9.
            $ratio = self::ratios()[$m[2] . ':' . $m[3]] ?? null;
            if ($ratio === null) {
                return null;
            }
        }
        return ['ratio' => $ratio, 'megapixels' => $megapixels, 'original' => false];
    }

    /**
     * The declared ratio nearest to any other one, on the logarithmic scale -
     * what a named format of an odd size (1788 x 2160) is shown in.
     */
    public static function nearestRatio(Ratio $ratio): Ratio
    {
        $nearest = null;
        $distance = INF;
        foreach (self::ratios() as $declared) {
            $d = abs(log($declared->value) - log($ratio->value));
            if ($d < $distance) {
                [$nearest, $distance] = [$declared, $d];
            }
        }
        return $nearest;
    }

    /**
     * The declared size for a wish in megapixels: the smallest that is not
     * noticeably below it, else the largest there is.
     */
    public static function sizeFor(float $megapixels): float
    {
        foreach (self::sizes() as $size) {
            if ($size >= $megapixels * 0.9) {
                return $size;
            }
        }
        $sizes = self::sizes();
        return (float) end($sizes);
    }

    /**
     * A named format of the settings (`mediaFormats`) as the variant it
     * stands for from now on. The name stays valid in templates and stored
     * content; it no longer has a crop or an exact pixel size of its own.
     *
     *   megapixels + aspect_ratio   that ratio's nearest declared one, that size
     *   megapixels alone            the master at that size
     *   width + height              the nearest declared ratio, the size that holds the pixels
     *   width or height alone       the master, sized for a usual 3:2 picture
     *   nothing                     the master in its own pixels
     *
     * @param array<string, mixed> $format
     * @return array{ratio: ?Ratio, megapixels: ?float} ratio null = the master
     */
    public static function alias(array $format): array
    {
        $megapixels = is_numeric($format['megapixels'] ?? null) ? (float) $format['megapixels'] : 0.0;
        $width = is_numeric($format['width'] ?? null) ? (int) $format['width'] : 0;
        $height = is_numeric($format['height'] ?? null) ? (int) $format['height'] : 0;
        if ($megapixels > 0) {
            $ratio = Ratio::of((string) ($format['aspect_ratio'] ?? ''));
            return ['ratio' => $ratio === null ? null : self::nearestRatio($ratio), 'megapixels' => self::sizeFor($megapixels)];
        }
        if ($width > 0 && $height > 0) {
            return ['ratio' => self::nearestRatio(Ratio::ofPixels($width, $height)), 'megapixels' => self::sizeFor($width * $height / 1e6)];
        }
        if ($width > 0) {
            return ['ratio' => null, 'megapixels' => self::sizeFor($width * $width / 1.5 / 1e6)];
        }
        if ($height > 0) {
            return ['ratio' => null, 'megapixels' => self::sizeFor($height * $height * 1.5 / 1e6)];
        }
        return ['ratio' => null, 'megapixels' => null];
    }

    /** Back to the defaults - for tests. */
    public static function reset(): void
    {
        self::$ratios = self::$sizes = self::$formats = null;
    }

    /** `0.5`, `2`, `10` - a size as it stands in a file name. */
    private static function megapixels(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
