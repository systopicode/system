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
		$constant = strtoupper($name) . '_BIN';
		if (defined($constant) && trim((string) constant($constant)) !== '') {
			return trim((string) constant($constant));
		}
		$isWindows = PHP_OS_FAMILY === 'Windows';
		$lookup = ($isWindows ? 'where ' : 'command -v ') . escapeshellarg($name);
		$output = shell_exec($lookup . ' 2>&1');
		if (!is_string($output)) {
			return NULL;
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
		return NULL;
	}
}
