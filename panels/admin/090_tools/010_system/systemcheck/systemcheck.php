<?php

/**
 * Can this server run fluxo? One file, no install.
 * Upload it, open it in the browser, then delete it.
 */

$bytes = static function (string $value): int {
	$value = trim($value);
	$number = (int) $value;
	return match (strtoupper(substr($value, -1))) {
		'G' => $number * 1024 ** 3,
		'M' => $number * 1024 ** 2,
		'K' => $number * 1024,
		default => $number,
	};
};

$format = static function (int $bytes): string {
	if ($bytes >= 1024 ** 3) {
		return round($bytes / 1024 ** 3, 1) . ' GB';
	}
	if ($bytes >= 1024 ** 2) {
		return round($bytes / 1024 ** 2) . ' MB';
	}
	return $bytes . ' B';
};

$row = static function (string $status, string $label): array {
	return ['status' => $status, 'label' => $label];
};

$cards = [];

$php = PHP_VERSION;
if (version_compare($php, '8.4.0', '>=')) {
	$cards['PHP'][] = $row('ok', "PHP $php");
} elseif (version_compare($php, '8.3.0', '>=')) {
	$cards['PHP'][] = $row('warn', "PHP $php. 8.3 can run it, Composer asks for 8.4.");
} else {
	$cards['PHP'][] = $row('bad', "PHP $php. At least 8.4 is required.");
}

$post = $bytes((string) ini_get('post_max_size'));
$upload = $bytes((string) ini_get('upload_max_filesize'));
$cards['PHP'][] = $row($post >= 128 * 1024 * 1024 ? 'ok' : 'warn', 'Post size ' . $format($post) . ($post >= 128 * 1024 * 1024 ? '' : '. 128 MB is the comfortable size.'));
$cards['PHP'][] = $row($upload >= 50 * 1024 * 1024 ? 'ok' : 'warn', 'Upload size ' . $format($upload) . ($upload >= 128 * 1024 * 1024 ? '' : '. 50 MB is the minimum, 128 MB is better for video.'));
if ($post < $upload) {
	$cards['PHP'][] = $row('bad', 'Post size is smaller than the upload size, so large uploads are rejected.');
}
$time = (int) ini_get('max_execution_time');
$cards['PHP'][] = $row($time === 0 || $time >= 60 ? 'ok' : 'warn', 'Script time ' . ($time === 0 ? 'unlimited' : $time . ' sec') . ($time !== 0 && $time < 60 ? '. At least 60 sec.' : ''));
$memory = $bytes((string) ini_get('memory_limit'));
$cards['PHP'][] = $row($memory === 0 || $memory >= 256 * 1024 * 1024 ? 'ok' : 'warn', 'Memory ' . ($memory === 0 ? 'unlimited' : $format($memory)) . ($memory !== 0 && $memory < 256 * 1024 * 1024 ? '. At least 256 MB.' : ''));
$cards['PHP'][] = $row(ini_get('display_errors') ? 'warn' : 'ok', ini_get('display_errors') ? 'Display errors is on. Turn it off on a live server.' : 'Display errors is off.');
$log = trim((string) ini_get('error_log'));
$cards['PHP'][] = $row(ini_get('log_errors') ? 'ok' : 'warn', ini_get('log_errors') ? 'Error log is on' . ($log !== '' ? " ($log)" : '') . '.' : 'Error log is off.');

$extensions = ['mysqlnd', 'xml', 'curl', 'dom', 'imagick', 'json', 'mbstring', 'mysqli', 'pdo_mysql', 'pgsql', 'simplexml', 'xmlreader', 'xmlwriter', 'xsl', 'zip', 'openssl', 'fileinfo', 'session', 'iconv', 'ctype', 'tokenizer'];
foreach ($extensions as $extension) {
	$cards['Extensions'][] = $row(extension_loaded($extension) ? 'ok' : (in_array($extension, ['mysqlnd','mysqli','pgsql','imagick','zip','xsl','xmlreader','xmlwriter'], true) ? 'warn' : 'bad'), $extension . (extension_loaded($extension) ? ' is loaded' : ' is not loaded'));
}
if (class_exists('Imagick', false)) {
	$version = Imagick::getVersion();
	preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', (string) ($version['versionString'] ?? ''), $found);
	$imagick = $found[1] ?? '?';
	$cards['Extensions'][] = $row(version_compare($imagick, '6.9.0', '>=') ? 'ok' : 'bad', "ImageMagick $imagick");
	$cards['Extensions'][] = $row(Imagick::queryFormats('PDF') !== [] ? 'ok' : 'warn', Imagick::queryFormats('PDF') !== [] ? 'PDF reading is allowed' : 'PDF reading is not allowed. Only matters for PDF uploads.');
	$cards['Extensions'][] = $row(Imagick::queryFormats('WEBP') !== [] ? 'ok' : 'bad', Imagick::queryFormats('WEBP') !== [] ? 'WEBP reading and writing is allowed' : 'WEBP is not allowed. Image formats will fail.');
}

$disabled = array_values(array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions')))));
foreach (['exec' => 'video frames', 'shell_exec' => 'finding programs', 'proc_open' => 'piped video'] as $function => $why) {
	$on = function_exists($function) && !in_array($function, $disabled, true);
	$cards['Functions'][] = $row($on ? 'ok' : 'warn', "$function() " . ($on ? 'is available' : 'is disabled') . " ($why)");
}
$cards['Functions'][] = $row($disabled === [] ? 'ok' : 'warn', $disabled === [] ? 'disable_functions is empty.' : 'disable_functions: ' . implode(', ', $disabled));

$which = static function (string $name) use ($disabled): ?string {
	foreach (array_unique([__DIR__, dirname(__DIR__), getcwd(), dirname(getcwd())]) as $root) {
        foreach (['var/bin','bin'] as $folder) {
        $dir = $root . '/' . $folder;
        foreach (array_merge([$dir . '/' . $name], glob($dir . '/*/' . $name) ?: []) as $path) {
		if (@is_file($path) && @is_executable($path)) {
			return $path;
		}
        }
        }
	}
	if (!function_exists('shell_exec') || in_array('shell_exec', $disabled, true)) {
		return null;
	}
	$found = trim((string) shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
	return $found !== '' ? $found : null;
};
foreach (['ffmpeg' => 'video frames', 'ffprobe' => 'video info', 'identify' => 'optional image metadata', 'magick' => 'optional ImageMagick command'] as $binary => $why) {
	$found = $which($binary);
	if ($found === null && (!function_exists('shell_exec') || in_array('shell_exec', $disabled, true))) {
		$cards['Programs'][] = $row('warn', "$binary could not be looked up ($why). shell_exec is disabled.");
		continue;
	}
	$cards['Programs'][] = $row($found !== null ? 'ok' : 'warn', $found !== null ? "$binary ($why): $found" : "$binary was not found ($why).");
}

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES);
echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>fluxo server check</title>';
echo '<style>
html{font-size:17.5px}
body{margin:0;background:#f0f3f4;color:#161c1f;font:1rem/1.45 system-ui,sans-serif}
main{max-width:52rem;margin:0 auto;padding:1.5rem}
h1{margin:0 0 .35rem;font-size:1.6rem;font-weight:650;letter-spacing:-.02em}
.lead{margin:0 0 1.25rem;color:#5c6b72}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(18rem,1fr));gap:1rem}
section{background:#fff;border:1px solid #d7dee2;border-radius:.75rem;overflow:hidden}
h2{margin:0;padding:.8rem 1rem;border-bottom:1px solid #e6ebed;font-size:1rem}
ul{list-style:none;margin:0;padding:.8rem 1rem;display:flex;flex-direction:column;gap:.4rem}
li{display:flex;align-items:center;gap:.5rem;margin:0;padding:.4rem .7rem;border-radius:.45rem;font-size:.875rem;line-height:1.4}
li.ok>span:first-child{color:#4fb95e}
li.warn>span:first-child{color:#a17600}
li.bad>span:first-child{color:#d6454c}
li.ok{background:color-mix(in srgb,#7cb249 18%,#fff)}
li.warn{background:color-mix(in srgb,#fcb60c 32%,#fff)}
li.bad{background:color-mix(in srgb,#c44536 14%,#fff)}
</style></head><body>';
echo '<main><h1>Server check</h1><p class="lead">Nothing was installed. This only asks whether fluxo can run here. Database, mail and folders are checked after the project is in place. Delete this file when you are done.</p><div class="grid">';
foreach ($cards as $title => $rows) {
	echo '<section><h2>' . $e($title) . '</h2><ul>';
	foreach ($rows as $item) {
		echo '<li class="' . $e($item['status']) . '"><span aria-hidden="true">' . (['ok'=>'✓','warn'=>'!','bad'=>'×'][$item['status']] ?? '•') . '</span><span>' . $e($item['label']) . '</span></li>';
	}
	echo '</ul></section>';
}
echo '</div></main></body></html>';
