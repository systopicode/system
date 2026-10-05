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
	];

}
