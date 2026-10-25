<?php

/**
 * Base class for file operators (Strategy Pattern).
 * 
 * Operators provide specialized file operations like rendering, transformation,
 * cropping, resizing, and export. They are loaded dynamically via fsFileOperators::getOperator().
 * 
 * Naming convention: fsFileOperator_$name (e.g., fsFileOperator_magick for magick command line tool)
 * 
 * $this->file is always the source file (the file the operator is attached to).
 * Conversion results live in the operator (resultBlob or resultTempPath),
 * delivered via store(), output(), or getContents().
 * 
 * Usage - the caller names the tool, or several in the order to try them:
 *   $file->getOperator('imagick')->read()->render('jpg')->store($path);
 *   $file->firstOperator(['magick', 'imagick'], fn($op) => $op->setMegapixels(2)->render('png'));
 * Which tool is right depends on the job (quality, speed, isolation), so the
 * order is written where the job is, with the reason next to it.
 * 
 * For compositing (e.g. paste), sources are passed as parameters.
 * 
 * Folder structure:
 *   Root level: fsFileOperator_$name classes extend fsFileOperator
 *               and integrate with the fsFile system.
 *   Subfolders: Contain helper classes that extend external libraries
 *               (e.g., tcpdf/fsFileOperatorTcpdf extends TCPDF).
 *               These are instantiated by the root operator class.
 * 
 * Example:
 *   fsFileOperator_domdocsvg extends fsFileOperator
 *     -> creates domdocsvg/fsFileOperatorDomdocsvg (wraps DOMDocument)
 *   fsFileOperator_tcpdf extends fsFileOperator
 *     -> uses tcpdf/fsFileOperatorTcpdf extends TCPDF
 * 
 * @property fsFileOperators $operators  Parent operators collection
 * @property fsFile $file                Source file (the file this operator belongs to)
 * @property string $name                Operator name (e.g., 'magick')
 * @property bool $read                  Whether file has been read
 * 
 * @see fsFileOperators::getOperator()
 * @see fsFileOperator_magick
 */
class fsFileOperator {

	/**
	 * What the operator is known to handle. Operators that can ask their tool
	 * (formats()) add to this at runtime - a newer tool version reads more
	 * without anyone editing a list.
	 */
	static array $capabilities = [
		'read' => [],
		'write' => [],
	];

	/** formats() per class, asked once per request */
	private static array $formatCache = [];

	/** formats that are pages to be rasterized, not pixels to be read */
	const PAGE_FORMATS = ['pdf', 'ps', 'eps', 'ai'];

	public $operators, $file, $name;

	protected ?string $resultBlob = null;
	protected ?string $resultTempPath = null;

	/** why the last step produced nothing - for the caller, not the debug panel */
	public string $lastError = '';

	/** the page of a page format to rasterize, 0 = the first */
	protected int $page = 0;

	/**
	 * [width, height] of the source in pixels, when the operator knows it and
	 * its result is scaled (the original size of a photo, the video's frame
	 * size) - NULL when the result is the source size, or nobody measured it.
	 */
	public ?array $sourceSize = null;

	function __construct(
			fsFIleOperators $operators,
			string $name
	) {
		$this->operators = $operators;
		$this->file = $operators->file;
		$this->name = $name;
	}

	public $read = FALSE;
	public ?float $megapixels = null;

	function read() {
		// no default operator
		$this->read = TRUE;
	}

	function setMegapixels(?float $megapixels): static {
		$this->megapixels = $megapixels;
		return $this;
	}

	function setPage(int $page): static {
		$this->page = max(0, $page);
		return $this;
	}

	/** Can this operator run here at all (binary found, extension loaded)? */
	static function isAvailable(): bool {
		return true;
	}

	/**
	 * ['read' => [...], 'write' => [...]] in lower case, as the tool says it
	 * (override queryFormats()), merged with the declared $capabilities.
	 */
	static function formats(): array {
		return self::$formatCache[static::class] ??= (static function (array $declared, array $queried): array {
			$merged = [];
			foreach (['read', 'write'] as $mode) {
				$types = array_map('strtolower', [...($declared[$mode] ?? []), ...($queried[$mode] ?? [])]);
				// one spelling per format, so a caller need not know which the tool uses
				foreach (['jpeg' => 'jpg', 'tiff' => 'tif'] as $long => $short) {
					if (in_array($long, $types, true) || in_array($short, $types, true)) {
						array_push($types, $long, $short);
					}
				}
				$merged[$mode] = array_values(array_unique($types));
			}
			return $merged;
		})(static::$capabilities, static::isAvailable() ? static::queryFormats() : []);
	}

	/** what the tool itself reports - nothing unless the operator can ask it */
	protected static function queryFormats(): array {
		return [];
	}

	static function canRead(string $type): bool {
		return in_array(strtolower($type), static::formats()['read'], true);
	}

	static function canWrite(string $type): bool {
		return in_array(strtolower($type), static::formats()['write'], true);
	}

	/**
	 * Rasterizing density for a page of the given physical size, so it comes
	 * out at the requested megapixels. Computed BEFORE rendering - rendering
	 * at a fixed density and scaling afterwards is what blows up on large
	 * print formats and what produced 72 dpi thumbnails (readImageBlob).
	 */
	static function densityFor(float $inchWidth, float $inchHeight, float $megapixels): float {
		if ($inchWidth <= 0 || $inchHeight <= 0 || $megapixels <= 0) {
			return 150.0;
		}
		return round(calculate::minmax(1, sqrt($megapixels * 1e6 / ($inchWidth * $inchHeight)), 1200), 2);
	}

	protected function fail(string $message): static {
		$this->lastError = $message;
		$this->cleanup();
		return $this;
	}

	/**
	 * An ImageMagick error turned into what to do about it - the two that are
	 * a setup issue rather than a broken file. NULL for anything else.
	 *
	 * @param string $policyFiles where the policy files are, if known (magick -list policy)
	 */
	static function explainMagickError(string $message, string $policyFiles = ''): ?string {
		// IM7 CLI: "not allowed by the security policy `PDF'", IM6: "not authorized `PDF'",
		// the imagick extension: "NotAuthorized `PDF'"
		if (preg_match('~(?:not allowed by the security policy|not ?authorized)\s+[`\'"]([A-Z0-9]+)~i', $message, $m)) {
			$coder = strtoupper($m[1]);
			return "ImageMagick's security policy does not allow $coder. In policy.xml change "
				. "<policy domain=\"coder\" rights=\"none\" pattern=\"$coder\" /> to rights=\"read|write\" "
				. '(or remove the line; a pattern like "{PS,PDF,XPS}" covers several), then restart PHP / the web server. '
				. ($policyFiles !== ''
					? "Policy file(s) in effect: $policyFiles."
					: 'Where it is: `magick -list policy` (lines "Path:"); usually /etc/ImageMagick-6/ or /etc/ImageMagick-7/ on Linux, '
						. 'the ImageMagick folder on Windows (for the PHP extension: the folder of its CORE_RL_*.dll files).');
		}
		if (preg_match('~FailedToExecuteCommand.*\b(gs|gswin64c|gswin32c)\b|delegate failed.*\b(gs|gswin\d+c)\b|gs(win64c)?: (not found|command not found)~i', $message)) {
			return 'Ghostscript is missing - ImageMagick needs it for pdf/ps/eps/ai. Install it (Linux: apt install ghostscript, '
				. 'Windows: ghostscript.com, the 64 bit installer) and restart PHP / the web server.';
		}
		return null;
	}

	function render($format): static {
		d("format '$format' not supported by " . static::class. '::render');
		return $this;
	}

	function destroy(): ?bool {
		return $this->operators->destroy($this);
	}

	function export(string $format): ?fsFile {
		d("format '$format' not supported by " . static::class. '::export');
		return null;
	}

	// ----------------------- Conversion Result API -----------------------

	protected function getTempPath(string $ext): string {
		$dir = (string) fs::$root . "var/temp/$this->name/";
		fs::isDir($dir) || fs::fsMkdir($dir, 0775, true);
		return $dir . uniqid() . ".$ext";
	}

	function hasResult(): bool {
		return $this->resultBlob !== null
			|| ($this->resultTempPath && fs::isFile($this->resultTempPath));
	}

	function getContents(): ?string {
		if ($this->resultBlob !== null) return $this->resultBlob;
		if ($this->resultTempPath) return fs::file_get_contents($this->resultTempPath);
		return null;
	}

	function store(string $targetPath): static {
		if ($this->resultBlob !== null) {
			$dir = dirname($targetPath);
			fs::isDir($dir) || fs::fsMkdir($dir, 0775, true);
			fs::file_put_contents($targetPath, $this->resultBlob);
			fs::chmod($targetPath, 0664);
		} elseif ($this->resultTempPath) {
			$dir = dirname($targetPath);
			fs::isDir($dir) || fs::fsMkdir($dir, 0775, true);
			fs::fsRename($this->resultTempPath, $targetPath);
			fs::chmod($targetPath, 0664);
			$this->resultTempPath = null;
		}
		$this->cleanup();
		return $this;
	}

	function output(?string $mimeType = null): void {
		if ($mimeType) header("Content-Type: $mimeType");
		if ($this->resultBlob !== null) {
			echo $this->resultBlob;
		} elseif ($this->resultTempPath) {
			readfile(fs::toNative($this->resultTempPath));
		}
		$this->cleanup();
	}

	function cleanup(): void {
		$this->resultBlob = null;
		if ($this->resultTempPath && fs::isFile($this->resultTempPath)) {
			fs::unlinkFile($this->resultTempPath);
		}
		$this->resultTempPath = null;
	}
}
