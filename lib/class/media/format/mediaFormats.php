<?php

#[AllowDynamicProperties]
class mediaFormats {

	public $media;
	private $formats = [];
	private $built = FALSE;

	function __construct($media) {
		$this->media = $media;
	}

	function build() {
		foreach (array_keys(static::$config) AS $name) {
			$this->formats[$name] = mediaFormat::create($this->media, $name);
		}
	}

	function hasFormat($name) {
		$this->built || $this->build();
		return key_exists($name, $this->formats);
	}

	function getFormat($name) {
		return $this->formats[$name];
	}

	// ********************* static // config  *************************
	static function get() {
		return self::$config;
	}

	static function add($formats) {
		self::$config += $formats;
		return self::$config;
	}
	static function create($media) {
		return new static($media);
	}

    /** Positive width:height (or width/height), shared by settings, output and editor. */
    static function aspectRatio($value): ?float {
        if (!is_scalar($value)) return null;
        $parts = preg_split('~[:/]~', trim((string) $value));
        if (count($parts) > 2 || !is_numeric($parts[0]) || (isset($parts[1]) && !is_numeric($parts[1]))) return null;
        $w = (float) $parts[0]; $h = isset($parts[1]) ? (float) $parts[1] : 1;
        if (!is_finite($w) || !is_finite($h) || $w <= 0 || $h <= 0) return null;
        $ratio = $w / $h;
        return is_finite($ratio) && $ratio > 0 ? $ratio : null;
    }

    static function fixedRatio(array $format): ?float {
        if ((float) ($format['megapixels'] ?? 0) > 0) return self::aspectRatio($format['aspect_ratio'] ?? '');
        $w = (float) ($format['width'] ?? 0); $h = (float) ($format['height'] ?? 0);
        return $w > 0 && $h > 0 ? $w / $h : null;
    }

	/** An aspect ratio as people write it: 16:9, 4:3 - else two decimals, 2.37:1. */
	static function ratioLabel($ratio): string {
		$ratio = (float) $ratio;
		if ($ratio <= 0) {
			return '';
		}
		for ($h = 1; $h <= 16; $h++) {
			$w = round($ratio * $h);
			if ($w > 0 && abs($w / $h - $ratio) < 0.002) {
				return $w . ':' . $h;
			}
		}
		return rtrim(rtrim(number_format($ratio, 2, '.', ''), '0'), '.') . ':1';
	}

	/** Match the sizing precedence used by mediaImage::getCanvasSize(). */
    static function sizeLabel(array $format): string {
        if ((float) ($format['megapixels'] ?? 0) > 0) return $format['megapixels'] . ' MP' . (self::fixedRatio($format) ? ' · ' . self::ratioLabel(self::fixedRatio($format)) : '');
        $w = (int) ($format['width'] ?? 0);
        $h = (int) ($format['height'] ?? 0);
        if ($w > 0 && $h > 0) return "$w × $h px";
        if ($w > 0) return "$w px wide · proportional height";
        if ($h > 0) return "$h px high · proportional width";
        return 'Original size';
    }

    /** Display label, with a stable technical-key fallback for existing projects. */
    static function label(string $name, ?array $format = null): string {
        $label = trim((string) (($format ?? self::$config[$name] ?? [])['label'] ?? ''));
        return $label !== '' ? $label : $name;
    }

	/** The image formats an editor may pick, name => label; the system's own are left out. */
	static function editorImages(): array {
		$labels = [];
		foreach (array_diff_key(self::$config, array_flip(self::SYSTEM)) as $name => $format) {
			if (($format['cms_display'] ?? '') === 'none') {
				continue;
			}
			$size = self::sizeLabel($format);
			$label = self::label((string) $name, $format);
			$labels[$name] = $size === '' ? $label : "$label ($size)";
		}
		return $labels;
	}

	/** The project's VIDEO_FORMATS, name => label. */
	static function editorVideos(): array {
		$labels = [];
		foreach (defined('VIDEO_FORMATS') && is_array(VIDEO_FORMATS) ? VIDEO_FORMATS : [] as $name => $format) {
			$label = self::label((string) $name, $format);
			$labels[$name] = isset($format['max_width']) ? $label . ' (' . $format['max_width'] . ' px)' : $label;
		}
		return $labels;
	}

	const SYSTEM = ['original', 'alias', 'mmlThumb', 'shareImage', 'portrait'];

	static $config = [ // system formats required for CMS operation
		'original' => array(// instant creation if original exceeds 1,5 mp - not impemented yet
			'megapixels' => NULL,
			'cms_display' => 'none',
			'mime_type' => NULL,
		),
		'alias' => array(// instant creation if original exceeds 1,5 mp - not impemented yet
			'cms_display' => 'none',
			'megapixels' => 3,
			'mime_type' => NULL,
		),
		'mmlThumb' => array(
			'cms_display' => 'none',
			'width' => 200,
			'height' => 200,
			'mime_type' => NULL,
		),
		'shareImage' => array( // instant creation
			'cms_display' => 'image',
			'width' => 1200,
			'height' => 630,
		),
		// A face in the account. JPEG and PNG are drawn to this square as WebP.
		// A WebP is stored as it arrived. Not a crop in the image editor.
		'portrait' => array(
			'cms_display' => 'none',
			'width' => 500,
			'height' => 500,
			'mime_type' => 'image/webp',
		),
	];

}
