<?php

class fsFileMediainfo_identify extends fsFileMediainfo
{

	#[\Override]
	function readMediainfo(): array
	{
		$filepath = (string) ($this->file->filepath ?? '');
		if ($filepath === '' || !\fs::isFile($filepath)) {
			return self::$defaultMediainfo;
		}

		$binary = \shellBinary::find('magick');
		if (!$binary) {
			p('magick not found - define MAGICK_BIN or add it to the PATH');
			return self::$defaultMediainfo;
		}

		$nativePath = \fs::toNative($filepath);
		// [0]: page one is enough and keeps multi page pdfs cheap
		$command = escapeshellarg($binary) . ' identify -verbose ' . escapeshellarg($nativePath . '[0]') . ' 2>&1';
		$output = shell_exec($command);
		if (!is_string($output) || trim($output) === '') {
			return self::$defaultMediainfo;
		}

		$identifyInfo = \str::parseYAML($output);
		$image = $identifyInfo['Image'] ?? null;
		if (!is_array($image)) {
			// Identify often prints delegate/errors before "Image:" (e.g. missing Ghostscript on first PDF touch).
			return self::$defaultMediainfo;
		}

		$printSize = trim((string) ($image['Print size']['value'] ?? ''));
		$resolution = trim((string) ($image['Resolution']['value'] ?? ''));
		$geometry = trim((string) ($image['Geometry']['value'] ?? ''));

		$wInch = $hInch = null;
		if ($printSize !== '' && str_contains($printSize, 'x')) {
			[$wInch, $hInch] = array_pad(explode('x', $printSize, 2), 2, null);
			$wInch = is_numeric($wInch) ? (float) $wInch : null;
			$hInch = is_numeric($hInch) ? (float) $hInch : null;
		}

		$xDpi = $yDpi = null;
		if ($resolution !== '' && str_contains($resolution, 'x')) {
			[$xDpi, $yDpi] = array_pad(explode('x', $resolution, 2), 2, null);
			$xDpi = is_numeric($xDpi) ? (float) $xDpi : null;
			$yDpi = is_numeric($yDpi) ? (float) $yDpi : null;
		}

		// Fallback: Geometry is pixels (e.g. JPEG without Print size / Resolution).
		if (($wInch === null || $hInch === null) && $geometry !== '' && preg_match('/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)/', $geometry, $m)) {
			return [
				'width' => (float) $m[1],
				'height' => (float) $m[2],
				'sizeUnit' => 'px',
				'resolutionX' => $xDpi,
				'resolutionY' => $yDpi,
				'resolutionUnit' => ($xDpi !== null || $yDpi !== null) ? 'dpi' : null,
			];
		}

		if ($wInch === null || $hInch === null) {
			return self::$defaultMediainfo;
		}

		return [
			'width' => $wInch,
			'height' => $hInch,
			'sizeUnit' => 'inch',
			'resolutionX' => $xDpi,
			'resolutionY' => $yDpi,
			'resolutionUnit' => ($xDpi !== null || $yDpi !== null) ? 'dpi' : null,
		];
	}

	#[\Override]
	function getDimensionsInfo(): string
	{
		return 'not Available';
	}

	#[\Override]
	function getResolutionInfo(): string
	{
		return 'not Available';
	}
}
