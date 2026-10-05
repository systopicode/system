<?php

#[AllowDynamicProperties]
class fsAutoloader {

	static $fileInfoByClassname = []; // cache - speedup => 2do session vals

	static function loadClass($className) {
		self::debug() && self::debug('start autoload on class', $className);

		// PSR-4: Systopic\System\ -> <sysRoot>/src/
		$psr4Prefix = 'Systopic\\System\\';
		if (str_starts_with($className, $psr4Prefix)) {
			$relative = str_replace('\\', '/', substr($className, strlen($psr4Prefix)));
			$psr4File = fs::$sysRoot . 'src/' . $relative . '.php';
			if (fs::isFile($psr4File)) {
				fs::includeFile($psr4File);
			}
			return;
		}

		$fileInfo = self::getFileInfoByClassname($className);
		if ($fileInfo) {
			fs::includeFile($fileInfo->dir . $fileInfo->file);
			if (!class_exists($className) && !trait_exists($className)) {
				p("autoloader failed on class/trait $className - file $fileInfo->dir$fileInfo->file does not contain class definition.");
				d($fileInfo);
			}
			// p($fileInfo);
			self::$fileInfoByClassname[$fileInfo->className]->reflectionClass = new ReflectionClass($fileInfo->className);
		}
		if (trait_exists($className)) {
			return;
		}
		if (class_exists($className)) {
			if ($className !== 'client' && class_exists('clientInterfaces', FALSE)) {
				clientInterfaces::register($fileInfo);
			}
		} else {
			// may check if further autoloaders are registred before stopping script
			// d(spl_autoload_functions());
			if (self::debug()) {
				return;
			}
			// self::debugAutoloader($className);

			// echo "autoloader failed on class '$className'<br>";
			// echo '$fileInfo(' . gettype($fileInfo) . ')<br>';
			// echo "<pre>" . print_r($fileInfo, 1) . "</pre>";
			// p("autoloader failed on class '$className'", $fileInfo)->openValues();
			// echo "<pre>" . print_r(debug_backtrace(), 1) . "</pre>";
			// exit;
		}
	}

	static function getDirpathByQueryBasename($className) { // used by dbConventions
		return self::getDirpathByCollectionClassname($className);
	}

	static function getDirpathByCollectionClassname($className) { // used by dbTableObs -> maybe join with autoloader
		$addslashes = strtolower(preg_replace('~[A-Z]~', '/$0', $className)); //add slashes before uppercase Letters
		$classPath = 'lib/class/' . preg_replace('~_.*$~', '', $addslashes) . '/';
		// cutoff related classes appendix --> ??? example ... error on underscore in foldernames ???
		$classPathSingular = substr($classPath, 0, -2) . '/';
		// Project first (projectRoot/lib/class/… — e.g. colors → color/), then
		// the system, then the packages that announced a lib root
		// (system-legacy). A folder can be split between system and a package
		// (lib/class/media: the generator here, the record class `medias` with
		// its medias.sql in system-legacy) — so the folder that holds the
		// collection's own .sql wins; otherwise the first that exists.
		$roots = [];
		if (fs::$projectRoot) {
			$roots[] = (string) fs::$projectRoot;
		}
		$roots[] = (string) fs::$sysRoot;
		foreach (\Systopic\System\Sys\Packages::libs() as $dir) {
			$roots[] = fs::toInternal($dir);
		}
		$first = NULL;
		foreach ($roots as $root) {
			foreach ([$classPath, $classPathSingular] as $path) {
				if (!fs::isDir($root . $path)) {
					continue;
				}
				if (fs::isFile($root . $path . $className . '.sql')) {
					return $root . $path;
				}
				$first ??= $root . $path;
			}
		}
		if ($first !== NULL) {
			return $first;
		}
		// singular/plural could also be in parent folders
		d("dir not found for collectionClassname '$className'");
	}

	static function getFileInfoByClassname($className) {
		if (self::debug() || !key_exists($className, self::$fileInfoByClassname)) {
			self::$fileInfoByClassname[$className] = self::evaluateFileInfoByClassname($className);
		}
		return self::$fileInfoByClassname[$className];
	}

	static function evaluateFileInfoByClassname($className) { //
		self::debug() && self::debug('uc first or uppercase convention', preg_match('~^[A-Z][A-Za-z]+$~', $className));
		if (preg_match('~^[A-Z][A-Za-z]+$~', $className)) { // uc first or uppercase convention
			$classNameLc = strtolower($className);
			$file = "$classNameLc.php";
			$extPath = "ext/$classNameLc/";
			if (fs::isFile(fs::$sysRoot . "ext/$className.php")) { // cms class 
				return (object) [
					    'className' => $className,
					    'path' => "ext/",
					    'dir' => fs::$sysRoot . "ext/",
					    'file' => "$className.php"
				];
			}
			if (fs::isFile(fs::$sysRoot . $extPath . $file)) { // cms class 
				return (object) [
					    'className' => $className,
					    'path' => $extPath,
					    'dir' => fs::$sysRoot . $extPath,
					    'file' => $file
				];
			}
			if (fs::isFile(fs::$sysRoot . $extPath . $file)) { // app class
				return (object) [
					    'className' => $className,
					    'path' => $extPath,
					    'dir' => fs::$sysRoot . $extPath,
					    'file' => $file
				];
			}
			if (fs::isFile(fs::$sysRoot . "src/$className.php")) {
				return (object) [
					    'className' => $className,
					    'path' => 'src/',
					    'dir' => fs::$sysRoot . 'src/',
					    'file' => "$className.php"
				];
			}
			return FALSE;
		}
		// systopic convention
		$file = "$className.php";
		// add slashes before uppercase Letters
		$addslashes = strtolower(preg_replace('~[A-Z]+~', '/$0', $className));
		$basePath = 'lib/class/';
		if (strpos($className, 'trait_') === 0) {
			$addslashes = substr($addslashes, 6);
			$basePath = 'lib/traits/';
			self::debug() && self::debug("trait: ", $basePath);
			// d($basePath . preg_replace('~_.*$~', '', $addslashes) . '/');
		}
		$path = $basePath . preg_replace('~_.*$~', '', $addslashes) . '/';
		$appPath = $path;
		// ***************** first - check project/app class
		// Project domain classes live under fs::$projectRoot/lib/class/
		// (not public/). root='app' tells client::loadJSfromFileInfo() /
		// loader.js to resolve companion JS via the projectRoot URL prefix
		// (DEV/STAGING). System classes fall through to root='sys'.
		$appDir = fs::$projectRoot . $appPath;
		self::debug() && self::debug("app: fs::isDir($appDir)", fs::isDir($appDir));
		if (!fs::isDir($appDir)) {
			$appPath = preg_replace('~s\/$~', '/', $appPath);
			self::debug() && self::debug("remove plural 's'", $appPath);
		}
		if (!fs::isDir(fs::$projectRoot . $appPath)) {
			$appPath = preg_replace('~ie\/$~', 'y/', $appPath); // queries -> qery
			self::debug() && self::debug("repl plural 'ie' by 'y'", $appPath);
		}
		$dir = fs::$projectRoot . $appPath;
		if (fs::isFile($dir . $file)) {
			return (object) [
				    'className' => $className,
				    'root' => 'app',
				    'path' => $appPath,
				    'dir' => $dir,
				    'file' => $file
			];
		}
		// ***************** 2nd - check system class
		$sysDir = fs::$sysRoot . $path;
		self::debug() && self::debug("sys: fs::isDir($sysDir)", fs::isDir($sysDir));
		if (!fs::isDir($sysDir)) {
			$path = preg_replace('~s\/$~', '/', $path); // remove plural s find settings.php in setting folder
			self::debug() && self::debug("remove plural 's'", $path);
		}
		
		if (!fs::isDir(fs::$sysRoot . $path)) {
			$path = preg_replace('~ie\/$~', 'y/', $path); // queries -> qery
		}
		
		self::debug() && self::debug("sys check file", fs::$sysRoot . $path . $file);
		if (fs::isFile(fs::$sysRoot . $path . $file)) {
			return (object) [
				    'className' => $className,
				    'root' => 'sys',
				    'path' => $path,
				    'dir' => fs::$sysRoot . $path,
				    'file' => $file
			];
		}
		
		// ***************** 3rd - the packages that announced a lib root (system-legacy)
		foreach (\Systopic\System\Sys\Packages::libs() as $root => $dir) {
			$dir = fs::toInternal($dir);
			$pkgPath = $basePath . preg_replace('~_.*$~', '', $addslashes) . '/';
			if (!fs::isDir($dir . $pkgPath)) {
				$pkgPath = preg_replace('~s\/$~', '/', $pkgPath);
			}
			if (!fs::isDir($dir . $pkgPath)) {
				$pkgPath = preg_replace('~ie\/$~', 'y/', $pkgPath);
			}
			if (fs::isFile($dir . $pkgPath . $file)) {
				return (object) [
					    'className' => $className,
					    'root' => $root,
					    'path' => $pkgPath,
					    'dir' => $dir . $pkgPath,
					    'file' => $file
				];
			}
		}
		
		self::debug() && self::debug("check file in basePath", fs::$sysRoot . $basePath . $file);
		if (fs::isFile(fs::$sysRoot . $basePath . $file)) {
			// e.g.
			// fs::$sysRoot/lib/traits/trait_dynamicProperties.php
			return (object) [
				    'className' => $className,
				    'root' => 'sys',
				    'path' => $path,
				    'dir' => fs::$sysRoot . $basePath,
				    'file' => $file
			];
		}
		
		return FALSE; // nothing found ... continue autoloader
	}

	static $debugInfo = [];
	static $debug = FALSE;

	static function debugAutoloader($className) {
		self::$debug = TRUE;
		self::loadClass($className);
		p('autoload log:', self::$debugInfo);
		self::$debugInfo = [];
		self::$debug = FALSE;
	}

	static function debug($key = FALSE, $value = NULL) {
		if ($key === FALSE) {
			return self::$debug;
		}
		self::$debugInfo[$key] = $value;
	}
}
