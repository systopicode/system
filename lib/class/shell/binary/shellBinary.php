<?php

/**
 * Resolves external CLI tools (ffmpeg, ffprobe, …) for shell_exec/exec calls.
 *
 * Override per tool with a constant, e.g. define('FFMPEG_BIN', 'C:\tools\ffmpeg.exe')
 * in the project index.php — otherwise the PATH is searched.
 */
class shellBinary {

	private static array $resolved = [];

	static function find(string $name): ?string {
		if (array_key_exists($name, self::$resolved)) {
			return self::$resolved[$name];
		}
		return self::$resolved[$name] = self::resolve($name);
	}

	static function isAvailable(string $name): bool {
		return self::find($name) !== NULL;
	}

	private static function resolve(string $name): ?string {
        if (!preg_match('/^[A-Za-z0-9_.-]+$/D', $name)) return null;
        foreach (['_BIN', '_BINARY'] as $suffix) {
            $constant = strtoupper($name) . $suffix;
            if (defined($constant) && trim((string) constant($constant)) !== '') return trim((string) constant($constant));
            $override = getenv($constant);
            if (is_string($override) && trim($override) !== '') return trim($override);
        }
		$isWindows = PHP_OS_FAMILY === 'Windows';
		$lookup = ($isWindows ? 'where ' : 'command -v ') . escapeshellarg($name);
		$output = function_exists('shell_exec') ? shell_exec($lookup . ' 2>&1') : null;
		if (!is_string($output)) {
			return self::searchPath($name, $isWindows) ?? self::projectBinary($name);
		}
		// Match a path, not the localised "not found" text `where` prints on stderr.
		// Do not stat the result: open_basedir may hide tool folders from is_file().
		$pattern = $isWindows ? '~^[a-z]:\\\\.+\.(exe|bat|cmd|com)$~i' : '~^/.+~';
		foreach (preg_split('/\R/', trim($output)) as $line) {
			$line = trim($line);
			if ($line !== '' && preg_match($pattern, $line)) {
				return $line;
			}
		}
		return self::searchPath($name, $isWindows) ?? self::projectBinary($name);
	}

	/**
	 * A shared host has no ffmpeg on the PATH. The project keeps its own
	 * copy in var/bin or bin, so the system package does not ship the binary.
	 */
	private static function projectBinary(string $name): ?string {
		if (!class_exists('fs', false)) {
			return null;
		}
		$roots = [];
		foreach (['siteRoot', 'projectRoot'] as $prop) {
			$root = (string) (fs::$$prop ?? '');
            if ($root !== '' && method_exists('fs', 'toNative')) $root = fs::toNative($root);
            $root = rtrim($root, '/\\');
			if ($root !== '') {
				$roots[] = $root;
			}
		}
		foreach (array_unique($roots) as $root) {
            $candidates = [$root . '/var/bin/' . $name, $root . '/bin/' . $name];
            if (function_exists('cmsFfmpegPlatform')) {
                $platform = cmsFfmpegPlatform();
                $candidates[] = $root . '/var/bin/' . $platform . '/' . $name;
                $candidates[] = $root . '/bin/' . $platform . '/' . $name;
            }
            foreach ([$root . '/var/bin/*/' . $name, $root . '/bin/*/' . $name] as $pattern) {
                foreach (glob($pattern) ?: [] as $path) $candidates[] = $path;
            }
            foreach ($candidates as $candidate) {
				if (@is_file($candidate) && @is_executable($candidate)) {
					return $candidate;
				}
			}
		}
		return null;
	}

	/**
	 * The PATH as PHP sees it, searched by hand.
	 *
	 * Under FastCGI (mod_fcgid, Windows) getenv('PATH') is the variable the
	 * request was handed, but a child process - `where`, the tool itself -
	 * inherits the web server's own environment. A tool installed after the
	 * server started is in the first and missing from the second: `where`
	 * finds nothing, the full path runs fine (2026-10-23, ImageMagick).
	 * The full path is what this returns, so the child's PATH no longer matters.
	 */
	private static function searchPath(string $name, bool $isWindows): ?string {
		$extensions = $isWindows ? ['.exe', '.bat', '.cmd', '.com'] : [''];
		foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
			$dir = rtrim(trim($dir, " \"'"), '/\\');
			if ($dir === '') {
				continue;
			}
			foreach ($extensions as $extension) {
				$candidate = $dir . DIRECTORY_SEPARATOR . $name . $extension;
				// @: a PATH entry may sit outside open_basedir
				if (@is_file($candidate)) {
					return $candidate;
				}
			}
		}
		return NULL;
	}
}
