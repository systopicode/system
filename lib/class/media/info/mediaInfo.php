<?php

#[AllowDynamicProperties]
class mediaInfo implements clientData { // => move to fsFile

	function __construct($media) {
		$this->media = $media;
		$info = $this->updateMimeInfo();
	}

	function updateMimeInfo() {
		if (!fs::isFile($this->media->path)) {
			$this->media->valid = FALSE;
			$this->media->mimetype = 'nofile/null';
			return;
		}
		$nativePath = fs::toNative($this->media->path);
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$this->media->mimetype = $finfo->file($nativePath);
		$this->media->type = preg_replace('~/.*$~', '', $this->media->mimetype);
		$pathinfo = pathinfo($nativePath);

		if ($pathinfo['extension'] === 'svg' && $this->media->mimetype != 'svg+xml') {
			$this->media->mimetype = 'image/svg+xml';
		}
		$extName = strtolower((string) ($pathinfo['extension'] ?? ''));
		if ($extName === 'skp') {
			$this->media->mimetype = 'application/vnd.sketchup.skp';
			$this->media->type = 'application';
			$this->media->valid = TRUE;
			$this->media->ext = 'skp';
			return;
		}
		if (!isset(self::$mimetypes[$this->media->mimetype])) {
			$this->media->valid = FALSE;
			$this->media->mimetype = 'nosupport:' . $this->media->mimetype;
			return;
		}
		$this->media->valid = TRUE;
		$this->media->ext = self::$mimetypes[$this->media->mimetype];

		if ($this->media->ext === 'zip') { // xlsx files are detected as zip files depending on php version
			if (in_array($pathinfo['extension'], array('xlsx', 'docx', 'pptx'))) {
				$this->media->ext = $pathinfo['extension'];
			}
			if (preg_match('~360\.zip$~', $pathinfo['basename'])) {
				$this->media->type = 'image360';
				$this->media->ext = 'gif';
				$this->media->mimetype = 'image/gif';
			}
		}
	}

	public static $mimetypes = [
		'image/webp' => 'webp',
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/gif' => 'gif',
		'image/tiff' => 'tif',
		'image/svg+xml' => 'svg',
		'application/pdf' => 'pdf',
		'application/vnd.sketchup.skp' => 'skp',
		'application/x-sketchup' => 'skp',
		'application/msword' => 'doc',
		'application/vnd.ms-excel' => 'xls',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
		'application/x-vcard|vcard' => 'vcf',
		'application/application/zip' => 'zip',
		'application/x-zip-compressed' => 'zip',
		'audio/mpeg' => 'mp3',
		'video/mpeg' => 'mpg',
		'video/mp4' => 'mp4',
		'video/quicktime' => 'mov',
		'text/vcard' => 'vcf',
		'text/x-vcard' => 'vcf',
	];
	public static $extentions = [// prepare to store presets on media handling e.g browser support
		'jpg' => [],
		'tiff' => [],
		'svg' => [],
		'pdf' => [],
		'mp4' => [],
		'mp3' => [],
	];

	public static function autoload() {
		
	}

	public static function clientData_export() {
		return (object) [
					'route' => 'classes.media.info',
					'data' => (object) [
						'mimetypes' => (object) self::$mimetypes,
						'extentions' => (object) self::$extentions,
					]
		];
	}
}
