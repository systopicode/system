<?php

class fs
{ // file and directory functions
	// *********************************** Method Forwarding ***************

	static function __callStatic($name, $args)
	{
		if (is_callable(['fsHelper', $name])) { // forward to helper
			return fsHelper::$name(...$args);
		}
		if (is_callable(['fsAutoloader', $name])) { // forward to autoloader
			return fsAutoloader::$name(...$args);
		}
		d(static::class . "::$name() not defined");
	}

	// set by http::setRoot();
	static fsDir $hostRoot; // location of host root dir on filesystem e.g /var/www/projectname/public/
	// *********************************** Paths and early Helpers ***************
	static string $sysPath; // SYS_PATH — relative from app root to system root (set by setRootAndSitePath)
	static string $sitePath = 'public/'; // relative path from appRoot to siteRoot - make dynamic later
	static ?fsDir $sysRoot = null; // system root directory object typically $root/vendor/systopic/system
	static ?fsDir $siteRoot = null; // site root directory object typically $root/public
	static ?fsDir $projectRoot = null; // project root directory object typically $root/
	static ?fsDir $root = null; // alisas of projectRoot


	// *********************************** Paths and early Helpers ***************

	static function setRootAndSitePath(string $rootToSysPath, ?string $root = null): void
	{
		$sysPathRel = str_replace('\\', '/', $rootToSysPath);
		$sysPathRel = $sysPathRel === '' ? '' : rtrim($sysPathRel, '/') . '/';
		self::$sysPath = $sysPathRel;

		$rootStr = rtrim(self::toInternal($root ?? self::cwd()), '/') . '/';
		self::$projectRoot = fsDir::get($rootStr);
		self::$root = self::$projectRoot;

		$sitePathRel = trim(str_replace('\\', '/', self::$sitePath), '/');
		$sitePathRel = $sitePathRel === '' ? '' : $sitePathRel . '/';
		$siteStr = self::simplifyPath($rootStr . $sitePathRel);
		self::$siteRoot = fsDir::get($siteStr);

		$sysStr = self::simplifyPath($rootStr . $sysPathRel);
		self::$sysRoot = fsDir::get($sysStr);
	}
	static function getCoreJsSettings(): array
	{
		// Filesystem-side roots are exposed under fs* keys so they don't
		// collide with the URL-side http::$siteRoot / http::$sysRoot when
		// merged into client settings (see sys::getCoreJsSettings()).
		// JS uses settings.siteRoot / settings.sysRoot as URL prefixes —
		// those must come from http::, never from fs::.
		// Filesystem paths use fs* keys so they don't overwrite URL-side
		// http::$projectRoot / siteRoot / sysRoot in client JS settings.
		return [
			'hostRoot' => (string) self::$hostRoot ?? '(uninitialized; http::setRoot)',
			'sysPath' => self::$sysPath,
			'sitePath' => self::$sitePath,
			'fsProjectRoot' => (string) self::$projectRoot,
			'fsSiteRoot' => (string) self::$siteRoot,
			'fsSysRoot' => (string) self::$sysRoot,
		];
	}

	static function debugInfo(): object
	{
		if (self::$sysRoot === null) {
			return (object) ['error' => 'setRootAndSitePath() has not been called yet'];
		}
		return (object) self::getCoreJsSettings();
	}

	static function simplifyPath($path)
	{ // only absolute paths
		$path = self::toInternal($path);
		$trim = trim($path, '/');
		$dirs = $trim === '' ? [] : explode('/', $trim);
		$simplifiedDirs = fs::simplifyDirs($dirs);
		if (count($simplifiedDirs)) {
			return '/' . implode('/', $simplifiedDirs) . '/';
		}
		return '/';
	}

	static function simplifyDirs($dirs)
	{
		$newDirs = [];
		foreach ($dirs as $dir) {
			if ($dir === '..' && count($newDirs)) {
				if (end($newDirs) !== '..') {
					array_pop($newDirs);
					continue;
				}
			}
			$newDirs[] = $dir;
		}
		return $newDirs;
	}

	// *********************************** cwd Directory Management ... simplify include statements ***************
	// changing
	static $activeToRootPath = ''; // relative path from active dir to siteRoot dir
	static $dirStack = []; // stack of reverse operations to restore dir // '' -> no change
	// debug
	static $debug = FALSE;

	static function openSysDir()
	{
		if (is_null(fs::$sysPath)) {
			die('file class needs path between site and sys dir');
		}
		if (empty(self::$dirStack)) { // fist call
			return self::openDir(fs::$sysPath);
		} else {
			// p(self::$activeToRootPath . self::$cmsPath);
			return self::openDir(self::$activeToRootPath . self::$sysPath, TRUE);
		}
	}

	static function openRootDir()
	{
		self::debug('openRootDir');
		// t('openRootDir');
		return self::openDir(self::$activeToRootPath, TRUE);
	}

	static function openDir($dir = '', $root = FALSE)
	{
		self::debug('************ openDir', $dir);
		if (empty($dir)) {
			self::$dirStack[] = array(
				'openr'    => '',
				'close'    => '',
				'savedCwd' => '',
				'2root'    => self::$activeToRootPath
			);
			self::debug('afterOpenDir (EMPTY)');
			return TRUE;
		}
		if (!\is_dir(self::toNative($dir))) {
			self::debug('afterOpenDir DIR NOT FOUND');
			return FALSE;
		}
		$closeDir = self::getCloseDir($dir);
		// Save the real absolute CWD *before* chdir so closeDir can restore it
		// reliably even when chdir traverses a symlink (on Linux getcwd() then
		// returns the realpath, making text-computed $closeDir invalid).
		$savedCwd = \getcwd();

		if ($root) {
			self::$activeToRootPath = '';
		} else {
			self::$activeToRootPath = $closeDir . self::$activeToRootPath;
		}

		self::$dirStack[] = array(
			'openr'    => $dir,
			'close'    => $closeDir,
			'savedCwd' => $savedCwd,
			'2root'    => self::$activeToRootPath,
		);
		$success = \chdir(self::toNative($dir));
		if (!$success) {
			p('openDirError at ' . self::cwd() . ' try to open ' . $dir);
		}
		self::debug('afterOpenDir', $dir);
		return $success;
	}

	/**
	 * Relativer Verzeichnispfad von $fromDir nach $toDir (für fs::openDir), ohne absoluten Pfad im Stack.
	 * Leerstring wenn beide gleich sind. Endet mit / sofern nicht leer.
	 */
	static function relativePathFromTo(string $fromDir, string $toDir): string
	{
		$from = self::realpath(rtrim(self::toInternal($fromDir), '/'));
		$to = self::realpath(rtrim(self::toInternal($toDir), '/'));
		if ($from === null || $to === null) {
			return '';
		}
		if ($from === $to) {
			return '';
		}
		$fromParts = array_values(array_filter(explode('/', $from), 'strlen'));
		$toParts = array_values(array_filter(explode('/', $to), 'strlen'));
		$i = 0;
		$n = min(count($fromParts), count($toParts));
		while ($i < $n && $fromParts[$i] === $toParts[$i]) {
			$i++;
		}
		$up = count($fromParts) - $i;
		$relParts = array_merge(array_fill(0, $up, '..'), array_slice($toParts, $i));
		return implode('/', $relParts) . '/';
	}

	static function getCloseDir($dir)
	{
		$cwd = rtrim(self::cwd(), '/');
		$dir = self::toInternal($dir);
		$currenrDirs = explode('/', $cwd);
		$openDirs = explode('/', $dir);
		$closeDirsRev = [];
		foreach ($openDirs as $openDir) {
			if ($openDir === '..') {
				$closeDirsRev[] = array_pop($currenrDirs); // remember dir for close
			} elseif (!empty($openDir)) {
				$closeDirsRev[] = '..';
			}
		}
		$closeDirs = array_reverse($closeDirsRev);
		$closeDirsSimpl = self::simplifyDirs($closeDirs);
		$terminatingSlash = count($closeDirsSimpl) ? '/' : '';
		return implode('/', $closeDirsSimpl) . $terminatingSlash; // count($closeDirs) ? '/' : '';
	}

	static function closeDir($debugNote = '')
	{
		self::debug('************** closeDir' . $debugNote);
		$dir = array_pop(self::$dirStack);
		if (count(self::$dirStack)) {
			self::$activeToRootPath = end(self::$dirStack)['2root'];
		} else {
			self::$activeToRootPath = '';
			// t('afterCloseDir -> dirstack empty - cwd is:' . getcwd());
			// empty dirstack should'nt be a problem
		}
		if (empty($dir['close'])) {
			self::debug('afterCloseDir (EMPTY)');
			return TRUE;
		}
		// Prefer the absolute saved CWD (symlink-proof) over the text-computed
		// relative close path. The relative path breaks when chdir() traversed a
		// symlink because getcwd() then returns the realpath, not the link path.
		// savedCwd is the raw getcwd() result (native OS format) from before chdir.
		$restoreTo = !empty($dir['savedCwd'])
			? $dir['savedCwd']
			: self::toNative($dir['close']);
		if (\is_dir($restoreTo)) {
			$success = \chdir($restoreTo);
			if (!$success) {
				p('closeDirError at ' . self::cwd() . ' try to restore ' . $restoreTo);
				error_log('fs::closeDir error at ' . self::cwd() . ' try to restore ' . json_encode($dir));
			}
			self::debug('afterCloseDir');
			return $success;
		} else {
			// Kein forceDebug/t(): Dump von $dirStack bzw. debug_backtrace() kann Speicher erschöpfen
			return FALSE;
		}
	}

	static function readCurrentDir($depth = 1, $path = [])
	{ // only current dir by default
		$return = [];
		$files = array_diff(\scandir('.'), array('.', '..'));
		foreach ($files as $file) {
			$info = (object) [
				'name' => $file,
				'time' => \filemtime($file),
				'path' => $path,
				'is_dir' => \is_dir($file),
			];
			$return[$file] = $info;
			if ($depth === 1 || !$info->is_dir) {
				continue;
			}
			if (fs::openDir($file)) {
				$return[$file]->dir = self::readCurrentDir($depth - 1, array_merge($path, [$file]));
				fs::closeDir();
			}
		}
		return $return;
	}

	static function forceDebug($caller, $arg = FALSE)
	{
		self::debug($caller, $arg, TRUE);
	}

	static function lev()
	{
		error_log('fs::lev dirLevel:' . count(self::$dirStack) . ' => ' . str_replace(substr(self::$siteRoot, 0, -1), '', self::cwd()));
	}

	static function debugStart()
	{
		self::$debug = TRUE;
	}

	static function debugStop()
	{
		self::$debug = FALSE;
	}

	static function debug($caller, $arg = FALSE, $force = FALSE)
	{
		if (!self::$debug && !$force) {
			return;
		}
		if (is_callable('t')) {
			//p([
			//	'caller' => $caller . ($arg ? ": '$arg'" : ''),
			//	'cwd' => count(fs::$dirStack) . ' |    ' . getcwd() . '   ',
			//		], 3, 2);
			// return;
			t([
				'caller' => $caller . ($arg ? ": '$arg'" : ''),
				'rootPath' => self::$activeToRootPath,
				'x_x' => '-----------------------------',
				'cwd' => '|    ' . self::cwd() . '  ',
				'x-x' => '-----------------------------',
				'dirstack' => fs::$dirStack,
			], 3, 2);
		}
		// Kein t()/p() – erzeugen bei vielen openDir/closeDir Backtraces und führen zu Memory Exhaustion
		error_log('fs::debug ' . $caller . ($arg ? ": $arg" : '') . ' | cwd: ' . self::cwd() . ' | stack: ' . count(self::$dirStack));
	}

	static function load($classNames)
	{
		if (is_string($classNames)) {
			return self::load([$classNames]);
		}
		foreach ($classNames as $className) {
			// p(class_exists($className, TRUE));
			class_exists($className, TRUE); // trigger autoloader
		}
	}

	// *********************************** Cross-Platform Handling ***************

	static function toInternal(string $path): string
	{
		if (OS !== 'windows') return $path;
		$path = str_replace('\\', '/', $path);
		if (preg_match('~^([A-Za-z]):~', $path, $m)) {
			$path = '/' . $m[1] . substr($path, 2);
		}
		return $path;
	}

	static function toNative(string $path): string
	{
		if (OS !== 'windows') return $path;
		if (preg_match('~^/([A-Za-z])/~', $path, $m)) {
			$path = $m[1] . ':' . substr($path, 2);
		}
		return str_replace('/', '\\', $path);
	}

	static function cwd(): string
	{
		return self::toInternal(getcwd()) . '/';
	}

	static function isFile(string $path): bool
	{
		return \is_file(self::toNative($path));
	}

	static function isDir(string $path): bool
	{
		return \is_dir(self::toNative($path));
	}

	static function includeFile(string $path): void
	{
		include self::toNative($path);
	}
	static function requireFile(string $path): void
	{
		require self::toNative($path);
	}
	static function requireOnceFile(string $path): void
	{
		require_once self::toNative($path);
	}
	static function realpath(string $path): ?string
	{
		$native = self::toNative($path);
		$result = \realpath($native);
		return $result === false ? null : self::toInternal($result);
	}
	static function getFileContents(string $path): string
	{
		return file_get_contents(self::toNative($path));
	}
}
