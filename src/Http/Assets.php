<?php

declare(strict_types=1);

namespace Systopic\System\Http;

/**
 * The version every asset URL carries, and the copies the mode `publish`
 * keeps under public/assets/ - see http::$assetsMode and
 * SYS/.cursor/plans/asset-delivery.plan.md.
 *
 * Every JS/CSS file the server hands the browser gets `?t=<its mtime>`: the
 * URL changes exactly when the file does, so the browser may keep it for a
 * year (the projects' .htaccess says immutable) and still never holds a
 * stale one. This replaces the global PUBLIC_VERSIONAPPENDIX - one value for
 * everything: time() on DEV (nothing cached at all), a date bumped by hand
 * on LIVE (everything fetched again).
 *
 * In mode `publish` the copy is checked at the same moment: missing, or
 * older than the original -> copied now. The copy and the URL change
 * together.
 */
final class Assets
{
	/** what may leave a root as an asset - extension => content type; never PHP */
	public const TYPES = [
		'js' => 'text/javascript', 'mjs' => 'text/javascript', 'css' => 'text/css', 'map' => 'application/json',
		'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
		'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
		'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
		'eot' => 'application/vnd.ms-fontobject',
	];

	/** where the project keeps assets - the only folders /assets/app/ may reach */
	public const APP_FOLDERS = ['lib', 'panels', 'site'];

	/** folders never walked when publishing everything */
	private const SKIP_FOLDERS = ['vendor', 'node_modules'];

	/** $href of the file at $path, with its version - its published copy fresh */
	public static function href(string $href, string $path): string
	{
		$mtime = self::mtime($path);
		if ($mtime === null) {
			return $href;
		}
		self::refresh($href, $path, $mtime);
		return $href . (str_contains($href, '?') ? '&' : '?') . 't=' . $mtime;
	}

	/**
	 * The version of a file of an asset root ('sys', 'app', 'systopic/cms')
	 * - the scripts the loader fetches (client::loadJS). NULL when the file
	 * is not there.
	 */
	public static function versionOf(string $root, string $src): ?int
	{
		$dir = self::rootDir($root);
		if ($dir === null) {
			return null;
		}
		$mtime = self::mtime($dir . $src);
		if ($mtime !== null) {
			self::refresh(\http::assetsRoot($root) . $src, $dir . $src, $mtime);
		}
		return $mtime;
	}

	/** the folder of an asset root, native, ending in '/' - NULL for one nobody registered */
	public static function rootDir(string $root): ?string
	{
		$dir = match ($root) {
			'app' => \fs::$projectRoot === null ? null : (string) \fs::$projectRoot,
			'sys' => \fs::$sysRoot === null ? null : (string) \fs::$sysRoot,
			default => \Systopic\System\Sys\Packages::libDir($root),
		};
		return $dir === null ? null : rtrim(str_replace('\\', '/', \fs::toNative($dir)), '/') . '/';
	}

	/**
	 * A copy that appears whole or not at all (temp file, then rename), with
	 * the original's mtime - which is what "fresh" is compared by. A
	 * stylesheet is copied rewritten (see rewriteCss()).
	 */
	public static function publish(string $source, string $target, int $mtime): bool
	{
		$targetDir = dirname($target);
		if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
			return false;
		}
		$temp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
		$written = self::isCss($source)
			? @file_put_contents($temp, self::rewriteCss((string) @file_get_contents($source), $source)) !== false
			: @copy($source, $temp);
		if (!$written) {
			@unlink($temp);
			return false;
		}
		@touch($temp, $mtime);
		if (@rename($temp, $target)) {
			return true;
		}
		@unlink($temp);
		return false;
	}

	public static function isCss(string $path): bool
	{
		return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'css';
	}

	/**
	 * A stylesheet whose relative url(…) carry the version of what they
	 * point to - `url(../fonts/x.woff2)` → `url(../fonts/x.woff2?t=<mtime>)` -
	 * so fonts and images the CSS loads change their URL when they change,
	 * like the files the server queues itself (Symfony's AssetMapper does
	 * the same). Absolute URLs, data:, root-relative and fragment-only
	 * references stay as they are, and so does one whose file is not there.
	 * $cssPath: where the stylesheet lies - the references resolve from there.
	 */
	public static function rewriteCss(string $css, string $cssPath): string
	{
		$dir = dirname(str_replace('\\', '/', $cssPath));
		return (string) preg_replace_callback('~url\(\s*([\'"]?)([^\'")]+)\1\s*\)~i', static function (array $m) use ($dir): string {
			$url = trim($m[2]);
			if ($url === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|/|#)~i', $url)) {
				return $m[0];
			}
			$fragment = '';
			if (($hash = strpos($url, '#')) !== false) {
				$fragment = substr($url, $hash);
				$url = substr($url, 0, $hash);
			}
			$query = '';
			if (($mark = strpos($url, '?')) !== false) {
				$query = substr($url, $mark);
				$url = substr($url, 0, $mark);
			}
			$mtime = @filemtime($dir . '/' . $url);
			if ($mtime === false) {
				return $m[0];
			}
			$query = $query === '' || $query === '?' ? "?t=$mtime" : "$query&t=$mtime";
			return 'url(' . $m[1] . $url . $query . $fragment . $m[1] . ')';
		}, $css);
	}

	/**
	 * Every asset root with its folder: the system, the packages that
	 * registered one, the project's own lib/, panels/ and site/.
	 *
	 * @return array<string, string> URL root name ('app/lib' for the project's folders) => native folder ending in '/'
	 */
	public static function roots(): array
	{
		$roots = [];
		foreach (['sys', ...array_keys(\Systopic\System\Sys\Packages::libs())] as $root) {
			$dir = self::rootDir($root);
			if ($dir !== null && is_dir($dir)) {
				$roots[$root] = $dir;
			}
		}
		$app = self::rootDir('app');
		foreach (self::APP_FOLDERS as $folder) {
			if ($app !== null && is_dir($app . $folder)) {
				$roots["app/$folder"] = $app . $folder . '/';
			}
		}
		return $roots;
	}

	/** where the published copies lie: public/assets/, native, ending in '/' */
	public static function publishedDir(): ?string
	{
		return \fs::$siteRoot === null ? null : rtrim(str_replace('\\', '/', \fs::toNative((string) \fs::$siteRoot)), '/') . '/assets/';
	}

	/**
	 * Every asset of every root into public/assets/ - what is missing or
	 * older than its original. What the page load and asset.php do one file
	 * at a time, for all of them at once (after a package update, so no
	 * file waits for its first visitor).
	 *
	 * @return array{copied: int, fresh: int, failed: int}
	 */
	public static function publishAll(): array
	{
		$count = ['copied' => 0, 'fresh' => 0, 'failed' => 0];
		$target = self::publishedDir();
		if ($target === null) {
			return $count;
		}
		foreach (self::roots() as $name => $dir) {
			foreach (self::files($dir) as $rel) {
				$mtime = (int) filemtime($dir . $rel);
				$copy = $target . $name . '/' . $rel;
				if (is_file($copy) && (int) filemtime($copy) >= $mtime) {
					$count['fresh']++;
				} elseif (self::publish($dir . $rel, $copy, $mtime)) {
					$count['copied']++;
				} else {
					$count['failed']++;
				}
			}
		}
		return $count;
	}

	/**
	 * public/assets/ removed - every copy is made again on its first request
	 * (asset.php) or page load. The repair for anything.
	 *
	 * @return int files removed
	 */
	public static function clearAll(): int
	{
		$dir = self::publishedDir();
		if ($dir === null || !is_dir($dir)) {
			return 0;
		}
		$removed = 0;
		$items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($items as $item) {
			if ($item->isDir()) {
				@rmdir($item->getPathname());
			} elseif (@unlink($item->getPathname())) {
				$removed++;
			}
		}
		@rmdir($dir);
		return $removed;
	}

	/**
	 * What the assets panel shows.
	 *
	 * @return array{mode: string, dir: ?string, files: int, bytes: int, newest: ?int}
	 */
	public static function status(): array
	{
		$dir = self::publishedDir();
		$files = 0;
		$bytes = 0;
		$newest = null;
		if ($dir !== null && is_dir($dir)) {
			foreach (self::files($dir) as $rel) {
				$files++;
				$bytes += (int) filesize($dir . $rel);
				$newest = max($newest ?? 0, (int) filemtime($dir . $rel));
			}
		}
		return ['mode' => \http::$assetsMode, 'dir' => $dir, 'files' => $files, 'bytes' => $bytes, 'newest' => $newest];
	}

	/**
	 * The asset files below $dir, relative - allowed types only, no hidden
	 * files or folders, no vendor/node_modules.
	 *
	 * @return list<string>
	 */
	private static function files(string $dir): array
	{
		$out = [];
		$items = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			static fn(\SplFileInfo $item): bool => $item->getFilename()[0] !== '.'
				&& !($item->isDir() && in_array($item->getFilename(), self::SKIP_FOLDERS, true)),
		));
		foreach ($items as $item) {
			if ($item->isFile() && isset(self::TYPES[strtolower($item->getExtension())])) {
				$out[] = str_replace('\\', '/', substr($item->getPathname(), strlen($dir)));
			}
		}
		return $out;
	}

	/** mode publish: the copy behind an /assets/ URL, made or renewed when it is missing or older */
	private static function refresh(string $href, string $path, int $mtime): void
	{
		if (\http::$assetsMode !== 'publish' || \fs::$siteRoot === null) {
			return;
		}
		$prefix = \http::$root . 'assets/';
		if (!str_starts_with($href, $prefix)) {
			return;
		}
		$target = rtrim(str_replace('\\', '/', \fs::toNative((string) \fs::$siteRoot)), '/') . '/' . substr($href, strlen(\http::$root));
		if (is_file($target) && (int) filemtime($target) >= $mtime) {
			return;
		}
		self::publish(\fs::toNative($path), $target, $mtime);
	}

	private static function mtime(string $path): ?int
	{
		$mtime = @filemtime(\fs::toNative($path));
		return $mtime === false ? null : $mtime;
	}
}
