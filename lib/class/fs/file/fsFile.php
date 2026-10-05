<?php

/**
 * One file inside an fsDir.
 *
 * The properties below were __getPropertyOnce / __getProperty on
 * trait_dynamicProperties. They are declared with hooks now; the values that
 * used to sit in $__DP_dynamicProperties live in $memo, because several of
 * them are legitimately null (a missing file has no size, no mime, no
 * contents) and null has to stay distinguishable from "not read yet".
 *
 * No magic left: the class used to answer any name via __get — mediainfo
 * fields as $file->width and operators as $file->imagick. Both have an
 * explicit route and that is the only one now:
 *
 *   $file->mediainfo->width
 *   $file->getOperator('imagick')
 */
class fsFile
{

	public $dir;
	public $name;

	/** read-once values, keyed by property name — null is a valid entry */
	protected array $memo = [];

	/** @return mixed the memoised value, computing it on first access */
	protected function memo(string $key, callable $compute): mixed
	{
		return array_key_exists($key, $this->memo)
			? $this->memo[$key]
			: ($this->memo[$key] = $compute());
	}

	// -------------------------------------------------------------------------------------
	// Read-once properties (were __getPropertyOnce)
	// -------------------------------------------------------------------------------------

	public mixed $mediainfo {
		get => $this->memo('mediainfo', fn() => fsFileMediainfo::create($this));
	}

	public mixed $pathinfo {
		get => $this->memo('pathinfo', fn() => (object) pathinfo($this->name));
	}

	public mixed $filebase {
		get => $this->memo('filebase', fn() => $this->pathinfo->filename ?? null);
	}

	public mixed $filepath {
		get => $this->memo('filepath', fn() => $this->dir->dirpath . $this->name);
	}

	public mixed $extension {
		get => $this->memo('extension', fn() => $this->pathinfo->extension ?? null);
	}

	/** alias of extension */
	public mixed $ext {
		get => $this->memo('ext', fn() => $this->pathinfo->extension ?? null);
	}

	public mixed $standardizedExtension {
		get => $this->memo(
			'standardizedExtension',
			fn() => fsFileMediainfo::standardizeExtension($this->extension)
		);
	}

	public mixed $exists {
		get => $this->memo('exists', fn() => fs::isFile($this->filepath));
		set (mixed $value) { $this->memo['exists'] = $value; }
	}

	public mixed $contents {
		get => $this->memo('contents', fn() => $this->exists ? fs::file_get_contents($this->filepath) : null);
		set (mixed $value) { $this->memo['contents'] = $value; }
	}

	// One finfo call fills the whole cluster; see computeMimeAndCache().
	public mixed $mimefulltype    { get => $this->memo('mimefulltype',    fn() => $this->computeMimeAndCache('mimefulltype')); }
	public mixed $mimetype        { get => $this->memo('mimetype',        fn() => $this->computeMimeAndCache('mimetype')); }
	public mixed $mimedescription { get => $this->memo('mimedescription', fn() => $this->computeMimeAndCache('mimedescription')); }
	public mixed $mimeext         { get => $this->memo('mimeext',         fn() => $this->computeMimeAndCache('mimeext')); }

	public mixed $size {
		get => $this->memo('size', fn() => $this->exists ? fs::filesize($this->filepath) : null);
	}

	public mixed $timestamp {
		get => $this->memo('timestamp', fn() => $this->exists ? fs::filemtime($this->filepath) : null);
	}

	public mixed $operators {
		get => $this->memo('operators', fn() => new fsFileOperators($this));
	}

	public mixed $href {
		get => $this->memo('href', fn() => http::filepathToHref($this->filepath));
	}

	// -------------------------------------------------------------------------------------
	// Derived on every read (were __getProperty)
	// -------------------------------------------------------------------------------------

	public mixed $filename {
		get => $this->name;
	}

	public bool $hasMediainfo {
		get => !is_null($this->mediainfo->mediainfo);
	}

	public array $info {
		get => $this->exists ? [
			'file' => $this->name,
			'mimetype - mimeext' => "{$this->mimefulltype} - {$this->mimeext}",
			'size' => str::formatbytes($this->size),
			'dimensions' => $this->mediainfo->getDimensionsInfo(),
			'resolution' => $this->mediainfo->getResolutionInfo(),
		] : [
			'file' => $this->name,
			'directory' => $this->dir->dirpath,
			'status' => 'file not found',
		];
	}

	public array $fileinfo {
		get => $this->exists ? [
			'exists' => $this->exists,
			'mime' => $this->mimefulltype,
			'mimeext' => $this->mimeext,
			'size' => $this->size,
			'modified_timestamp' => $this->timestamp,
		] : ['exists' => $this->exists];
	}

	static function get($filepath)
	{
		$pathinfo = (object) pathinfo($filepath);
		return fsDir::get("$pathinfo->dirname/")->getFile($pathinfo->basename);
	}

	function __construct($dir, $name)
	{
		$this->dir = $dir;
		$this->name = $name;
	}

	protected function computeMimeAndCache(string $requested)
	{
		if (!$this->exists) {
			// cache nulls for the cluster to avoid recomputation
			foreach (['mimefulltype', 'mimetype', 'mimedescription', 'mimeext'] as $k) {
				$this->memo[$k] = null;
			}
			return null;
		}

		$full = (new finfo(FILEINFO_MIME_TYPE))->file(fs::toNative($this->filepath));
		[$type, $desc] = array_pad(explode('/', $full, 2), 2, null);

		$map = [
			// special cases where common extension differs from mimedescription
			'octet-stream' => 'bin',
			'gzip' => 'gz',
			'svg+xml' => 'svg',
			'tiff' => 'tif',
			'jpeg' => 'jpg',
		];
		$ext = $map[$desc] ?? $desc;

		// libmagic reports text/xml for SVGs whose root element it does not
		// recognise (e.g. <svg class='…'> without version/width). Everything
		// downstream keys off the mime: mediainfo reader, imagick svg handling
		// and the Content-Type we serve. Trust the extension in that case.
		if ($ext !== 'svg' && strtolower((string) $this->extension) === 'svg' && $this->hasSvgRootElement()) {
			$full = 'image/svg+xml';
			$type = 'image';
			$desc = 'svg+xml';
			$ext = 'svg';
		}

		// cache all related values

		$this->memo['mimefulltype'] = $full;
		$this->memo['mimetype'] = $type;
		$this->memo['mimedescription'] = $desc;
		$this->memo['mimeext'] = $ext;

		return $this->memo[$requested] ?? null;
	}

	protected function hasSvgRootElement(): bool
	{
		$head = @file_get_contents(fs::toNative($this->filepath), false, null, 0, 4096);
		return is_string($head) && stripos($head, '<svg') !== false;
	}

	// -------------------------------------------------------------------------------------
	// existing public API stays as-is
	// -------------------------------------------------------------------------------------

	function getOperator($name): fsFileOperator
	{
		return $this->operators->getOperator($name);
	}

	function convertTo(string $format, ?string $operator = null): fsFileOperator
	{
		$operatorName = $operator ?? fsFileOperators::findOperatorFor($this->extension, $format);
		$op = $this->getOperator($operatorName);
		$op->render($format);
		return $op;
	}

	function unvalidate()
	{ // external changes
		// the list this used to unset one by one was exactly every memoised
		// property, so dropping the whole memo is the same thing
		$this->memo = [];
	}

	function unlink(): bool
	{
		// Clear stat cache for SMB/NAS - prevents phantom files from stale cache
		fs::clearstatcache(true, $this->filepath);
		unset($this->memo['exists']); // Clear cached exists property

		if ($this->exists) {
			if (fs::unlinkFile($this->dir->dirpath . $this->name)) {
				$this->dir->removeFile($this);
				$this->unvalidate();
			} else {
				d("fsFile->unlink() error removing file '$this->name' from '{$this->dir->dirpath}'.");
				return false;
			}
			return true;
		}
		return false;
	}

	function move($fsDir)
	{
		if ($this->exists) {
			fs::fsRename($this->filepath, $fsDir->dirpath . $this->name);
			$this->dir->removeFile($this);
			$fsDir->addFile($this);
			$this->dir = $fsDir;
			$this->unvalidate();
		}
	}

	function copy($fsDir)
	{
		if ($this->exists) {
			$fsDir->exists || $fsDir->make();
			fs::fsCopy($this->filepath, $fsDir->dirpath . $this->name);
			$fsDir->addFile($this);
			$this->dir = $fsDir;
			$this->unvalidate();
		}
	}

	function rename($newname)
	{
		$newFilepath = $this->dir->dirpath . $newname;
		if ($this->exists && $newFilepath !== $this->filepath) {
			fs::fsRename($this->filepath, $newFilepath);
			$this->dir->removeFile($this);
			$this->name = $newname;
			$this->dir->addFile($this);
			$this->unvalidate();
		}
	}

	function read()
	{
		return $this->contents;
	}

	function write($contents = false)
	{
		if (is_string($contents)) {
			$this->contents = $contents;
		}
		if (array_key_exists('contents', $this->memo)) {
			$this->dir->exists || $this->dir->make();
			$this->exists || $this->touch();
			if (false === fs::file_put_contents($this->filepath, $this->contents)) {
				d("error writing file '$this->filepath'");
			}
		}
		return $this;
	}

	function append($data)
	{
		$this->dir->exists || $this->dir->make();
		$this->exists || $this->touch();
		if (false === fs::file_put_contents($this->filepath, $data, FILE_APPEND)) {
			d("error appending to file '$this->filepath'");
		}
		if (array_key_exists('contents', $this->memo)) {
			$this->contents .= $data;
		}
	}

	function touch($timestamp = null)
	{
		$this->dir->exists || $this->dir->make();
		if (!fs::isWritable($this->dir->parent)) {
			d("error not writeable " . $this->dir->parent);
		}
		if (!$this->exists) {
			if (false === fs::touch($this->filepath, $timestamp)) {
				d("error creating file '$this->filepath'");
			}
			$this->exists = true;
			$this->dir->addFile($this);
		}
		return fs::touch($this->filepath, $timestamp);
	}
}
