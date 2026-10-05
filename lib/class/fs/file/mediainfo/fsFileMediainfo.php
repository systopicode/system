<?php

/**
 * Reads dimensions, resolution and duration for one file.
 *
 * The concrete reader is picked by create() from the file's mime/extension;
 * subclasses only override readMediainfo(). Every property below is declared
 * with a hook - `mediainfo` and `type` are read once and kept, the rest is
 * derived on each read.
 */
class fsFileMediainfo {

    public static array $defaultMediainfo = [
        // bitmaps
        'width' => null,
        'height' => null,
        'sizeUnit' => null,
        'resolutionX' => null,
        'resolutionY' => null,
        'resolutionUnit' => null,
        'rotate' => null,
        // + video
        'frames' => null,
        'duration' => null,
        'has_audio' => null,
        // processed (default placeholders)
        'ar' => null,
        'aspectRatio' => null,
        'resolutionIsIsotropic' => null,
        'mmWidth' => null,
        'mmHeight' => null,
        'pxWidth' => null,
        'pxHeight' => null,
        'inchWidth' => null,
        'inchHeight' => null,
    ];

    /** @var fsFile */
    public $file;

    static function create($fsFile) {
        // Sniffed mime first, filename extension as fallback: libmagic misses
        // some payloads (prolog-less SVG, EPS variants, …).
        $candidates = $fsFile->exists
                ? [$fsFile->mimeext, $fsFile->standardizedExtension]
                : [$fsFile->standardizedExtension];
        foreach ($candidates as $ext) {
            switch ($ext) {
                case 'jpg':
                case 'png':
                case 'tif':
                    return new fsFileMediainfo_imagick($fsFile);
                case 'mov':
                case 'mp4':
                case 'm4v':
                case 'webm':
                case 'wbm': // legacy typo, kept so old records keep resolving
                    return new fsFileMediainfo_ffprobe($fsFile);
                case 'svg':
                    return new fsFileMediainfo_domdocument($fsFile);
                case 'pdf':
                    return new fsFileMediainfo_identify($fsFile);
            }
        }
        p("unrecognized filetype: '" . implode("' / '", $candidates) . "'",$fsFile,$fsFile->exists);
        return new fsFileMediainfo($fsFile);
    }

    function __construct($fsFile) {
        $this->file = $fsFile;
    }

    // -------------------- properties --------------------
    // Were __getPropertyOnce (cached) / __getProperty (recomputed) on
    // trait_dynamicProperties. readMediainfo() and the class-name split both
    // always return a value, so ??= is a safe memo here.

    /** the raw reader output - read once per object */
    public ?array $mediainfo {
        get => $this->mediainfo ??= $this->readMediainfo();
    }

    /** reader flavour, from the class name: imagick, ffprobe, domdocument, identify */
    public ?string $type {
        get {
            // isset(), not === null: an untouched typed backing store is
            // uninitialized, and reading it directly would throw.
            if (!isset($this->type)) {
                $parts = explode('_', get_class($this));
                $this->type = (count($parts) > 1) ? end($parts) : 'unknown';
            }
            return $this->type;
        }
    }

    // ---- pass-through fields from mediainfo with defaults ----
    public mixed $width          { get => $this->mediaField('width'); }
    public mixed $height         { get => $this->mediaField('height'); }
    public mixed $sizeUnit       { get => $this->mediaField('sizeUnit'); }
    public mixed $resolutionX    { get => $this->mediaField('resolutionX'); }
    public mixed $resolutionY    { get => $this->mediaField('resolutionY'); }
    public mixed $resolutionUnit { get => $this->mediaField('resolutionUnit'); }
    public mixed $rotate         { get => $this->mediaField('rotate'); }
    public mixed $frames         { get => $this->mediaField('frames'); }
    public mixed $duration       { get => $this->mediaField('duration'); }
    public mixed $has_audio      { get => $this->mediaField('has_audio'); }

    // ---- processed / derived ----
    public ?float $ar {
        get => ($this->width && $this->height) ? ($this->width / $this->height) : null;
    }

    public ?float $aspectRatio {
        get => $this->ar;
    }

    public bool $resolutionIsIsotropic {
        get => (bool) ($this->resolutionX && $this->resolutionY && ($this->resolutionX === $this->resolutionY));
    }

    public mixed $resolution {
        get => $this->resolutionIsIsotropic ? $this->resolutionX : null;
    }

    public ?float $mmWidth    { get => $this->convertSize('width', 'mm'); }
    public ?float $mmHeight   { get => $this->convertSize('height', 'mm'); }
    public ?float $pxWidth    { get => $this->convertSize('width', 'px'); }
    public ?float $pxHeight   { get => $this->convertSize('height', 'px'); }
    public ?float $inchWidth  { get => $this->convertSize('width', 'inch'); }
    public ?float $inchHeight { get => $this->convertSize('height', 'inch'); }

    /**
     * Readers may find nothing (PDF without ImageMagick, unreadable image, …).
     * calculate::convertValue() only accepts floats and throws on empty units,
     * so an isset($mediainfo->inchWidth) check would fatal instead of failing soft.
     */
    protected function convertSize(string $field, string $toUnit): ?float {
        $value = $this->$field;
        $unit = $this->sizeUnit;
        if (!is_numeric($value) || !is_string($unit) || $unit === '') {
            return null;
        }
        $resolution = $field === 'width' ? $this->resolutionX : $this->resolutionY;
        if (($toUnit === 'px' || strtolower($unit) === 'px') && !is_numeric($resolution)) {
            return null;
        }
        return calculate::convertValue(
            (float) $value,
            $unit,
            $toUnit,
            (float) ($resolution ?: 0),
            (string) ($this->resolutionUnit ?: 'dpi')
        );
    }

    /**
     * Read mediainfo array (override in subclasses).
     * @return array
     */
    function readMediainfo(): array {
        return [];
    }

    function getDimensionsInfo() {
        return 'not Available';
    }

    function getResolutionInfo() {
        return 'not Available';
    }

    static function standardizeExtension($ext) {
        return strtolower($ext);
    }

    // -------------------- helpers --------------------

    /**
     * Return a value from $this->mediainfo with fallback to defaults.
     * Does not cache per-key (trait caches final property value if needed).
     */
    protected function mediaField(string $key) {
        $mi = $this->mediainfo; // triggers once
        if (array_key_exists($key, $mi)) {
            return $mi[$key];
        }
        return self::$defaultMediainfo[$key] ?? null;
    }

}
