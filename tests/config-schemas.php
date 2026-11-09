<?php

declare(strict_types=1);

/**
 * The config definitions (<package>/config/config.schema.json, one per
 * package), see .cursor/docs/config-struktur.md.
 *
 * Read only: config.json and config.local.json of projects/* are checked
 * against them, in either layout. Values are never printed.
 *
 *   php tests/config-schemas.php
 */

require_once __DIR__ . '/../src/Config/Config.php';
require_once __DIR__ . '/../src/Config/Layout.php';

const PACKAGES = ['system', 'db', 'relay'];
const KEYWORDS = [
	'$schema', 'title', 'description', 'type', 'properties', 'required', 'items', 'enum', 'default', 'minimum', 'maximum',
	'minLength', 'pattern', 'format', 'additionalProperties', 'propertyNames', 'not', 'anyOf', 'writeOnly',
	'x-mandatory', 'x-stages', 'x-computed', 'x-optional', 'x-overridable', 'x-options', 'x-backup', 'x-tool', 'x-entry-name', 'x-project',
];

function check(string $label, bool $ok, string $detail = ''): void
{
	if (!$ok) {
		throw new RuntimeException($label . ($detail === '' ? '' : ": $detail"));
	}
	echo "ok: $label\n";
}

/** every keyword of a definition, also the nested ones */
function keywords(array $node, array &$found): void
{
	foreach ($node as $key => $value) {
		if (is_string($key)) {
			$found[$key] = true;
		}
		if ($key === 'properties' && is_array($value)) {
			foreach ($value as $child) {
				keywords($child, $found);
			}
		} elseif (in_array($key, ['items', 'propertyNames', 'not', 'additionalProperties'], true) && is_array($value)) {
			keywords($value, $found);
		}
	}
}

/**
 * @return array<string, array> dotted path => definition, the leaves. An array
 * of objects counts as one leaf, it is one field of the form. A map with
 * names of its own (connections) has '*' in the path.
 */
function leaves(array $node, string $path = '', bool $project = false): array
{
	$out = [];
	$project = $project || !empty($node['x-project']);
	foreach ($node['properties'] ?? [] as $name => $child) {
		$full = $path === '' ? (string) $name : "$path.$name";
		if (is_array($child['additionalProperties'] ?? null)) {
			$out += leaves($child['additionalProperties'], "$full.*", $project || !empty($child['x-project']));
		} else {
			$out += isset($child['properties']) ? leaves($child, $full, $project) : [$full => $child + ['_project' => $project || !empty($child['x-project'])]];
		}
	}
	return $out;
}

function typeOk(mixed $value, array $definition): bool
{
	$names = ['boolean' => 'is_bool', 'integer' => 'is_int', 'string' => 'is_string', 'array' => 'is_array', 'object' => 'is_array', 'null' => 'is_null'];
	foreach ((array) ($definition['type'] ?? []) as $type) {
		if ($names[$type]($value)) {
			return true;
		}
	}
	return false;
}

$packages = dirname(__DIR__, 2);

// ---- the definitions themselves
$definitions = [];
$flat = [];
foreach (PACKAGES as $package) {
	$path = "$packages/$package/config/config.schema.json";
	check("$package has one definition", is_file($path) && glob("$packages/$package/config/*.schema.json") === [$path]);
	$json = json_decode((string) file_get_contents($path), true);
	check("$package is JSON Schema 2020-12", is_array($json)
		&& ($json['$schema'] ?? '') === 'https://json-schema.org/draft/2020-12/schema' && ($json['type'] ?? '') === 'object');
	$used = [];
	keywords($json, $used);
	$unknown = array_diff(array_keys($used), KEYWORDS);
	check("$package uses known keywords only", $unknown === [], implode(', ', $unknown));
	check("$package has a title and a description", ($json['title'] ?? '') !== '' && ($json['description'] ?? '') !== '');
	$definitions[$package] = $json;
	$flat[$package] = leaves($json);
}

$wrong = [];
foreach ($flat as $package => $keys) {
	foreach ($keys as $key => $leaf) {
		if (!empty($leaf['writeOnly']) && $leaf['_project']) {
			$wrong[] = "$package.$key is a secret of the project";
		}
		if (isset($leaf['default']) && !typeOk($leaf['default'], $leaf)) {
			$wrong[] = "$package.$key default";
		}
		if (!empty($leaf['x-overridable']) && !$leaf['_project']) {
			$wrong[] = "$package.$key: only a value of the project can be overridden";
		}
	}
}
check('secrets belong to the installation, defaults fit, overrides only of the project', $wrong === [], implode(', ', $wrong));
check('the installation is named', ($definitions['system']['properties']['instance']['x-mandatory'] ?? '') === 'input' && in_array('instance', $definitions['system']['required'] ?? [], true));
check('reserved in ini', $definitions['system']['properties']['ini']['propertyNames']['not']['enum'] === ['error_log', 'display_errors', 'error_reporting']);
check('ini and constants are the project\'s', !empty($definitions['system']['properties']['ini']['x-project']) && !empty($definitions['system']['properties']['constants']['x-project']));
check('every project has the connection main', $definitions['db']['properties']['connections']['required'] === ['main']);
check('mysql is the default engine', $flat['db']['connections.*.driver']['default'] === 'mysql');

// ---- what is orphaned is said by the definitions: everything the code reads has to be in them
$reads = 0;
$strays = [];
foreach (['system/src', 'system/lib', 'cms/src', 'relay/src'] as $folder) {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$packages/$folder", FilesystemIterator::SKIP_DOTS));
	foreach ($files as $source) {
		if ($source->getExtension() !== 'php') {
			continue;
		}
		preg_match_all("~package\('(\w+)'\)((?:\['\w+'\])+)~", (string) file_get_contents($source->getPathname()), $found, PREG_SET_ORDER);
		foreach ($found as [, $package, $keys]) {
			preg_match_all("~'(\w+)'~", $keys, $names);
			$path = implode('.', $names[1]);
			$reads++;
			$known = array_filter(array_keys($flat[$package] ?? []), static fn(string $key): bool => $key === $path || str_starts_with($key, "$path."));
			if ($known === []) {
				$strays[] = "$package.$path";
			}
		}
	}
}
check("$reads reads in the code are defined", $reads > 5 && $strays === []);

// ---- the real files, where their keys lie today
$projects = glob(dirname($packages) . '/projects/*/config/*.json') ?: [];
$files = array_values(array_filter($projects, static fn(string $f): bool => in_array(basename($f), ['config.json', 'config.local.json'], true)));
if ($files === []) {
	echo "skipped: no projects next to the packages\n";
	exit(0);
}

/** the leaves of a config file - lists are values, '//' is a comment */
function configKeys(array $data, string $path = ''): array
{
	$out = [];
	foreach ($data as $key => $value) {
		if ($key === '//') {
			continue;
		}
		$full = $path === '' ? (string) $key : "$path.$key";
		$out += is_array($value) && !array_is_list($value) && $value !== [] ? configKeys($value, $full) : [$full => $value];
	}
	return $out;
}

$unknown = [];
$wrongType = [];
$exposed = [];
foreach ($files as $file) {
	$data = json_decode((string) file_get_contents($file), true);
	$name = basename(dirname($file, 2)) . '/' . basename($file);
	check("readable: $name", is_array($data));
	foreach (\Systopic\System\Config\Layout::packages($data) as $package => $tree) {
		foreach (configKeys($tree) as $short => $value) {
			$key = "$package.$short";
			if (str_starts_with($short, 'ini.')) {
				continue; // an open block
			}
			// a name of a map stands for itself
			$lookup = preg_replace('~^connections\.[^.]+\.~', 'connections.*.', $short);
			$leaf = $flat[$package][$lookup] ?? null;
			if ($leaf === null) {
				$unknown[$key] = $name;
				continue;
			}
			if (!typeOk($value, $leaf)) {
				$wrongType[$key] = $name;
			}
			if (!empty($leaf['writeOnly']) && (string) $value !== '' && basename($file) !== 'config.local.json') {
				$exposed[$key] = ($exposed[$key] ?? 0) + 1;
			}
		}
	}
}
check(count($files) . ' config files: every key is defined', $unknown === [], implode(', ', array_keys($unknown)));
check('every value has the defined type', $wrongType === [], implode(', ', array_keys($wrongType)));
foreach ($exposed as $key => $count) {
	echo "note: $key lies in config.json in $count files\n";
}
echo "all fine\n";
