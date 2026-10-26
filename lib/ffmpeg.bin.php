<?php
declare(strict_types=1);
if (function_exists('cmsFindBinary')) {
	return;
}
/**
 * ffmpeg/ffprobe für Shared Hosting (IONOS u. a.):
 * System-Binary, sonst Fallback var/bin/ oder bin/ (statische Linux-64-Builds).
 */

function cmsSiteRootForBinaries(): string {
	if (class_exists('fs', false)) {
		if (!empty(fs::$siteRoot)) {
			return rtrim((string) fs::$siteRoot, '/');
		}
		if (!empty(fs::$sitePath)) {
			return rtrim((string) fs::$sitePath, '/');
		}
	}
	return dirname(__DIR__);
}

function cmsBinaryHostCompatible(string $path): bool {
	if (!is_file($path) || !is_readable($path)) {
		return false;
	}
	$fh = fopen($path, 'rb');
	if ($fh === false) {
		return false;
	}
	$head = (string) fread($fh, 64);
	fclose($fh);
	$magic = substr($head, 0, 4);
	if (PHP_OS_FAMILY === 'Darwin') {
		return in_array($magic, [
			"\xCF\xFA\xED\xFE",
			"\xCE\xFA\xED\xFE",
			"\xFE\xED\xFA\xCF",
			"\xFE\xED\xFA\xCE",
			"\xCA\xFE\xBA\xBE",
		], true);
	}
	if (!str_starts_with($head, "\x7fELF")) {
		return false;
	}
	$mach = unpack('v', substr($head, 18, 2) . "\0\0")[1] ?? 0;
	$m = strtolower(php_uname('m'));
	if (str_contains($m, 'aarch64') || str_contains($m, 'arm64')) {
		return $mach === 183;
	}
	if (str_contains($m, 'x86_64') || str_contains($m, 'amd64')) {
		return $mach === 62;
	}
	if (preg_match('/arm/', $m) === 1) {
		return $mach === 40 || $mach === 183;
	}
	return true;
}

function cmsBinaryRunnable(string $path): bool {
	if ($path === '' || !is_file($path) || is_dir($path)) {
		return false;
	}
	if (!is_executable($path)) {
		@chmod($path, 0755);
	}
	return is_executable($path) && cmsBinaryHostCompatible($path);
}

function cmsFindBinary(string $name, ?string $root = null): string {
	static $cache = [];
	$root = $root ?? cmsSiteRootForBinaries();
	$key = $root . "\0" . $name;
	if (isset($cache[$key])) {
		return $cache[$key];
	}

	$candidates = [];
	$const = strtoupper($name) . '_BINARY';
	if (defined($const) && (string) constant($const) !== '') {
		$candidates[] = (string) constant($const);
	}
	$env = getenv($const);
	if (is_string($env) && $env !== '') {
		$candidates[] = $env;
	}

	foreach ([
		'/opt/homebrew/bin/' . $name,
		'/usr/local/bin/' . $name,
		'/Applications/ServBay/bin/' . $name,
		'/usr/bin/' . $name,
	] as $sys) {
		$candidates[] = $sys;
	}

	$shellOk = function_exists('shell_exec')
		&& !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
	if ($shellOk) {
		$fromPath = trim((string) @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
		if ($fromPath !== '') {
			$candidates[] = $fromPath;
		}
	}

	$candidates[] = $root . '/var/bin/' . $name;
	$candidates[] = $root . '/bin/' . $name;
	foreach (glob($root . '/var/bin/*/' . $name) ?: [] as $g) {
		$candidates[] = $g;
	}
	foreach (glob($root . '/bin/*/' . $name) ?: [] as $g) {
		$candidates[] = $g;
	}
	$home = getenv('HOME') ?: (string) ($_SERVER['HOME'] ?? '');
	if ($home !== '') {
		$candidates[] = rtrim($home, '/') . '/bin/' . $name;
	}

	foreach ($candidates as $c) {
		$c = trim((string) $c);
		if ($c !== '' && cmsBinaryRunnable($c)) {
			return $cache[$key] = $c;
		}
	}
	return $cache[$key] = '';
}

function cmsFfmpegPlatform(): string {
	$m = strtolower(php_uname('m'));
	if (PHP_OS_FAMILY === 'Darwin') {
		return 'osx-64';
	}
	if (str_contains($m, 'aarch64') || str_contains($m, 'arm64')) {
		return 'linux-arm64';
	}
	if (str_contains($m, 'arm')) {
		return 'linux-armhf';
	}
	return 'linux-64';
}

function cmsHttpGetBinary(string $url): string {
	$tmp = tempnam(sys_get_temp_dir(), 'ffb');
	if ($tmp === false || !cmsHttpSaveBinary($url, $tmp)) {
		if (is_string($tmp) && $tmp !== '') {
			@unlink($tmp);
		}
		return '';
	}
	$data = (string) file_get_contents($tmp);
	@unlink($tmp);
	return $data;
}

function cmsHttpSaveBinary(string $url, string $dest, ?callable $onTick = null): bool {
	@set_time_limit(180);
	if (function_exists('curl_init')) {
		$fh = fopen($dest, 'wb');
		if ($fh === false) {
			return false;
		}
		$ch = curl_init($url);
		$opts = [
			CURLOPT_FILE => $fh,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => 180,
			CURLOPT_USERAGENT => 'stefanpohlfilm-systemcheck',
		];
		if ($onTick) {
			$opts[CURLOPT_NOPROGRESS] = false;
			$opts[CURLOPT_PROGRESSFUNCTION] = static function ($ch, $total, $now, $uploadTotal, $uploaded) use ($onTick): int {
				static $last = 0.0;
				$t = microtime(true);
				if ($t - $last > 2) {
					$last = $t;
					$onTick($total > 0 ? (int) floor($now / $total * 100) : 0);
				}
				return 0;
			};
		}
		curl_setopt_array($ch, $opts);
		$ok = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		fclose($fh);
		if ($ok && $code >= 200 && $code < 300 && is_file($dest) && filesize($dest) > 0) {
			return true;
		}
		@unlink($dest);
		return false;
	}
	$ctx = stream_context_create([
		'http' => ['timeout' => 180, 'follow_location' => 1, 'header' => "User-Agent: stefanpohlfilm-systemcheck\r\n"],
	]);
	$in = @fopen($url, 'rb', false, $ctx);
	$out = @fopen($dest, 'wb');
	if ($in === false || $out === false) {
		if (is_resource($in)) {
			fclose($in);
		}
		if (is_resource($out)) {
			fclose($out);
		}
		@unlink($dest);
		return false;
	}
	$copied = stream_copy_to_stream($in, $out);
	fclose($in);
	fclose($out);
	if ($copied === false || !is_file($dest) || filesize($dest) === 0) {
		@unlink($dest);
		return false;
	}
	return true;
}

/**
 * Statische Builds nach var/bin/ (Shared Hosting ohne System-ffmpeg).
 * @return array{ok:bool,detail:string}
 */
function cmsInstallFfmpegBinaries(?string $root = null, ?callable $onProgress = null): array {
	$root = $root ?? cmsSiteRootForBinaries();
	$dir = $root . '/var/bin';
	$say = static function (string $msg) use ($onProgress): void {
		if ($onProgress) {
			$onProgress($msg);
		}
	};
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		return ['ok' => false, 'detail' => 'var/bin/ konnte nicht angelegt werden'];
	}
	if (!is_writable($dir)) {
		return ['ok' => false, 'detail' => 'var/bin/ ist nicht schreibbar'];
	}
	if (!class_exists('ZipArchive')) {
		return ['ok' => false, 'detail' => 'PHP-ZipArchive fehlt – Binaries per FTP nach var/bin/ legen'];
	}

	$say('Plattform ' . cmsFfmpegPlatform());
	$json = cmsHttpGetBinary('https://ffbinaries.com/api/v1/version/latest');
	$meta = json_decode($json, true);
	$platform = cmsFfmpegPlatform();
	$pack = $meta['bin'][$platform] ?? null;
	if (!is_array($pack) || empty($pack['ffmpeg']) || empty($pack['ffprobe'])) {
		return ['ok' => false, 'detail' => 'Keine Download-URL für Plattform ' . $platform];
	}

	foreach (['ffmpeg', 'ffprobe'] as $name) {
		$tmp = $dir . '/' . $name . '.zip';
		$say($name . ' wird geladen');
		if (!cmsHttpSaveBinary((string) $pack[$name], $tmp, static function (int $pct) use ($say, $name): void {
			$say($name . ' ' . $pct . '%');
		})) {
			return ['ok' => false, 'detail' => 'Download fehlgeschlagen: ' . $name];
		}
		$zip = new ZipArchive();
		if ($zip->open($tmp) !== true) {
			@unlink($tmp);
			return ['ok' => false, 'detail' => 'ZIP defekt: ' . $name];
		}
		$target = $dir . '/' . $name;
		$entryName = '';
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->getNameIndex($i);
			if ($entry === false || str_ends_with($entry, '/')) {
				continue;
			}
			$base = basename($entry);
			if ($base === $name || $base === $name . '.exe') {
				$entryName = $entry;
				break;
			}
		}
		$extracted = $entryName !== '' && $zip->extractTo($dir, $entryName);
		$zip->close();
		@unlink($tmp);
		$got = $dir . '/' . $entryName;
		if (!$extracted || !is_file($got)) {
			return ['ok' => false, 'detail' => $name . ' nicht im ZIP gefunden'];
		}
		if ($got !== $target) {
			@unlink($target);
			if (!@rename($got, $target)) {
				return ['ok' => false, 'detail' => $name . ' konnte nicht nach var/bin/ gelegt werden'];
			}
		}
		@chmod($target, 0755);
		if (!cmsBinaryRunnable($target)) {
			return ['ok' => false, 'detail' => $name . ' liegt in var/bin/, ist aber nicht ausführbar (chmod 755)'];
		}
		$say($name . ' liegt in var/bin/');
	}

	return ['ok' => true, 'detail' => 'ffmpeg + ffprobe in ' . $dir];
}
