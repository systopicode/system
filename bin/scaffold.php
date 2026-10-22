<?php

declare(strict_types=1);

/**
 * Scaffold — the files a project needs besides its own code.
 *
 * Run by composer, from the project's composer.json (the projects manager
 * writes this into every composer.json it creates):
 *
 *   "scripts": {
 *       "post-install-cmd": "@php vendor/systopic/system/bin/scaffold.php",
 *       "post-update-cmd":  "@php vendor/systopic/system/bin/scaffold.php"
 *   }
 *
 * composer runs scripts of the root package only, never those of a
 * dependency — hence the entry in the project, not a plugin (see
 * tools/.cursor/plans/projekt-setup.plan.md for the comparison).
 *
 * Every installed package may bring a scaffold — in its composer.json:
 *
 *   "extra": { "systopic-scaffold": { "dir": "scaffold/", "owned": ["public/index.php", …] } }
 *
 * The folder mirrors a project. A file listed under "owned" belongs to the
 * package and is written again whenever it differs (public/index.php,
 * .htaccess …); a block between "# >>> project" and "# <<< project" in the
 * project's copy survives that. Every other file is created once, when it is
 * missing, and belongs to the project from then on. systopic/system goes
 * first, the other packages follow in install order and may add or replace.
 *
 * A project without config/ gets a setup token (var/setup.token): the setup
 * page (Setup\Setup) only answers a local browser - or one holding it.
 *
 * Never fails the composer run: whatever cannot be written is reported, the
 * exit code stays 0.
 */

(static function (): void {
	$say = static fn(string $line) => fwrite(STDOUT, "scaffold: $line\n");

	try {
		$root = str_replace('\\', '/', (string) getcwd());
		if (!is_file("$root/composer.json") || !is_file("$root/vendor/composer/installed.json")) {
			$say("no project here ($root) - run it through composer from the project folder");
			return;
		}
		$installed = json_decode((string) file_get_contents("$root/vendor/composer/installed.json"), true);
		$packages = $installed['packages'] ?? $installed;
		if (!is_array($packages)) {
			$say('vendor/composer/installed.json is unreadable');
			return;
		}
		// systopic/system first: the others build on it
		usort($packages, static fn($a, $b) => (($b['name'] ?? '') === 'systopic/system') <=> (($a['name'] ?? '') === 'systopic/system'));

		$written = 0;
		foreach ($packages as $package) {
			$scaffold = $package['extra']['systopic-scaffold'] ?? null;
			if (!is_array($scaffold)) {
				continue;
			}
			$base = realpath("$root/vendor/composer/" . ($package['install-path'] ?? ''));
			$dir = $base === false ? false : realpath($base . '/' . trim((string) ($scaffold['dir'] ?? 'scaffold'), '/'));
			if ($dir === false) {
				$say(($package['name'] ?? '?') . ': scaffold folder not found');
				continue;
			}
			$owned = array_map('strval', (array) ($scaffold['owned'] ?? []));
			$written += scaffoldPackage((string) $package['name'], str_replace('\\', '/', $dir), $root, $owned, $say);
		}
		$say($written === 0 ? 'nothing to write' : "$written file(s) written");

		setupToken($root, $say);
	} catch (\Throwable $e) {
		$say('failed: ' . $e->getMessage());
	}
})();

/** copies one package's scaffold, returns how many files were written */
function scaffoldPackage(string $name, string $dir, string $root, array $owned, callable $say): int
{
	$written = 0;
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($files as $file) {
		if (!$file->isFile()) {
			continue;
		}
		$rel = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($dir)), '/');
		$target = "$root/$rel";
		$template = (string) file_get_contents($file->getPathname());
		$exists = is_file($target);

		if (!in_array($rel, $owned, true)) {
			if ($exists) {
				continue; // created once - the project's now
			}
			$content = $template;
		} else {
			$current = $exists ? (string) file_get_contents($target) : '';
			$content = keepProjectBlock($template, $current);
			if ($exists && $content === $current) {
				continue;
			}
			// a hand-written file is replaced the first time - keep it where
			// sync does not look (var/ is in its ignores), never next to it
			if ($exists && !str_contains($current, 'bin/scaffold.php')) {
				$backup = "$root/var/scaffold/" . $rel . '.' . date('Ymd-His');
				@mkdir(dirname($backup), 0775, true);
				@copy($target, $backup);
				$say("$rel: the previous version is in " . substr($backup, strlen($root) + 1));
			}
		}
		@mkdir(dirname($target), 0775, true);
		if (file_put_contents($target, $content) === false) {
			$say("$rel: cannot write");
			continue;
		}
		$say("$rel " . ($exists ? 'updated' : 'created') . " ($name)");
		$written++;
	}
	return $written;
}

/** the template, with the project block of the current file put back in */
function keepProjectBlock(string $template, string $current): string
{
	$pattern = '~# >>> project\R.*?# <<< project~s';
	if (!preg_match($pattern, $current, $kept) || !preg_match($pattern, $template)) {
		return $template;
	}
	return (string) preg_replace_callback($pattern, static fn() => $kept[0], $template, 1);
}

/**
 * Without an instance in config/, the setup page decides what this
 * installation is. It answers a local browser, everyone else needs the
 * token - printed here, so whoever ran composer can pass it on.
 */
function setupToken(string $root, callable $say): void
{
	$instances = array_filter(
		glob("$root/config/*.config.json") ?: [],
		static fn($file) => basename($file) !== 'config.local.json'
	);
	if (is_file("$root/config/config.json") && $instances !== []) {
		return;
	}
	$file = "$root/var/setup.token";
	$token = is_file($file) ? trim((string) file_get_contents($file)) : '';
	if ($token === '') {
		$token = bin2hex(random_bytes(16));
		@mkdir(dirname($file), 0775, true);
		file_put_contents($file, $token);
	}
	$say('not set up yet - open public/ in a browser; from a non-local address add ?setup=' . $token);
}
