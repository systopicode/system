<?php

/**
 * ImageMagick command line (magick). Preferred over the imagick extension for
 * PDF/PS sources: only the requested page is rasterized and the density can be
 * calculated up front, so huge print formats do not explode in memory.
 */
#[AllowDynamicProperties]
class fsFileOperator_magick extends fsFileOperator {

	static array $capabilities = [
		'read' => ['pdf', 'ps', 'eps', 'ai'],
		'write' => ['jpg', 'png', 'gif', 'tif'],
	];

	function scale($factor) {
		
	}

	public $width, $height;

	function create($width = NULL, $height = NULL) {
		$this->width = $width;
		$this->height = $height;
	}

	function render($formatName): static {
		switch ($formatName) {
			case 'jpg':
			case 'png':
				$binary = shellBinary::find('magick');
				if (!$binary) {
					p("magick not found - define MAGICK_BIN or add it to the PATH");
					return $this;
				}
				$this->resultTempPath = $this->getTempPath($formatName);
				// [0] renders the first page only - a multi page pdf would
				// otherwise produce one file per page.
				$source = fs::toNative($this->file->filepath) . '[0]';
				$command = implode(' ', [
					escapeshellarg($binary),
					'-density ' . $this->getDensityForMegapixels(),
					escapeshellarg($source),
					escapeshellarg(fs::toNative($this->resultTempPath)),
					'2>&1',
				]);
				$output = shell_exec($command);
				if (!$this->hasResult()) {
					p("magick render failed: " . trim((string) $output), $command);
					$this->resultTempPath = null;
				}
				return $this;
		}
		return parent::render($formatName);
	}

	/**
	 * Rasterizing dpi that makes the page come out at the requested megapixels,
	 * derived from its physical size (identify reports it in inch for pdf).
	 * Big print formats end up well below 72 dpi - that is the point: rendering
	 * at a fixed density first and downscaling afterwards is what blows up.
	 */
	protected function getDensityForMegapixels(): float {
		$fallback = 150.0;
		$mp = (float) ($this->megapixels ?: 0);
		if ($mp <= 0) {
			return $fallback;
		}
		$mediainfo = $this->file->mediainfo;
		$inchWidth = (float) ($mediainfo->inchWidth ?: 0);
		$inchHeight = (float) ($mediainfo->inchHeight ?: 0);
		if ($inchWidth <= 0 || $inchHeight <= 0) {
			p("no page size for '{$this->file->filename}' - rendering at $fallback dpi");
			return $fallback;
		}
		$density = sqrt($mp * 1e6 / ($inchWidth * $inchHeight));
		return round(calculate::minmax(1, $density, 1200), 2);
	}

	function destroy(): ?bool {
		return parent::destroy();
	}
}
