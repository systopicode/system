<?php

/**
 * Glob-style filesystem search.
 *
 * Wildcards (per path segment unless noted):
 *   *          beliebig viele Zeichen innerhalb einer Ebene (kein /)
 *   ?          genau ein Zeichen innerhalb einer Ebene
 *   **         beliebig viele Ebenen (darf / enthalten)
 *   {a,b,c}    Alternativen (verschachtelbar, duerfen / enthalten)
 *   trailing / nur Ordner
 *
 * Examples (base = $dir):
 *   $dir->find('*')                 eine Ebene unter $dir
 *   $dir->find('**')                alles rekursiv unter $dir
 *   $dir->find('* / *')             genau zwei Ebenen (ohne Leerzeichen)
 *   $dir->find('**\/*.{jpg,png}')   alle Bilder rekursiv
 *   $dir->find('/foo/**', ['onlyDirs' => true])
 *
 * Results are lazy - the search runs on first access of
 * $search->results / $search->files / $search->folders.
 *
 * NOTE (PHP 8.4+ property hooks):
 * This class used to get its lazy, read-only properties from
 * trait_dynamicProperties (__get + __getPropertyOnce/__getProperty, values
 * cached in $__DP_dynamicProperties). That is replaced by native property
 * hooks - the aim is to drop the trait everywhere:
 *   - results/files/folders are hooked properties backed by real typed
 *     fields ($resultItems/$resultFiles/$resultFolders), filled once by
 *     run() - the equivalent of __getPropertyOnce (cached).
 *   - length/empty/paths/names are virtual (no backing store), recomputed
 *     on every read - the equivalent of __getProperty.
 * Benefits: the properties are declared and typed (IDE + static analysis
 * see them, no @property-read needed), write attempts fail loudly instead
 * of silently landing in a magic cache, and there is no __get/__isset
 * indirection per access. reset() clears the backing fields instead of
 * unset()ing magic cache entries - a hooked property cannot be unset.
 */
class fsSearch implements IteratorAggregate, Countable
{

	/**
	 * Search options - extend by adding a key here and handling it in
	 * normalizeOptions() / accept() / descend().
	 *
	 * onlyDirs   bool                 nur Ordner zurueckgeben
	 * onlyFiles  bool                 nur Dateien zurueckgeben
	 * hidden     bool                 versteckte Eintraege (.name) einbeziehen
	 * minDepth   int                  Treffer erst ab dieser Ebene (1 = direkte Kinder)
	 * maxDepth   int|null             maximale Ebenentiefe unter dem Basisordner
	 * ext        string|array|null    Dateiendung(en), z.B. 'jpg' oder ['jpg','png']
	 * name       string|null          zusaetzliche RegEx auf den Basisnamen, z.B. '~^IMG_~'
	 * newerThan  int|string|null      mtime > Zeitpunkt (timestamp, '-7 days', DateTime)
	 * olderThan  int|string|null      mtime < Zeitpunkt
	 * minSize    int|string|null      Dateigroesse >= (Bytes oder '2M')
	 * maxSize    int|string|null      Dateigroesse <=
	 * skip       string|array|null    Ordnernamen (Array) oder RegEx, die nicht betreten werden
	 * filter     callable|null        fn(fsFile|fsDir $item): bool
	 * limit      int|null             Suche nach N Treffern abbrechen
	 * sort       bool                 Treffer nach Pfad sortieren (natural, case-insensitive)
	 * keys       string               'path' (default) oder 'name' als Collection-Key
	 * case       bool|null            Gross-/Kleinschreibung beachten - null = OS-Default
	 */
	public static array $defaultOptions = [
		'onlyDirs'  => false,
		'onlyFiles' => false,
		'hidden'    => false,
		'minDepth'  => 0,
		'maxDepth'  => null,
		'ext'       => null,
		'name'      => null,
		'newerThan' => null,
		'olderThan' => null,
		'minSize'   => null,
		'maxSize'   => null,
		'skip'      => null,
		'filter'    => null,
		'limit'     => null,
		'sort'      => true,
		'keys'      => 'path',
		'case'      => null,
	];

	public fsDir $dir;           // search origin
	public string $pattern;      // pattern as given
	public array $opt = [];      // merged options
	public array $patterns = []; // pattern after brace expansion
	public bool $searched = false;

	// -------------------------------------------------------------------------------------
	// Lazy result properties (property hooks - read only)
	// -------------------------------------------------------------------------------------
	// backing fields, filled once by run() / buildCollections(), cleared by reset()
	protected fsItems $resultItems;
	protected fsFiles $resultFiles;
	protected fsDirs $resultFolders;

	/** all matches (files and folders) */
	public fsItems $results {
		get {
			$this->run();
			return $this->resultItems;
		}
	}

	/** matched files */
	public fsFiles $files {
		get {
			$this->run();
			return $this->resultFiles;
		}
	}

	/** matched folders */
	public fsDirs $folders {
		get {
			$this->run();
			return $this->resultFolders;
		}
	}

	/** number of matches */
	public int $length {
		get => count($this->results);
	}

	/** no matches */
	public bool $empty {
		get => $this->results->empty();
	}

	/** matched paths as strings */
	public array $paths {
		get => $this->results->keys();
	}

	/** matched basenames as strings */
	public array $names {
		get => array_map(fn($item) => $item->name, $this->results->values());
	}

	// normalized options
	protected ?array $ext = null;
	protected ?int $newerThan = null;
	protected ?int $olderThan = null;
	protected ?int $minSize = null;
	protected ?int $maxSize = null;
	protected ?int $maxDepth = null;
	protected bool $caseInsensitive = false;

	// per pattern state
	protected array $segments = [];
	protected bool $dirOnly = false;

	// collecting
	protected array $raw = []; // path => fsFile|fsDir
	protected bool $done = false;

	// -------------------------------------------------------------------------------------
	// Factory
	// -------------------------------------------------------------------------------------

	public static function create(fsDir $dir, string $pattern, array $options = []): self
	{
		$search = new self();
		$search->dir = $dir;
		$search->pattern = $pattern;
		$unknown = array_diff_key($options, self::$defaultOptions);
		if ($unknown) {
			d('fsSearch: unknown option(s): ' . implode(', ', array_keys($unknown)));
		}
		$search->opt = array_merge(self::$defaultOptions, $options);
		$search->normalizeOptions();
		$search->patterns = self::expandBraces(str_replace('\\', '/', trim($pattern)));
		return $search;
	}

	public function getIterator(): Iterator
	{
		return $this->results;
	}

	public function count(): int
	{
		return count($this->results);
	}

	public function __toString(): string
	{
		return implode("\n", array_map('strval', $this->results->values()));
	}

	public function data(): object
	{
		return (object) [
			'dir'      => $this->dir->dirpath,
			'pattern'  => $this->pattern,
			'patterns' => $this->patterns,
			'options'  => array_diff_assoc($this->opt, self::$defaultOptions),
			'results'  => array_keys($this->raw),
		];
	}

	// -------------------------------------------------------------------------------------
	// Search
	// -------------------------------------------------------------------------------------

	/**
	 * Runs the search once and fills results / files / folders.
	 * $return picks one of them, run() alone returns NULL.
	 */
	public function run(?string $return = null): NULL|fsItems|fsFiles|fsDirs
	{
		if (!$this->searched) {
			$this->searched = true;
			$this->raw = [];
			$this->done = false;
			foreach ($this->patterns as $pattern) {
				if ($this->done) {
					break;
				}
				[$base, $segments, $dirOnly] = $this->resolve($pattern);
				if (!$segments || !$base->exists) {
					continue;
				}
				$this->segments = $segments;
				$this->dirOnly = $dirOnly;
				$this->walk($base, 0, 0);
			}
			$this->buildCollections();
		}
		return match ($return) {
			'results' => $this->resultItems,
			'files' => $this->resultFiles,
			'folders' => $this->resultFolders,
			default => NULL,
		};
	}

	/** re-run on next access (e.g. after files changed) */
	public function reset(): self
	{
		$this->searched = false;
		unset($this->resultItems, $this->resultFiles, $this->resultFolders);
		return $this;
	}

	protected function buildCollections(): void
	{
		if ($this->opt['sort']) {
			ksort($this->raw, SORT_NATURAL | SORT_FLAG_CASE);
		}
		$results = new fsItems($this->dir);
		$files = new fsFiles($this->dir);
		$folders = new fsDirs($this->dir);
		foreach ($this->raw as $path => $item) {
			$key = $this->opt['keys'] === 'name' ? $item->name : $path;
			$results[$key] = $item;
			if ($item instanceof fsDir) {
				$folders[$key] = $item;
			} else {
				$files[$key] = $item;
			}
		}
		$this->resultItems = $results;
		$this->resultFiles = $files;
		$this->resultFolders = $folders;
	}

	/**
	 * Splits one (brace free) pattern into base directory and pattern segments.
	 * Leading literal segments are folded into the base, so untouched
	 * directories are never scanned.
	 *
	 * @return array{0:fsDir,1:string[],2:bool} [base, segments, dirOnly]
	 */
	protected function resolve(string $pattern): array
	{
		$dirOnly = str_ends_with($pattern, '/');
		$isAbsolute = $pattern !== '' && ($pattern[0] === '/' || preg_match('~^[A-Za-z]:~', $pattern));
		if ($isAbsolute) {
			$pattern = fs::toInternal($pattern);
		}
		$trim = trim($pattern, '/');
		$segments = $trim === '' ? [] : explode('/', $trim);
		$segments = array_values(array_filter($segments, fn($segment) => $segment !== '' && $segment !== '.'));
		$prefix = $isAbsolute ? '/' : $this->dir->dirpath;
		// fold leading literal segments into the base (keep the last one - it may be a file)
		while (count($segments) > 1 && !self::hasWildcard($segments[0])) {
			$prefix .= array_shift($segments) . '/';
		}
		return [fsDir::get($prefix), $segments, $dirOnly];
	}

	protected function walk(fsDir $dir, int $i, int $depth): void
	{
		if ($this->done) {
			return;
		}
		$segment = $this->segments[$i];
		$isLast = ($i === count($this->segments) - 1);

		if ($segment === '**') {
			if ($isLast) {
				$this->collectAll($dir, $depth);
				return;
			}
			$this->walk($dir, $i + 1, $depth); // ** may match zero levels
			if ($this->maxDepth !== null && $depth >= $this->maxDepth) {
				return;
			}
			foreach ($dir->folders as $folder) {
				if ($this->done) {
					return;
				}
				if ($this->descend($folder)) {
					$this->walk($folder, $i, $depth + 1); // stay on ** for deeper levels
				}
			}
			return;
		}

		if ($this->maxDepth !== null && $depth + 1 > $this->maxDepth) {
			return;
		}

		if (!self::hasWildcard($segment)) { // literal - no scan required
			$folder = fsDir::get($dir->dirpath . $segment . '/');
			if ($isLast) {
				if (fs::isFile($dir->dirpath . $segment)) {
					$this->collect($dir->getFile($segment), $depth + 1);
				}
				if ($folder->exists) {
					$this->collect($folder, $depth + 1);
				}
				return;
			}
			if ($folder->exists && $this->descend($folder)) {
				$this->walk($folder, $i + 1, $depth + 1);
			}
			return;
		}

		$regex = $this->segmentRegex($segment);
		if (!$isLast) {
			foreach ($dir->folders as $folder) {
				if ($this->done) {
					return;
				}
				if (preg_match($regex, $folder->name) && $this->descend($folder)) {
					$this->walk($folder, $i + 1, $depth + 1);
				}
			}
			return;
		}
		if (!$this->dirOnly && !$this->opt['onlyDirs']) {
			foreach ($dir->files as $file) {
				if ($this->done) {
					return;
				}
				if (preg_match($regex, $file->name)) {
					$this->collect($file, $depth + 1);
				}
			}
		}
		if (!$this->opt['onlyFiles']) {
			foreach ($dir->folders as $folder) {
				if ($this->done) {
					return;
				}
				if (preg_match($regex, $folder->name)) {
					$this->collect($folder, $depth + 1);
				}
			}
		}
	}

	/** trailing ** - everything below $dir, recursive */
	protected function collectAll(fsDir $dir, int $depth): void
	{
		if ($this->done || ($this->maxDepth !== null && $depth >= $this->maxDepth)) {
			return;
		}
		if (!$this->dirOnly && !$this->opt['onlyDirs']) {
			foreach ($dir->files as $file) {
				if ($this->done) {
					return;
				}
				$this->collect($file, $depth + 1);
			}
		}
		foreach ($dir->folders as $folder) {
			if ($this->done) {
				return;
			}
			if (!$this->opt['onlyFiles']) {
				$this->collect($folder, $depth + 1);
			}
			if ($this->descend($folder)) {
				$this->collectAll($folder, $depth + 1);
			}
		}
	}

	protected function collect(fsFile|fsDir $item, int $depth): void
	{
		if (!$this->accept($item, $depth)) {
			return;
		}
		$path = $item instanceof fsDir ? $item->dirpath : $item->filepath;
		if (isset($this->raw[$path])) {
			return;
		}
		$this->raw[$path] = $item;
		if ($this->opt['limit'] !== null && count($this->raw) >= $this->opt['limit']) {
			$this->done = true;
		}
	}

	/** may this folder be entered? (independent of whether it is a match itself) */
	protected function descend(fsDir $folder): bool
	{
		if (!$this->opt['hidden'] && str_starts_with($folder->name, '.')) {
			return false;
		}
		$skip = $this->opt['skip'];
		if ($skip === null) {
			return true;
		}
		if (is_string($skip)) {
			return !preg_match($skip, $folder->name);
		}
		return !in_array($folder->name, (array) $skip, true);
	}

	/** does this match pass the option filters? */
	protected function accept(fsFile|fsDir $item, int $depth): bool
	{
		$isDir = $item instanceof fsDir;
		if ($isDir && $this->opt['onlyFiles']) {
			return false;
		}
		if (!$isDir && ($this->opt['onlyDirs'] || $this->dirOnly)) {
			return false;
		}
		if (!$this->opt['hidden'] && str_starts_with($item->name, '.')) {
			return false;
		}
		if ($depth < $this->opt['minDepth']) {
			return false;
		}
		if ($this->opt['name'] !== null && !preg_match($this->opt['name'], $item->name)) {
			return false;
		}
		if ($this->ext !== null) {
			if ($isDir || !in_array(strtolower((string) $item->extension), $this->ext, true)) {
				return false;
			}
		}
		if ($this->newerThan !== null || $this->olderThan !== null) {
			$timestamp = $item->timestamp;
			if ($timestamp === null) {
				return false;
			}
			if ($this->newerThan !== null && $timestamp <= $this->newerThan) {
				return false;
			}
			if ($this->olderThan !== null && $timestamp >= $this->olderThan) {
				return false;
			}
		}
		if ($this->minSize !== null || $this->maxSize !== null) {
			if ($isDir) {
				return false;
			}
			$size = (int) $item->size;
			if ($this->minSize !== null && $size < $this->minSize) {
				return false;
			}
			if ($this->maxSize !== null && $size > $this->maxSize) {
				return false;
			}
		}
		$filter = $this->opt['filter'];
		if ($filter !== null && !$filter($item)) {
			return false;
		}
		return true;
	}

	// -------------------------------------------------------------------------------------
	// Pattern helpers
	// -------------------------------------------------------------------------------------

	public static function hasWildcard(string $segment): bool
	{
		return strpbrk($segment, '*?') !== false;
	}

	/** one path segment -> anchored regex, * and ? never cross a / */
	protected function segmentRegex(string $segment): string
	{
		$re = '';
		for ($i = 0, $n = strlen($segment); $i < $n; $i++) {
			$char = $segment[$i];
			$re .= match ($char) {
				'*' => '[^/]*',
				'?' => '[^/]',
				default => preg_quote($char, '~'),
			};
		}
		return '~^' . $re . '$~u' . ($this->caseInsensitive ? 'i' : '');
	}

	/**
	 * {jpg,png} -> ['jpg', 'png'] on the whole pattern, nesting aware.
	 * Alternatives may contain / - expansion happens before segmentation.
	 */
	public static function expandBraces(string $pattern): array
	{
		$open = null;
		$close = null;
		$depth = 0;
		for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
			if ($pattern[$i] === '{') {
				if ($depth === 0) {
					$open = $i;
				}
				$depth++;
			} elseif ($pattern[$i] === '}' && $depth > 0 && --$depth === 0) {
				$close = $i;
				break;
			}
		}
		if ($open === null || $close === null) {
			return [$pattern];
		}
		$prefix = substr($pattern, 0, $open);
		$suffix = substr($pattern, $close + 1);
		$body = substr($pattern, $open + 1, $close - $open - 1);
		$alternatives = [];
		$buffer = '';
		$depth = 0;
		for ($i = 0, $n = strlen($body); $i < $n; $i++) {
			$char = $body[$i];
			if ($char === '{') {
				$depth++;
			} elseif ($char === '}') {
				$depth--;
			} elseif ($char === ',' && $depth === 0) {
				$alternatives[] = $buffer;
				$buffer = '';
				continue;
			}
			$buffer .= $char;
		}
		$alternatives[] = $buffer;
		$expanded = [];
		foreach ($alternatives as $alternative) {
			foreach (self::expandBraces($prefix . $alternative . $suffix) as $sub) {
				$expanded[] = $sub;
			}
		}
		return array_values(array_unique($expanded));
	}

	// -------------------------------------------------------------------------------------
	// Option normalization
	// -------------------------------------------------------------------------------------

	protected function normalizeOptions(): void
	{
		$this->maxDepth = $this->opt['maxDepth'] === null ? null : (int) $this->opt['maxDepth'];
		$this->opt['minDepth'] = (int) $this->opt['minDepth'];
		$this->caseInsensitive = $this->opt['case'] === null ? (OS === 'windows') : !$this->opt['case'];
		if ($this->opt['ext'] !== null) {
			$this->ext = array_map(
				fn($ext) => strtolower(ltrim((string) $ext, '.')),
				(array) $this->opt['ext']
			);
		}
		$this->newerThan = self::toTimestamp($this->opt['newerThan']);
		$this->olderThan = self::toTimestamp($this->opt['olderThan']);
		$this->minSize = self::toBytes($this->opt['minSize']);
		$this->maxSize = self::toBytes($this->opt['maxSize']);
		if ($this->opt['filter'] !== null && !is_callable($this->opt['filter'])) {
			d('fsSearch: option filter is not callable');
			$this->opt['filter'] = null;
		}
	}

	/** int timestamp, DateTimeInterface or anything strtotime() understands ('-7 days') */
	public static function toTimestamp($value): ?int
	{
		if ($value === null) {
			return null;
		}
		if ($value instanceof DateTimeInterface) {
			return $value->getTimestamp();
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		$timestamp = strtotime((string) $value);
		if ($timestamp === false) {
			d("fsSearch: can't read date '$value'");
			return null;
		}
		return $timestamp;
	}

	/** int bytes or shorthand like '2M', '512k', '1.5G' */
	public static function toBytes($value): ?int
	{
		if ($value === null) {
			return null;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		if (!preg_match('~^\s*([0-9.]+)\s*([kmgt]?)b?\s*$~i', (string) $value, $match)) {
			d("fsSearch: can't read size '$value'");
			return null;
		}
		$factor = ['' => 1, 'k' => 1024, 'm' => 1024 ** 2, 'g' => 1024 ** 3, 't' => 1024 ** 4];
		return (int) ((float) $match[1] * $factor[strtolower($match[2])]);
	}
}
