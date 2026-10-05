<?php

/**
 * One directory, cached per path by get().
 *
 * The read-only properties below were __getPropertyOnce / __getProperty on
 * trait_dynamicProperties. They are property hooks now, each backed by a
 * protected field: a hooked property cannot be unset, so invalidation (see
 * reset(), rename(), unlink()) clears the backing field instead.
 */
class fsDir
{

	public static array $cache = [];
	public string $dirpath;
	public array $filesPriorToScan = []; // may read file without scanning dir to save resources
	public array $foldersPriorToScan = []; // may read subdir without scanning parent dir to save resources
	public bool $scanned = false;

	// -------------------------------------------------------------------------------------
	// Factory / statics
	// -------------------------------------------------------------------------------------

	public static function get(string $dirpathInput): self
	{
		// Normalisiere Backslashes zu Forward-Slashes
		$dirpathInput = str_replace('\\', '/', $dirpathInput);
		// Pruefe ob absoluter Pfad (beginnt mit / oder X:/ fuer Windows-Laufwerke)
		$isAbsolute = ($dirpathInput !== '' && (
			$dirpathInput[0] === '/' || 
			preg_match('~^[A-Za-z]:~', $dirpathInput)
		));
		if (!$isAbsolute) {
			$cwd = fs::cwd();
			$dirpath = fs::simplifyPath($cwd . $dirpathInput);
		} else {
			$dirpath = fs::simplifyPath($dirpathInput);
		}
		if (!isset(self::$cache[$dirpath])) {
			self::$cache[$dirpath] = new self();
			self::$cache[$dirpath]->dirpath = $dirpath;
		}
		return self::$cache[$dirpath];
	}

	public static function realpath(string $path): ?string
	{
		$rp = fs::realpath($path);
		return $rp === null ? null : ($rp . '/');
	}

	// -------------------------------------------------------------------------------------
	// Read-only properties (property hooks)
	// -------------------------------------------------------------------------------------
	// Backing fields: null means "not computed yet". timestamp may legitimately
	// BE null, so it carries its own read flag.

	protected ?bool $_exists = null;
	protected ?string $_name = null;
	protected ?string $_parentDirpath = null;
	protected ?self $_parent = null;
	protected ?array $_path = null;
	protected ?fsFiles $_files = null;
	protected ?fsDirs $_folders = null;
	protected ?int $_timestamp = null;
	protected bool $_timestampRead = false;

	public bool $exists {
		get => $this->_exists ??= fs::isDir($this->dirpath);
	}

	public string $name {
		get => $this->_name ??= preg_replace('~^.*/(.*?)/$~', '$1', $this->dirpath);
	}

	public string $parentDirpath {
		get => $this->_parentDirpath ??= str_replace($this->name . '/', '', $this->dirpath);
	}

	public self $parent {
		get => $this->_parent ??= self::get($this->parentDirpath);
	}

	/** alias of parent */
	public self $dir {
		get => $this->parent;
	}

	public ?int $timestamp {
		get {
			if (!$this->_timestampRead) {
				$this->_timestampRead = true;
				$this->_timestamp = $this->exists ? (fs::filemtime($this->dirpath) ?: null) : null;
			}
			return $this->_timestamp;
		}
	}

	public array $path {
		get {
			if ($this->_path === null) {
				$strip = trim($this->dirpath, '/');
				$this->_path = $strip ? explode('/', $strip) : [];
			}
			return $this->_path;
		}
	}

	public ?fsFiles $files {
		get { $this->scanned || $this->scan(); return $this->_files; }
	}

	public ?fsDirs $folders {
		get { $this->scanned || $this->scan(); return $this->_folders; }
	}

	/** recomputed on every read - was __getProperty */
	public bool $empty {
		get => ($this->files->empty() && $this->folders->empty());
	}

	/** true while the backing field holds a scanned collection */
	protected function hasScannedFolders(): bool
	{
		return $this->_folders !== null;
	}

	protected function hasScannedFiles(): bool
	{
		return $this->_files !== null;
	}

	// -------------------------------------------------------------------------------------
	// String casting
	// -------------------------------------------------------------------------------------

	public function __toString(): string
	{
		return $this->dirpath;
	}

	// -------------------------------------------------------------------------------------
	// Directory ops
	// -------------------------------------------------------------------------------------

	public function create(): void
	{
		d('depr. fsDir::create() use fdDir::make()');
	}

	public function make(): void
	{
		if (!$this->exists) {
			fs::fsMkdir($this->dirpath, 0777, true);
			$this->_exists = true;
		}
	}

	public function unlink(bool $recursive = false): bool
	{
		// Clear stat cache for SMB/NAS - prevents phantom folders from stale cache
		fs::clearstatcache(true, $this->dirpath);
		$this->_exists = null; // drop the cached exists
		
		if (!$this->exists) {
			return false;
		}
		if (!$recursive && !$this->empty) {
			d("can't unlink directory '$this->dirpath' - not empty");
			return false;
		}
		// Cache collection refs - avoids __get() on every iteration
		// and prevents any possibility of rescan during loop
		$folders = $this->folders;
		$files = $this->files;
		while (!$folders->empty()) {
			$folder = $folders->reset();
			$result = $folder->unlink($recursive);
			// If unlink failed (phantom folder), manually remove from collection
			if (!$result && !$folder->exists) {
				unset($folders[$folder->name]);
			}
		}
		while (!$files->empty()) {
			$file = $files->reset();
			$result = $file->unlink();
			// If unlink failed (phantom file), manually remove from collection
			if (!$result && !$file->exists) {
				unset($files[$file->name]);
			}
		}
		// Recheck exists - folder might have been deleted already (race condition/external change)
		$this->_exists = null;
		if (!fs::isDir($this->dirpath)) {
			// Already gone, just cleanup references
			$this->_exists = false;
			if ($this->dir->hasScannedFolders()) {
				$this->dir->removeFolder($this);
			}
			return true;
		}
		if (false === fs::fsRmdir($this->dirpath)) {
			d("error removing directory '$this->dirpath'");
		} else {
			$this->_exists = false;
			$this->dir->removeFolder($this);
		}
		return true;
	}

	public function moveTo(fsDir $targetDir): void
	{
		// 2do register src parent // update cache
		fs::fsRename($this->dirpath, "$targetDir->dirpath$this->name/");
	}

	public function rename(string $newname): void
	{
		// 2do update children
		if ($this->exists && fs::fsRename($this->dirpath, $this->parentDirpath . $newname)) {
			unset(self::$cache[$this->dirpath]);
			$this->dirpath = $this->parentDirpath . "$newname/";
			self::$cache[$this->dirpath] = $this;
			// refresh cached path-related props
			$this->_name = $this->_parentDirpath = $this->_path = null;
		}
	}

	// -------------------------------------------------------------------------------------
	// Queries, scanning & cache control
	// -------------------------------------------------------------------------------------

	/**
	 * Glob-style search below this directory.
	 *
	 *   *   beliebig viele Zeichen innerhalb einer Ebene (kein /)
	 *   ?   genau ein Zeichen innerhalb einer Ebene
	 *   **  beliebig viele Ebenen (darf / enthalten)
	 *   {jpg,png}  Alternativen
	 *   ein abschliessendes / liefert nur Ordner
	 *
	 * An absolute pattern ('/foo/**') ignores this directory as base.
	 * The returned fsSearch holds $results (all), $files and $folders.
	 * See fsSearch::$defaultOptions for the available options.
	 */
	public function find(string $pattern, array $options = []): fsSearch
	{
		return fsSearch::create($this, $pattern, $options);
	}

	public function contains(string $name): bool
	{
		$this->scanned || $this->scan();
		if ($this->files->hasKey($name)) {
			return $this->files[$name]->exists;
		}
		if ($this->folders->hasKey($name)) {
			return $this->folders[$name]->exists;
		}
		return false;
	}

	public function getFile(string $filename): fsFile
	{
		if ($this->scanned) {
			if (!$this->files->hasKey($filename)) {
				$this->files[$filename] = new fsFile($this, $filename);
			}
			return $this->files[$filename];
		}
		// prior to directory scan - only scan if necessary
		$this->filesPriorToScan[$filename] = $this->filesPriorToScan[$filename] ?? new fsFile($this, $filename);
		return $this->filesPriorToScan[$filename];
	}

	/**
	 * Subdirectory by exact folder name (like getFile: no glob, no folder2name).
	 */
	public function getFolder(string $foldername): self
	{
		$foldername = str_replace('\\', '/', $foldername);
		$foldername = trim($foldername, '/');
		if ($this->scanned) {
			if (!$this->folders->hasKey($foldername)) {
				$this->folders[$foldername] = self::get($this->dirpath . $foldername . '/');
			}
			return $this->folders[$foldername];
		}
		$this->foldersPriorToScan[$foldername] = $this->foldersPriorToScan[$foldername]
			?? self::get($this->dirpath . $foldername . '/');
		return $this->foldersPriorToScan[$foldername];
	}

	/**
	 * Resolve a configured path against this directory.
	 *
	 * Unlike fsDir::get(), a relative path resolves against *this* directory
	 * instead of the current working directory — which is what config entries
	 * ('../../projects/', './build/') mean. Absolute paths ('/foo', 'C:/foo')
	 * are taken as given.
	 */
	public function resolve(string $path): self
	{
		$path = str_replace('\\', '/', trim($path));
		if ($path === '' || $path === '.' || $path === './') {
			return $this;
		}
		$isAbsolute = $path[0] === '/' || preg_match('~^[A-Za-z]:~', $path);
		return self::get(($isAbsolute ? '' : $this->dirpath) . rtrim($path, '/') . '/');
	}

	/**
	 * Resolve a configured path that may contain wildcards into the folders it
	 * addresses. Only the levels the pattern spells out are visited:
	 *
	 *   '../tools/'          the tools folder itself
	 *   '../tools/*'         every folder directly inside tools
	 *   '../projects/*./*'   two levels below projects (without the dot)
	 *   '../projects/**'     every folder below projects, at any depth
	 *
	 * Without a wildcard the result is the single folder resolve() returns,
	 * whether it exists or not — the caller decides how to report that.
	 *
	 * @return array<string, self> dirpath => folder
	 */
	public function resolveDirs(string $pattern, array $options = []): array
	{
		$segments = $this->patternSegments($pattern);
		$base = $this->resolveBase($pattern);
		if ($segments === []) {
			return [$base->dirpath => $base];
		}
		if (!$base->exists) {
			return [];
		}
		$found = $base->find(implode('/', $segments) . '/', $options + ['onlyDirs' => true]);
		$dirs = [];
		foreach ($found->folders as $dir) {
			$dirs[$dir->dirpath] = $dir;
		}
		return $dirs;
	}

	/**
	 * The folder a wildcard pattern starts from: everything up to the first
	 * segment carrying a wildcard. '../projects/*' resolves to ../projects/,
	 * a pattern without a wildcard to the folder itself.
	 */
	public function resolveBase(string $pattern): self
	{
		$pattern = str_replace('\\', '/', trim($pattern));
		$segments = explode('/', trim($pattern, '/'));
		$literal = [];
		while ($segments !== [] && !preg_match('~[*?{\[]~', $segments[0])) {
			$literal[] = array_shift($segments);
		}
		return $this->resolve(implode('/', $literal) . '/');
	}

	/** the wildcard part of a pattern, relative to resolveBase() */
	private function patternSegments(string $pattern): array
	{
		$segments = explode('/', trim(str_replace('\\', '/', trim($pattern)), '/'));
		$literal = [];
		while ($segments !== [] && !preg_match('~[*?{\[]~', $segments[0])) {
			array_shift($segments);
		}
		return $segments;
	}

	public function unvalidate(): void
	{
		d('depr');
	}

	/**
	 * Drops only the cached exists, for a folder something outside fsDir may
	 * have created or removed. Unlike reset() it keeps the scan - a rescan in
	 * the same request trips the RESCAN guard in scan().
	 */
	public function forgetExists(): void
	{
		fs::clearstatcache(true, $this->dirpath);
		$this->_exists = null;
	}

	public function reset(): void
	{ // untested
		$this->_files = $this->_folders = null;
		$this->filesPriorToScan = [];
		$this->foldersPriorToScan = [];
		$this->scanned = false;
		// also reset quick state so they re-compute next time
		$this->_exists = null;
	}
	static array $dbg_scanned = [];

	public function scan($return = NULL): NULL|fsFiles|fsDirs
	{
		if(in_array($this->dirpath, self::$dbg_scanned)) {
			d('RESCAN: $this->dirpath');
			return NULL;
		}
		self::$dbg_scanned[] = $this->dirpath;
		$files = new fsFiles($this);
		$folders = new fsDirs($this);

		if (fs::isDir($this->dirpath)) {
			$dirhandle = fs::fsOpendir($this->dirpath);
			if (!$dirhandle) {
				$exists = false;
				return NULL;
			}
			while (true) {
				$filename = readdir($dirhandle);
				if ($filename === false) {
					break;
				}
				if ($filename === '.' || $filename === '..') {
					continue;
				}
				$fullpath = "$this->dirpath$filename";
				// Clear stat cache for SMB/NAS - prevents phantom folders from stale cache
				fs::clearstatcache(true, $fullpath);
				
				if (fs::isDir($fullpath)) {
					// Double-check with opendir to catch SMB phantom folders
					$testHandle = @fs::fsOpendir($fullpath);
					if ($testHandle !== false) {
						closedir($testHandle);
						$folders[$filename] = $this->foldersPriorToScan[$filename] ?? self::get("$fullpath/");
					}
					// else: phantom folder from stale SMB cache, skip it
				}
				if (fs::isFile($fullpath)) {
					$files[$filename] = $this->filesPriorToScan[$filename] ?? new fsFile($this, $filename);
				}
			}
			closedir($dirhandle);
			$exists = true;
		} else {
			$exists = false;
		}

		$this->scanned = true;
		$this->_files = $files;
		$this->_folders = $folders;
		$this->_exists = $exists;
		$this->filesPriorToScan = [];
		$this->foldersPriorToScan = [];
		$this->sort();
		return $return ? $this->$return : NULL;
	}

	// *********************** fsFile INTERFACE *********************** //

	public function addFolder($fsDir): void
	{
		d('unimplemented');
	}

	public function removeFolder($fsDir): void
	{
		// Check if folders collection is already loaded, don't rescan
		if ($this->hasScannedFolders()) {
			unset($this->_folders[$fsDir->name]);
		} elseif ($this->scanned) {
			// Scanned but collection not in dynamic props (edge case)
			$this->scanned || $this->scan();
			unset($this->folders[$fsDir->name]);
		} else {
			// Not yet scanned, remove from preScan array
			unset($this->filesPriorToScan[$fsDir->name]);
		}
	}

	public function addFile($fsFile): void
	{
		if ($this->scanned) {
			$this->files[$fsFile->name] = $fsFile;
		} else {
			$this->filesPriorToScan[$fsFile->name] = $fsFile;
		}
	}

	public function removeFile($fsFile): void
	{
		// Check if files collection is already loaded, don't rescan
		if ($this->hasScannedFiles()) {
			unset($this->_files[$fsFile->name]);
		} elseif ($this->scanned) {
			// Scanned but collection not in dynamic props (edge case)
			$this->scanned || $this->scan();
			unset($this->files[$fsFile->name]);
		} else {
			// Not yet scanned, remove from preScan array
			unset($this->filesPriorToScan[$fsFile->name]);
		}
	}

	public function data(): object
	{
		return (object) [
			'dirpath' => $this->dirpath,
			'files' => $this->files->keys(),
			'folders' => $this->folders->keys(),
		];
	}

	public function sort(): void
	{
		// 2do add callback
		$this->scanned || $this->scan();
		$this->_files?->sort();
		$this->_folders?->sort();
	}
}

// 2do:: add session structure // forget after N clicks unused