<?php

/**
 * ImageMagick command line (magick): rasterizes one page of pdf/ps/eps/ai.
 *
 * Runs in its own process: Ghostscript and ImageMagick can take that down
 * on a broken or huge upload, not the PHP worker. The density is computed
 * from the page size BEFORE rendering, so the raster comes out at
 * setMegapixels() however large the print format is.
 *
 * Setup errors (security policy, missing Ghostscript) come back in lastError
 * as what to do about them - see fsFileOperator::explainMagickError().
 *
 * Usage:
 *   $file->getOperator('magick')->setMegapixels(2)->setPage(0)->render('png')->getContents();
 */
#[AllowDynamicProperties]
class fsFileOperator_magick extends fsFileOperator {

	static array $capabilities = [
		'read' => ['pdf', 'ps', 'eps', 'ai'],
		'write' => ['jpg', 'png', 'gif', 'tif'],
	];

	/** without setMegapixels(), and when the page size cannot be read */
	const FALLBACK_DENSITY = 150.0;

	static function isAvailable(): bool {
		return shellBinary::isAvailable('magick');
	}

	/**
	 * `magick -list format`: the Mode column says r and w separately
	 * ("PDF  PDF  rw+  Portable Document Format"; IM6 has no Module column).
	 */
	protected static function queryFormats(): array {
		$output = (string) shell_exec(escapeshellarg((string) shellBinary::find('magick')) . ' -list format 2>&1');
		preg_match_all('~^\s*([A-Z0-9-]+)\*?\s+(?:\S+\s+)?([r-])([w-])[+-]\s~m', $output, $rows, PREG_SET_ORDER);
		$formats = ['read' => [], 'write' => []];
		foreach ($rows as [, $format, $read, $write]) {
			$read === 'r' && $formats['read'][] = $format;
			$write === 'w' && $formats['write'][] = $format;
		}
		return $formats;
	}

	function scale($factor) {

	}

	public $width, $height;

	function create($width = NULL, $height = NULL) {
		$this->width = $width;
		$this->height = $height;
	}

	function render($formatName): static {
		$format = strtolower((string) $formatName);
		if (!in_array($format, static::$capabilities['write'], true)) {
			return parent::render($formatName);
		}
		$this->lastError = '';
		$binary = shellBinary::find('magick');
		if (!$binary) {
			return $this->fail('magick not found - install ImageMagick 7, or define MAGICK_BIN');
		}
		if (!$this->file->exists) {
			return $this->fail("source not found: {$this->file->filepath}");
		}
		// [n]: this page only - without it every page becomes a file
		$source = fs::toNative($this->file->filepath) . "[{$this->page}]";
		$density = $this->density($binary, $source);
		if ($density === null) {
			return $this; // failed, lastError says why
		}
		$this->resultTempPath = $this->getTempPath($format);
		$command = [escapeshellarg($binary), '-density', $density, escapeshellarg($source)];
		if ($format === 'jpg') {
			// no alpha in jpg: transparent page areas would turn black
			$command[] = '-background white -alpha remove -alpha off -quality 90';
		}
		$command[] = escapeshellarg(fs::toNative($this->resultTempPath));
		$output = (string) shell_exec(implode(' ', $command) . ' 2>&1');
		if (!$this->hasResult()) {
			return $this->fail($this->explain('render failed', $output, $binary));
		}
		return $this;
	}

	/**
	 * The density that gives setMegapixels() for this page, NULL when it
	 * failed for a reason rendering would fail on too (policy, Ghostscript).
	 * The page size comes from `magick identify` - points at the resolution
	 * the format reports (72 for pdf), so pixels / resolution are inches.
	 */
	protected function density(string $binary, string $source): ?float {
		$megapixels = (float) ($this->megapixels ?: 0);
		if ($megapixels <= 0) {
			return self::FALLBACK_DENSITY;
		}
		// the format string literally: escapeshellarg() turns % into a space on Windows
		$output = (string) shell_exec(escapeshellarg($binary) . ' identify -format "%w %h %x %y" ' . escapeshellarg($source) . ' 2>&1');
		if (preg_match('~^(\d+(?:\.\d+)?) (\d+(?:\.\d+)?) (\d+(?:\.\d+)?) (\d+(?:\.\d+)?)~', trim($output), $m)) {
			$xResolution = (float) $m[3] ?: 72.0;
			$yResolution = (float) $m[4] ?: 72.0;
			return static::densityFor((float) $m[1] / $xResolution, (float) $m[2] / $yResolution, $megapixels);
		}
		if (static::explainMagickError($output) !== null) {
			$this->fail($this->explain('page size unknown', $output, $binary));
			return null;
		}
		p("magick: no page size for '{$this->file->filename}' - rendering at " . self::FALLBACK_DENSITY . ' dpi', trim($output));
		return self::FALLBACK_DENSITY;
	}

	/** what went wrong, with the policy files in effect when the policy was the reason */
	protected function explain(string $what, string $output, string $binary): string {
		$output = trim($output);
		$policyFiles = '';
		if (preg_match('~security policy|not ?authorized~i', $output)) {
			preg_match_all('~^Path:\s*(.+)$~m', (string) shell_exec(escapeshellarg($binary) . ' -list policy 2>&1'), $m);
			// "[built-in]" is no file to edit
			$policyFiles = implode(', ', array_filter(array_map('trim', $m[1] ?? []), static fn($path) => !str_starts_with($path, '[')));
		}
		return (static::explainMagickError($output, $policyFiles) ?? "magick: $what") . ($output !== '' ? " (magick: $output)" : '');
	}

	protected function fail(string $message): static {
		p("magick: $message");
		return parent::fail($message);
	}

	function destroy(): ?bool {
		return parent::destroy();
	}
}
