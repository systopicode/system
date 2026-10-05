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
 * Usage:
 *   $pdfFile->convertTo('jpg')->store('/output/thumb.jpg');
 *   $pdfFile->convertTo('jpg')->output();
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

	static array $capabilities = [
		'read' => [],
		'write' => [],
	];

	public $operators, $file, $name;

	protected ?string $resultBlob = null;
	protected ?string $resultTempPath = null;

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
