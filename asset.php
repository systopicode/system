<?php

declare(strict_types=1);

/**
 * The files of the system, the packages and the project's own lib/, panels/
 * and site/ - for a public/ that cannot reach them (RC, LIVE: the document
 * root is public/, vendor/ and the project lie above it).
 * See SYS/.cursor/plans/asset-delivery.plan.md.
 *
 * public/.htaccess sends every /assets/… that is not a file there to the
 * project's public/asset.php, which only includes this file:
 *
 *   /assets/sys/<path>              this package (systopic/system)
 *   /assets/app/<path>              the project - lib/, panels/, site/ only
 *   /assets/<vendor>/<pkg>/<path>   a package that registered itself (Sys\Packages)
 *
 * Mode `serve`: from the original, every time. Otherwise (`publish`) the
 * file is also copied to public/assets/<same path>, so the next request for
 * it never comes here (the Magento way). A stylesheet goes out with its
 * url(…) versioned (Http\Assets::rewriteCss). Only asset types
 * (Http\Assets::TYPES), never PHP, never a hidden path, never anything
 * outside the root it names.
 *
 * Boots no system: the Composer autoloader (the packages register their
 * folders) and the config (the mode), nothing else.
 */
(static function (): void {
	$notFound = static function (): never {
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: no-store');
		exit('not found');
	};

	$publicDir = dirname((string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
	$projectDir = dirname($publicDir);
	$base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/assets/';
	$uri = rawurldecode((string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'));
	if (!str_starts_with($uri, $base)) {
		$notFound();
	}
	$rel = substr($uri, strlen($base));
	$segments = explode('/', $rel);
	foreach ($segments as $segment) {
		// no '..', no hidden files or folders, nothing a filesystem could read twice
		if ($segment === '' || $segment[0] === '.' || str_contains($segment, '\\') || str_contains($segment, "\0") || str_contains($segment, ':')) {
			$notFound();
		}
	}
	if (count($segments) < 2) {
		$notFound();
	}

	require $projectDir . '/vendor/autoload.php';

	$type = \Systopic\System\Http\Assets::TYPES[strtolower(pathinfo($rel, PATHINFO_EXTENSION))] ?? null;
	if ($type === null) {
		$notFound();
	}

	// the root the first segment(s) name
	$root = null;
	$inner = $segments;
	$first = array_shift($inner);
	if ($first === 'sys') {
		$root = __DIR__;
	} elseif ($first === 'app') {
		// the project: only the folders its assets live in - never config/, vendor/ …
		if (in_array($inner[0] ?? '', \Systopic\System\Http\Assets::APP_FOLDERS, true)) {
			$root = $projectDir;
		}
	} else {
		$package = $first . '/' . array_shift($inner);
		$dir = \Systopic\System\Sys\Packages::libDir($package);
		$root = $dir === null ? null : rtrim(str_replace('\\', '/', (string) $dir), '/');
	}
	if ($root === null || $inner === []) {
		$notFound();
	}
	$rootReal = realpath($root);
	$file = realpath($root . '/' . implode('/', $inner));
	if ($rootReal === false || $file === false || !is_file($file)) {
		$notFound();
	}
	$rootReal = rtrim(str_replace('\\', '/', $rootReal), '/') . '/';
	if (!str_starts_with(str_replace('\\', '/', $file), $rootReal)) {
		$notFound();
	}

	// the mode of this instance - anything but serve publishes
	$mode = null;
	try {
		$config = \Systopic\System\Config\Config::boot($projectDir . '/config', $_SERVER, $projectDir);
		$mode = $config->instance->get('assets');
	} catch (\Throwable) {
		// no config: publish, like an instance without the key
	}
	$mtime = (int) filemtime($file);
	if ($mode !== 'serve' && $mode !== 'source') {
		\Systopic\System\Http\Assets::publish($file, $publicDir . '/assets/' . $rel, $mtime);
	}

	$body = \Systopic\System\Http\Assets::isCss($file)
		? \Systopic\System\Http\Assets::rewriteCss((string) file_get_contents($file), $file)
		: null;
	$etag = '"' . dechex((int) filesize($file)) . '-' . dechex($mtime) . '"';
	header('Content-Type: ' . $type . (str_starts_with($type, 'text/') ? '; charset=utf-8' : ''));
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('ETag: ' . $etag);
	header('Cache-Control: no-cache'); // revalidate: cheap with the ETag, and a changed file shows at once
	$ifNoneMatch = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
	if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
		http_response_code(304);
		exit;
	}
	if ($body !== null) {
		header('Content-Length: ' . strlen($body));
		echo $body;
		return;
	}
	header('Content-Length: ' . filesize($file));
	readfile($file);
})();
