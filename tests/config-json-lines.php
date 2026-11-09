<?php

declare(strict_types=1);

/**
 * JsonLines: the line of a key in a JSON text, for the syscoder links of
 * the environment settings.
 *
 *   php tests/config-json-lines.php
 */

require __DIR__ . '/bootstrap.php';

use Systopic\System\Config\JsonLines;

$json = <<<'JSON'
{
	"matches": [
		{ "serverIp": "192.168.22.50", "path": "/projects/x/" },
		{
			"serverName": ["a.test", "b.test"]
		}
	],
	"packages": {
		"system": {
			"ini": { "session.cookie_lifetime": 0, "memory_limit": "1G" },
			"debug": {
				"debug": true
			}
		},
		"db": { "connections": { "main": { "name": "x\"y", "host": "{[,:]}" } } }
	},
	"default": false
}
JSON;

check('a key at the top', JsonLines::line($json, ['matches']) === 2);
check('a list item', JsonLines::line($json, ['matches', 0]) === 3 && JsonLines::line($json, ['matches', '1']) === 4);
check('a key inside a list item', JsonLines::line($json, ['matches', 1, 'serverName']) === 5);
check('an item of a list of strings', JsonLines::line($json, ['matches', 1, 'serverName', 1]) === 5);
check('a nested key', JsonLines::line($json, ['packages', 'system', 'debug', 'debug']) === 12);
check('a key with a dot is one key', JsonLines::line($json, ['packages', 'system', 'ini', 'session.cookie_lifetime']) === 10);
check('brackets and quotes inside strings are text', JsonLines::line($json, ['packages', 'db', 'connections', 'main', 'host']) === 15 && JsonLines::line($json, ['default']) === 17);
check('a missing key', JsonLines::line($json, ['packages', 'relay']) === null);
check('the first candidate that is there', JsonLines::first($json, [['packages', 'relay'], ['packages', 'db']]) === 15);
check('broken JSON gives what came before the break', JsonLines::line("{\n\"a\": {\n\"b\": [", ['a', 'b']) === 3);

// the definitions themselves: every property has a line
foreach (glob(dirname(__DIR__, 2) . '/{system,db,relay}/config/*.schema.json', GLOB_BRACE) as $file) {
    $text = (string) file_get_contents($file);
    $walk = static function (array $node, array $keys) use (&$walk, $text, $file): void {
        foreach ($node['properties'] ?? [] as $name => $child) {
            $at = [...$keys, 'properties', (string) $name];
            if (JsonLines::line($text, $at) === null) {
                check(basename($file) . ': ' . implode('.', $at) . ' has a line', false);
            }
            $walk($child, $at);
        }
        if (is_array($node['additionalProperties'] ?? null)) {
            $walk($node['additionalProperties'], [...$keys, 'additionalProperties']);
        }
        if (is_array($node['items'] ?? null)) {
            $walk($node['items'], [...$keys, 'items']);
        }
    };
    $walk(json_decode($text, true), []);
}
check('every property of the definitions has a line', true);

echo "all fine\n";
