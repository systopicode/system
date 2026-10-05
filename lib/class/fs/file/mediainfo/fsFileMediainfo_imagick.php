<?php

class fsFileMediainfo_imagick extends fsFileMediainfo {

	#[\Override]
			function readMediainfo(): array {
		if (!$this->file->exists || !$this->file->size) {
			return self::$defaultMediainfo + ['error' => 'image file missing or empty'];
		}
		try {
			$img = $this->file->getOperator('imagick');
			return [
				'width' => $img->width,
				'height' => $img->height,
				'sizeUnit' => 'px',
				'resolutionX' => $img->resolutionX,
				'resolutionY' => $img->resolutionY,
				'resolutionUnit' => 'dpi',
			];
		} catch (Throwable $exception) {
			// Unreadable payload must not kill the request: callers check
			// width/height and can report a build error instead.
			return self::$defaultMediainfo + ['error' => 'imagick: ' . $exception->getMessage()];
		}
	}

	#[\Override]
			function getDimensionsInfo(): string {
		$mp = str::megapixels($this->width, $this->height);
		return "{$this->width}x{$this->height}{$this->sizeUnit} = {$mp}Megapixel";
	}

	#[\Override]
			function getResolutionInfo(): string {
		if ($this->resolution > 0) {
			return $this->resolution . $this->resolutionUnit . ' => ' . round($this->mmWidth) . 'x' . round($this->mmHeight) . 'mm';
		}
		return 'resolution not valid or unisotropic';
	}
}
