<?php

class fsFileOperator_imagick extends fsFileOperator {

	static array $capabilities = [
		'read' => ['pdf', 'tif', 'tiff', 'jpg', 'png', 'gif', 'psd', 'eps', 'ai', 'svg'],
		'write' => ['jpg', 'png', 'gif', 'pdf', 'tif'],
	];

	static function isAvailable(): bool {
		return extension_loaded('imagick');
	}

	/**
	 * What the linked ImageMagick knows. queryFormats() does not tell reading
	 * from writing, so both get the list - render() still only writes what it
	 * has a case for.
	 */
	protected static function queryFormats(): array {
		$formats = Imagick::queryFormats();
		return ['read' => $formats, 'write' => $formats];
	}

	/** the canvas - created on first access, kept for the operator's lifetime */
	public ?Imagick $imagick {
		get => $this->imagick ??= new Imagick();
	}

	// Dimensions come from the canvas, so reading one loads the file.
	public int $width {
		get { $this->read || $this->read(); return $this->imagick->getImageWidth(); }
	}

	public int $height {
		get { $this->read || $this->read(); return $this->imagick->getImageHeight(); }
	}

	public mixed $resolutionX {
		get { $this->read || $this->read(); return $this->imagick->getImageResolution()['x']; }
	}

	public mixed $resolutionY {
		get { $this->read || $this->read(); return $this->imagick->getImageResolution()['y']; }
	}

	function read(): static {
		$type = $this->file->mimeext ?: $this->file->standardizedExtension;
		if (in_array($type, static::PAGE_FORMATS, true) && $this->megapixels && $this->file->exists) {
			return $this->readPage();
		}
		try {
			if ($this->file->mimeext === 'svg') {
				// SVG has no raster canvas; Imagick paints opaque white unless
				// the background is set before readImageBlob.
				$this->imagick->setBackgroundColor(new ImagickPixel('transparent'));
				$this->imagick->readImageBlob($this->fixSvg());
			} else {
				$this->imagick->readImageBlob($this->file->contents);
			}
			$this->read = TRUE;
		} catch (Exception $exception) {
			p('imagick error readImageBlob - contensize:' . strlen($this->file->contents));
			p($this->file, 2);
			d($exception);
		}
		return $this;
	}

	/**
	 * One page of a pdf/ps/eps/ai at the density that gives setMegapixels().
	 *
	 * By path with the page index, density set BEFORE the read - the only way
	 * Imagick rasterizes at a chosen size. readImageBlob() renders every page
	 * at 72 dpi, and a resolution set afterwards changes nothing (measured
	 * 2026-10-23: 596x842 instead of 1190x1680) - that is the "Imagick cannot
	 * set the dpi" experience, not a limit of the extension.
	 */
	protected function readPage(): static {
		$this->lastError = '';
		$source = fs::toNative($this->file->filepath) . "[{$this->page}]";
		try {
			$probe = new Imagick();
			$probe->pingImage($source); // page size without rasterizing
			$resolution = $probe->getImageResolution();
			$inchWidth = $probe->getImageWidth() / max(1, (float) $resolution['x']);
			$inchHeight = $probe->getImageHeight() / max(1, (float) $resolution['y']);
			$probe->clear();
			$density = static::densityFor($inchWidth, $inchHeight, (float) $this->megapixels);
			$this->imagick->setResolution($density, $density);
			$this->imagick->readImage($source);
			$this->read = TRUE;
		} catch (Exception $exception) {
			$message = $exception->getMessage();
			$this->fail((static::explainMagickError($message) ?? 'imagick: page read failed') . " (imagick: $message)");
		}
		return $this;
	}

	function fixSvg() {
		$op = $this->file->getOperator('domdocsvg');
		$op->read();
		$op->domdocsvg->allNodes(function ($node) {
			$classNames = explode(' ', $node->getAttribute('class'));
			// imagick renderer fails on multiple classnames
			// remove all classnames but first (may change appearance)
			if (count($classNames) > 1) {
				$node->setAttribute('class', reset($classNames));
			}
		});
		return $op->domdocsvg->toSvg();
	}

	function setSvgResolution($megapixel, $width, $height, $sizeUnit): static {
		// imagick assumes 96 dpi on svg graphics
		// no unit in svg width/height means unit = pixels
		$pxWidth = calculate::convert($sizeUnit ?: 'px', 'px', $width, 96);
		$pxHeight = calculate::convert($sizeUnit ?: 'px', 'px', $height, 96);
		$resolution = 96 * calculate::scaleToMegapixels($pxWidth, $pxHeight, $megapixel);
		$this->imagick->setResolution($resolution, $resolution);
		return $this;
	}

	function setSvgSize($megapixel, $width, $height, $sizeUnit): static {
		// imagick assumes 96 dpi on svg graphics
		// no unit in svg width/height means unit = pixels
		$pxWidth = calculate::convert($sizeUnit ?: 'px', 'px', $width, 96);
		$pxHeight = calculate::convert($sizeUnit ?: 'px', 'px', $height, 96);
		$resolution = 96 * calculate::scaleToMegapixels($pxWidth, $pxHeight, $megapixel);
		$this->imagick->setSize((int) $pxWidth, (int) $pxHeight);
		$this->imagick->setResolution($resolution, $resolution);
		return $this;
	}

	/**
	 * Resampled to setMegapixels() - up or down, with the sinc filter: the
	 * normalized raster the CMS cuts its formats from (mediaImage alias).
	 * The size before goes to sourceSize. Without megapixels: unchanged.
	 */
	function resample(): static {
		if (!$this->read && $this->lastError === '') {
			$this->read();
		}
		if (!$this->read || !$this->megapixels) {
			return $this;
		}
		$width = max(1, $this->imagick->getImageWidth());
		$height = max(1, $this->imagick->getImageHeight());
		$this->sourceSize = [$width, $height];
		$targetWidth = sqrt($width * $this->megapixels * 1e6 / $height);
		$this->imagick->resizeImage(
				max(1, (int) round($targetWidth)),
				max(1, (int) round($targetWidth * $height / $width)),
				Imagick::FILTER_SINC, 1
		);
		return $this;
	}

	function scale($factor): static {
		$this->imagick->scaleImage((int) ($this->width * $factor), 0, false);
		return $this;
	}

	function create($width = NULL, $height = NULL) {
		$this->imagick->newImage(
				round($width),
				round($height),
				new ImagickPixel('transparent'),
				$this->file->mimeext ?? ''
		);
		$this->read = TRUE;
	}

	function paste($sourceImagickOperator, $cropData) {
		$fitIn = calculate::crop($sourceImagickOperator, $this, $cropData); /* $this->cropData->scale */
		// imagick assumes 72 dpi if no resolution in file
		// reading the resolution first also makes sure the source image is loaded
		$resolutionX = $sourceImagickOperator->resolutionX ?: 72;
		$resolutionY = $sourceImagickOperator->resolutionY ?: 72;
		$sourceImagick = clone $sourceImagickOperator->imagick;

		// add crop ... resample unused image areas
		$sourceImagick->resampleImage(
				$resolutionX * $fitIn->scale,
				$resolutionY * $fitIn->scale, Imagick::FILTER_SINC, 1
		);

		$this->imagick->compositeImage(
				$sourceImagick,
				Imagick::COMPOSITE_DEFAULT,
				round($fitIn->x),
				round($fitIn->y),
		);

		$this->imagick->setImageResolution($resolutionX, $resolutionY);

		// $target->file->imagick->setImageResolution($target->megapixels, $target->megapixels);
	}

	function render($formatName): static {
		// ->setMegapixels()->render() works like with magick: read on demand
		if (!$this->read && $this->lastError === '') {
			$this->read();
		}
		if (!$this->read) {
			return $this; // the read failed, lastError says why
		}
		switch ($formatName) {
			case 'jpg':
				if ($this->imagick->getImageAlphaChannel()) {
					$this->imagick->setImageBackgroundColor(new ImagickPixel('white'));
					$this->imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
				}
				$this->imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
				$this->imagick->setImageCompressionQuality(90);
				$this->imagick->setImageFormat($formatName);
				$this->resultBlob = $this->imagick->getImageBlob();
				return $this;
			case 'png':
				$this->imagick->setImageFormat($formatName);
				$this->resultBlob = $this->imagick->getImageBlob();
				return $this;
		}
		return parent::render($formatName);
	}

	function destroy(): ?bool {
		$this->imagick->destroy();
		return parent::destroy();
	}
}
