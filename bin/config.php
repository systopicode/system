<?php

declare(strict_types=1);

/**
 * The config files of the project in the current folder: config.json, the
 * project, and config.local.json, this installation.
 *
 *   php vendor/systopic/system/bin/config.php --check
 *       what is unknown, repeated or in the wrong file. Writes nothing.
 *   php vendor/systopic/system/bin/config.php --adopt[=<instance>]
 *       takes an old instance file (DEV.ojhome.config.json …) over into
 *       config.local.json: its values, its name, and the site addresses its
 *       rules spell out. Without a name the one whose rules fit this machine.
 *       What config.local.json already says wins. The old file stays, the
 *       other machines adopt theirs; delete them all afterwards.
 *   php vendor/systopic/system/bin/config.php --prune-defaults
 *       takes out of config.local.json what only repeats the package
 *       defaults or config.json.
 *   php vendor/systopic/system/bin/config.php --import-settings
 *       moves what the definitions know of Global Settings (`x-constant`:
 *       PUBLIC_PAGE_NAME, META_*, LANGUAGES …) into config.json: from
 *       config/global.settings.json, or the single-line define()s of a
 *       settings.php. What moved leaves the source; an empty
 *       global.settings.json goes. What config.json has already stays.
 *
 * Every file that changes is copied to var/config-backup/<time>/ first. See
 * .cursor/docs/config-struktur.md.
 */

use Systopic\System\Config\Config;
use Systopic\System\Config\Explain;
use Systopic\System\Config\Layout;
use Systopic\System\Config\MachineRules;
use Systopic\System\Config\Reconcile;
use Systopic\System\Config\Tree;
use Systopic\System\Setup\Setup;

$root = str_replace('\\', '/', (string) getcwd());
if (!is_file("$root/vendor/autoload.php") || !is_dir("$root/config")) {
	fwrite(STDERR, "run this in a project: no vendor/autoload.php or config/ here\n");
	exit(2);
}
require "$root/vendor/autoload.php";

$say = static fn(string $line) => print($line . "\n");
$read = static fn(string $file): ?array => is_file("$root/config/$file") ? Setup::readJson("$root/config/$file") : [];

$stamp = date('Ymd-His');
$backup = static function (string $file) use ($root, $stamp): void {
	if (!is_file("$root/config/$file")) {
		return;
	}
	$dir = "$root/var/config-backup/$stamp";
	@mkdir($dir, 0775, true);
	copy("$root/config/$file", "$dir/$file");
	@chmod("$dir/$file", 0600);
};

/** the layers as Explain sees them, each the `packages` of its file */
$layers = static function () use ($read): array {
	return [
		'project' => (array) (Layout::lift((array) $read('config.json'))['packages'] ?? []),
		'local' => (array) (Layout::lift((array) $read('config.local.json'))['packages'] ?? []),
	];
};

/** the stage config.local.json names, '' without one */
$stage = static function () use ($layers): string {
	$name = (string) ($layers()['local']['system']['instance'] ?? '');
	return $name === '' ? '' : strtoupper(explode('.', $name)[0]);
};

/** @return array<string, string> the old instance files: name => file */
$oldFiles = static function () use ($root): array {
	$found = [];
	foreach (glob("$root/config/*.config.json") ?: [] as $path) {
		$found[basename($path, '.config.json')] = basename($path);
	}
	ksort($found, SORT_STRING);
	return $found;
};

$option = $argv[1] ?? '';

if ($option === '--check') {
	$problems = 0;
	foreach (['config.json', 'config.local.json'] as $file) {
		$data = $read($file);
		if ($data === null) {
			$say("$file: no plain JSON, not checked");
			$problems++;
			continue;
		}
		if (array_intersect(array_keys($data), Layout::MOVED) !== []) {
			$say("$file: old layout");
		}
		foreach (Reconcile::findings($file, $data) as $finding) {
			$name = trim($finding['package'] . '.' . $finding['path'], '.');
			match ($finding['status']) {
				Reconcile::FOREIGN => $say("$file: $name is not installed here, it stays"),
				default => [$say("$file: $name: " . $finding['status']), $problems++],
			};
		}
	}
	if (($layers()['local']['system']['instance'] ?? '') === '') {
		$say('config.local.json: names no installation (packages.system.instance), --adopt takes an old instance file over');
		$problems++;
	}
	$redundant = Explain::redundantIn($layers(), 'local', $stage());
	if ($redundant !== []) {
		$say('config.local.json: ' . count($redundant) . ' values repeat what applies anyway, --prune-defaults takes them out');
	}
	if ($oldFiles() !== []) {
		$say('old instance files, no longer read: ' . implode(', ', $oldFiles()));
	}
	$say($problems === 0 ? 'fine' : "$problems to look at");
	exit($problems === 0 ? 0 : 1);
}

if ($option === '--adopt' || str_starts_with($option, '--adopt=')) {
	$entries = [];
	foreach ($oldFiles() as $name => $file) {
		$data = $read($file);
		if (is_array($data)) {
			$entries[$name] = $data;
		}
	}
	$name = substr($option, strlen('--adopt='));
	if ($name === '' || $option === '--adopt') {
		$ip = \Systopic\System\Http::getSocketIp();
		$hit = MachineRules::select(array_filter($entries, static fn(array $e): bool => !empty($e['matches'])), ['SERVER_NAME' => $ip, 'HTTP_HOST' => $ip, 'SERVER_ADDR' => $ip], $root);
		$name = $hit[0] ?? '';
		if ($name === '') {
			fwrite(STDERR, "no instance file fits this machine ($ip): name it, --adopt=<instance> (" . implode(', ', array_keys($entries)) . ")\n");
			exit(1);
		}
	}
	if (!isset($entries[$name])) {
		fwrite(STDERR, "$name.config.json cannot be read\n");
		exit(1);
	}
	$local = $read('config.local.json');
	if ($local === null) {
		fwrite(STDERR, "config.local.json is no plain JSON: adopt by hand\n");
		exit(1);
	}
	$old = Layout::lift($entries[$name]);
	$local = Layout::lift($local);
	// what config.local.json says already wins, as it did over the instance file
	$packages = Config::merge((array) ($old['packages'] ?? []), (array) ($local['packages'] ?? []));
	$packages['system']['instance'] = $name;
	// the rules, as far as they spell out where the sites answer
	$addresses = [];
	foreach ((array) ($old['matches'] ?? []) as $rule) {
		$site = is_array($rule) ? (string) ($rule['site'] ?? '') : '';
		if ($site === '') {
			continue;
		}
		$label = substr($site, 0, (int) (strrpos($site, '.') ?: strlen($site)));
		$host = '';
		foreach ((array) ($rule['serverName'] ?? []) as $each) {
			if (!filter_var((string) $each, FILTER_VALIDATE_IP) && !str_contains((string) $each, '*') && $each !== 'dev.local') {
				$host = strtolower((string) $each);
				break;
			}
		}
		$path = strtolower(trim((string) (((array) ($rule['path'] ?? []))[0] ?? '')));
		if ($path !== '' && str_contains($path, "/$site/")) {
			// the folder of the site: in /projects/ or a folder next to it
			$pattern = str_starts_with($path, "/projects/$site/") ? '/projects(\.[a-z]+)?/{site}/' : str_replace("/$site/", '/{site}/', $path);
			$addresses[] = $host === '' ? $pattern : $host . $pattern;
		} elseif ($host !== '' && $path === '') {
			$addresses[] = str_starts_with($host, "$label.") ? '{name}' . substr($host, strlen($label)) : $host;
		}
	}
	$addresses = array_values(array_unique($addresses));
	// a literal host (rc.systopic.net) is the default site's: first
	usort($addresses, static fn(string $a, string $b): int => (int) str_contains($a, '{') <=> (int) str_contains($b, '{'));
	if ($addresses !== [] && !isset($packages['cms']['siteAddress'])) {
		$packages['cms']['siteAddress'] = count($addresses) === 1 ? $addresses[0] : $addresses;
	}
	foreach ($old as $key => $value) {
		if ($key === 'constants' && is_array($value)) {
			$local['constants'] = Config::merge($value, (array) ($local['constants'] ?? []));
		}
	}
	$local['packages'] = $packages;
	$backup('config.local.json');
	if (!Setup::writeJson("$root/config/config.local.json", $local)) {
		fwrite(STDERR, "config.local.json: cannot write\n");
		exit(1);
	}
	$say("config.local.json: is $name now" . ($addresses === [] ? '' : ', sites at ' . implode(', ', $addresses)));
	$say("$name.config.json is no longer read. Delete the old instance files once every machine has adopted its own.");
	exit(0);
}

if ($option === '--prune-defaults') {
	$data = $read('config.local.json');
	if (!is_array($data) || $data === []) {
		fwrite(STDERR, "config.local.json cannot be read\n");
		exit(1);
	}
	$found = Explain::redundantIn($layers(), 'local', $stage());
	if ($found === []) {
		$say('config.local.json: nothing repeats');
		exit(0);
	}
	$data = Layout::lift($data);
	foreach ($found as $value) {
		Tree::drop($data, ['packages', $value['package'], ...explode('.', $value['path'])]);
	}
	$backup('config.local.json');
	if (!Setup::writeJson("$root/config/config.local.json", $data)) {
		fwrite(STDERR, "config.local.json: cannot write\n");
		exit(1);
	}
	$say('config.local.json: took out ' . implode(', ', array_map(static fn(array $v): string => $v['package'] . '.' . $v['path'], $found)));
	exit(0);
}

if ($option === '--import-settings') {
	// the constants the definitions name: of the packages here and of those beside the system (cms on a project without it)
	$map = [];
	$schemas = [...(glob("$root/vendor/systopic/*/config/config.schema.json") ?: []), ...(glob(dirname(__DIR__, 2) . '/*/config/config.schema.json') ?: [])];
	foreach ($schemas as $file) {
		$package = basename(dirname($file, 2));
		$definition = json_decode((string) file_get_contents($file), true);
		foreach (is_array($definition) ? Config::constantsOf($definition, []) : [] as [$name, $path]) {
			$map[$name] ??= [$package, $path];
		}
	}
	$project = $read('config.json');
	if (!is_array($project) || !is_file("$root/config/config.json")) {
		fwrite(STDERR, "config.json cannot be read (comments?): import by hand\n");
		exit(1);
	}
	$found = [];
	$jsonFile = is_file("$root/config/global.settings.json") ? 'global.settings.json' : (is_file("$root/config/page.settings.json") ? 'page.settings.json' : '');
	$settings = $jsonFile !== '' ? $read($jsonFile) : null;
	$php = "$root/settings.php";
	$phpLines = [];
	if (is_array($settings)) {
		foreach ($settings as $section => $values) {
			foreach (is_array($values) ? $values : [] as $name => $value) {
				if (isset($map[(string) $name])) {
					$found[(string) $name] = [$value, [(string) $section, (string) $name]];
				}
			}
		}
	} elseif (is_file($php)) {
		// define('NAME', <literal>); on one line or over several: strings,
		// numbers, true, false, null and arrays of them. The tokens are
		// checked first, only such a literal is evaluated.
		$allowed = [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_ARRAY, T_DOUBLE_ARROW, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
		$tokens = token_get_all((string) file_get_contents($php));
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'define') {
				continue;
			}
			$start = $token[2];
			$j = $i + 1;
			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
			if (($tokens[$j] ?? null) !== '(') continue;
			$j++;
			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
			if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING) continue;
			$name = trim($tokens[$j][1], '\'"');
			if (!isset($map[$name])) continue;
			$j++;
			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
			if (($tokens[$j] ?? null) !== ',') continue;
			$expression = '';
			$plain = true;
			$depth = 0;
			for ($j++; $j < $count; $j++) {
				$part = $tokens[$j];
				if ($part === '(' || $part === '[') $depth++;
				if ($part === ')' && $depth === 0) break;
				if ($part === ')' || $part === ']') $depth--;
				if (is_array($part)) {
					$word = strtolower($part[1]);
					$plain = $plain && (in_array($part[0], $allowed, true) || ($part[0] === T_STRING && in_array($word, ['true', 'false', 'null'], true)));
					$expression .= $part[1];
				} else {
					$plain = $plain && in_array($part, ['[', ']', '(', ')', ',', '-'], true);
					$expression .= $part;
				}
			}
			$k = $j + 1;
			while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE && !str_contains($tokens[$k][1], "\n")) $k++;
			if (($tokens[$k] ?? null) !== ';') continue;
			// the line of the ; : the next token with a line number, minus the new lines before it
			$end = $start + substr_count(implode('', array_map(static fn($t) => is_array($t) ? $t[1] : $t, array_slice($tokens, $i, $k - $i + 1))), "\n");
			if (!$plain) {
				$say("settings.php: $name is no plain value, move it by hand");
				continue;
			}
			$found[$name] = [eval('return ' . $expression . ';'), null];
			array_push($phpLines, ...range($start - 1, $end - 1));
			$i = $k;
		}
	}
	if ($found === []) {
		$say('nothing to import');
		exit(0);
	}
	$project = Layout::lift($project);
	$moved = [];
	foreach ($found as $name => [$value, $at]) {
		[$package, $path] = $map[$name];
		$keys = ['packages', $package, ...$path];
		if (Tree::get($project, $keys) !== null) {
			$say("$name: config.json has packages.$package." . implode('.', $path) . ' already, it stays');
		} else {
			Tree::put($project, $keys, $value);
		}
		$moved[] = $name;
	}
	$backup('config.json');
	if (!Setup::writeJson("$root/config/config.json", $project)) {
		fwrite(STDERR, "config.json: cannot write\n");
		exit(1);
	}
	if (is_array($settings)) {
		foreach ($found as [, $at]) {
			unset($settings[$at[0]][$at[1]]);
			if ($settings[$at[0]] === []) {
				unset($settings[$at[0]]);
			}
		}
		$backup($jsonFile);
		$left = array_diff_key($settings, ['//' => true]);
		if ($left === []) {
			unlink("$root/config/$jsonFile");
			$say("$jsonFile: deleted, everything moved");
		} else {
			Setup::writeJson("$root/config/$jsonFile", $settings);
			$say("$jsonFile: left in it: " . implode(', ', array_keys($left)));
		}
	} else {
		$dir = "$root/var/config-backup/$stamp";
		@mkdir($dir, 0775, true);
		copy($php, "$dir/settings.php");
		$text = (string) file_get_contents($php);
		$lines = preg_split('~\R~', $text) ?: [];
		foreach ($phpLines as $index) {
			unset($lines[$index]);
		}
		file_put_contents($php, implode(str_contains($text, "\r\n") ? "\r\n" : "\n", $lines));
		$say('settings.php: ' . count($found) . ' define()s taken out (' . count($phpLines) . ' lines)');
	}
	$say('config.json: ' . implode(', ', $moved));
	exit(0);
}

fwrite(STDERR, "usage: config.php --check | --adopt[=<instance>] | --prune-defaults | --import-settings\n");
exit(2);
