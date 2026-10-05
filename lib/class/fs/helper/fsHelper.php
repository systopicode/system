<?php

class fsHelper {

	static function globrec($pattern, $flags = 0, $depth = 0) {
		$nativePattern = fs::toNative(fs::toInternal($pattern));
		$files = \glob($nativePattern, $flags);
		if (!--$depth) { // skip recursion
			return $files;
		}
		foreach (\glob(fs::toNative(fs::toInternal(dirname($pattern) . '/*')), GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
			if (0 === strpos($dir, './')) { // glob adds local dir prefix for some reason
				$dir = substr($dir, 2);
			}
			$files = array_merge($files, self::globrec($dir . '/' . basename($pattern), $flags, $depth));
		}
		return $files;
	}

	static function getFilesRec($fsDir, $depth = 0) {
		$files = new fsFiles;
		$files->join($fsDir->files);
		if (--$depth) {
			foreach ($fsDir->folders AS $folder) {
				$files->join(self::getFilesRec($folder, $depth));
			}
		}
		return $files;
	}

	// *******************************  HELPER ********************************

	static function toRootpath($dirpath) {
		return $dirpath[0] === '/' ? $dirpath : (fs::$root ? (string) fs::$root : '') . $dirpath;
	}

	static function splitName($name) {
		if (preg_match('~(\d+)_([^\.]+)(\.|\.([^\.]+)\.|)([^\.]*)$~', $name, $match)) {
			return (object) [
				    'name' => $match[2],
				    'extends' => $match[4],
				    'ext' => $match[5],
				    'node_ordering' => $match[1],
			];
		}
		return FALSE;
	}

	static function mkdir($dir) {
		$native = fs::toNative(fs::toInternal($dir));
		if (!\file_exists($native)) {
			\mkdir($native, 0777, true);
		}
	}

	static function unlinkDir($dir) {
		$native = fs::toNative(fs::toInternal($dir));
		$files = array_diff(\scandir($native), array('.', '..'));
		foreach ($files as $file) {
			$fullNative = fs::toNative(fs::toInternal("$dir/$file"));
			(\is_dir($fullNative)) ? self::unlinkDir("$dir/$file") : \unlink($fullNative);
		}
		return \rmdir($native);
	}

	/**
	 * Wrapper fuer file_get_contents() - Pfad wird fuer natives Dateisystem konvertiert
	 */
	static function file_get_contents(string $path, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false {
		$native = fs::toNative(fs::toInternal($path));
		return \file_get_contents($native, $use_include_path, $context, $offset, $length);
	}

	/**
	 * Wrapper fuer file_put_contents() - Pfad wird fuer natives Dateisystem konvertiert
	 */
	static function file_put_contents(string $path, mixed $data, int $flags = 0, $context = null): int|false {
		$native = fs::toNative(fs::toInternal($path));
		return \file_put_contents($native, $data, $flags, $context);
	}

	/**
	 * Wrapper fuer filemtime() - Pfad wird fuer natives Dateisystem konvertiert
	 */
	static function filemtime(string $path): int|false {
		$native = fs::toNative(fs::toInternal($path));
		return \filemtime($native);
	}

	/**
	 * Wrapper fuer touch() - Pfad wird fuer natives Dateisystem konvertiert
	 */
	static function touch(string $path, ?int $mtime = null, ?int $atime = null): bool {
		$native = fs::toNative(fs::toInternal($path));
		return $mtime !== null && $atime !== null
			? \touch($native, $mtime, $atime)
			: ($mtime !== null ? \touch($native, $mtime) : \touch($native));
	}

	// ******************************* Cross-Platform Wrappers ********************************

	static function fileExists(string $path): bool {
		return \file_exists(fs::toNative(fs::toInternal($path)));
	}

	static function filesize(string $path): int|false {
		return \filesize(fs::toNative(fs::toInternal($path)));
	}

	static function glob(string $pattern, int $flags = 0): array|false {
		$native = fs::toNative(fs::toInternal($pattern));
		return \glob($native, $flags);
	}

	static function scandir(string $dir, int $order = 0): array|false {
		return \scandir(fs::toNative(fs::toInternal($dir)), $order);
	}

	static function unlinkFile(string $path): bool {
		return \unlink(fs::toNative(fs::toInternal($path)));
	}

	static function fsRename(string $from, string $to): bool {
		return \rename(
			fs::toNative(fs::toInternal($from)),
			fs::toNative(fs::toInternal($to))
		);
	}

	static function fsCopy(string $from, string $to): bool {
		return \copy(
			fs::toNative(fs::toInternal($from)),
			fs::toNative(fs::toInternal($to))
		);
	}

	static function fsRmdir(string $path): bool {
		return \rmdir(fs::toNative(fs::toInternal($path)));
	}

	static function fsMkdir(string $path, int $permissions = 0777, bool $recursive = false): bool {
		return \mkdir(fs::toNative(fs::toInternal($path)), $permissions, $recursive);
	}

	static function chmod(string $path, int $mode): bool {
		return @\chmod(fs::toNative(fs::toInternal($path)), $mode);
	}

	static function fsOpendir(string $path) {
		return \opendir(fs::toNative(fs::toInternal($path)));
	}

	static function fopen(string $path, string $mode) {
		return \fopen(fs::toNative(fs::toInternal($path)), $mode);
	}

	static function chdir(string $path): bool {
		return \chdir(fs::toNative(fs::toInternal($path)));
	}

	static function isWritable(string $path): bool {
		return \is_writable(fs::toNative(fs::toInternal($path)));
	}

	static function clearstatcache(bool $clear_realpath_cache = false, string $path = ''): void {
		if ($path !== '') {
			\clearstatcache($clear_realpath_cache, fs::toNative(fs::toInternal($path)));
		} else {
			\clearstatcache($clear_realpath_cache);
		}
	}
}
