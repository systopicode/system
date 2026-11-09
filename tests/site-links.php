<?php

declare(strict_types=1);

/**
 * Where the sites answer (Sites, from the patterns of `siteAddress`), and
 * their directory links: which are missing, and making them - a junction on
 * windows, a symlink everywhere else. Stands alone, so it also runs where
 * only PHP is there:
 *
 *   php tests/site-links.php
 */

require_once __DIR__ . '/../src/Config/Site.php';
require_once __DIR__ . '/../src/Config/SiteHit.php';
require_once __DIR__ . '/../src/Config/Sites.php';
require_once __DIR__ . '/../src/Config/SiteLinks.php';

use Systopic\System\Config\SiteLinks;
use Systopic\System\Config\Sites;

function check(string $label, bool $ok): void
{
	if (!$ok) {
		throw new RuntimeException('FAIL: ' . $label);
	}
	echo "ok: $label\n";
}

// ---- the addresses of the sites
$names = ['systopic.net' => ['default' => true], 'beautiful-technology.de' => [], 'expo-systems.de' => ['aliases' => ['expo.example']]];
$dev = '/projects(\.[a-z]+)?/{site}/';

$sites = new Sites($names, [$dev], '192.168.22.50', '/projects/expo-systems.de/public/');
$hit = $sites->resolve('192.168.22.50', '/projects/expo-systems.de/public/');
check('DEV: the folder names the site', $hit->site->name === 'expo-systems.de' && $hit->entry === '/projects/expo-systems.de/' && $hit->remainder === 'public/');
check('DEV: every site gets the folder next to it', $sites->byName('beautiful-technology.de')->entries === ['/projects/beautiful-technology.de/']);
check('the aliases come after', $sites->byName('expo-systems.de')->entries === ['/projects/expo-systems.de/', 'expo.example']);

$archived = new Sites($names, [$dev], 'dev.local', '/projects.archive/beautiful-technology.de/public/');
$hit = $archived->resolve('dev.local', '/projects.archive/beautiful-technology.de/public/');
check('a project moved to projects.archive keeps its sites', $hit->site->name === 'beautiful-technology.de' && $archived->byName('systopic.net')->firstEntry() === '/projects.archive/systopic.net/');

$staging = new Sites($names, ['staging.systopic.net/projects/{site}/'], 'staging.systopic.net', '/projects/systopic.net/public/');
check('STAGING: host and folder', $staging->byName('expo-systems.de')->entries[0] === 'staging.systopic.net/projects/expo-systems.de/'
    && $staging->resolve('staging.systopic.net', '/projects/systopic.net/public/')->site->name === 'systopic.net');

$rc = ['rc.systopic.net', '{name}.systopic.net'];
$sites = new Sites($names, $rc, 'beautiful-technology.systopic.net', '/');
check('RC: the name without its TLD is the subdomain', $sites->resolve('beautiful-technology.systopic.net', '/')->site->name === 'beautiful-technology.de');
check('RC: the default site has a host of its own, first', $sites->byName('systopic.net')->firstEntry() === 'rc.systopic.net');
check('RC: others only by their name', $sites->byName('expo-systems.de')->entries[0] === 'expo-systems.systopic.net');
$sites = new Sites($names, $rc, 'rc.systopic.net', '/');
check('RC: the default site answers on its host', $sites->resolve('rc.systopic.net', '/')->site->name === 'systopic.net');
$sites = new Sites($names, $rc);
check('without a request a plain pattern is written out', $sites->byName('expo-systems.de')->entries[0] === 'expo-systems.systopic.net');
check('a pattern with more than text is not, without a request', (new Sites($names, [$dev]))->byName('expo-systems.de')->entries === ['expo.example']);

$live = new Sites($names);
check('LIVE: every site on its name', $live->resolve('beautiful-technology.de', '/')->site->name === 'beautiful-technology.de');
check('LIVE: and on its aliases', $live->resolve('expo.example', '/')->site->name === 'expo-systems.de');
check('an unknown host lands on the default site', $live->resolve('elsewhere.test', '/')->site->name === 'systopic.net');
check('a folder that is no site, too', (new Sites($names, [$dev], 'x', '/projects/unknown.de/'))->resolve('x', '/projects/unknown.de/')->site->name === 'systopic.net');
check('no sites: one that is the whole tree', (new Sites([], [$dev]))->default()->entries === ['*']);

// ---- the links
$root = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/site-links-' . bin2hex(random_bytes(4));
$project = "$root/projects/main";
mkdir("$project/public", 0775, true);
file_put_contents("$project/public/marker.txt", 'here');
mkdir("$root/projects/real");

$names = ['main', 'other', 'real'];
$sites = new Sites(array_fill_keys($names, []), ['/projects/{site}/'], 'h', '/projects/main/public/');
$entries = static fn(string $name): array => $sites->byName($name)->entries;

check('the site has its path', in_array('/projects/other/', $entries('other'), true));
check('a missing link is found', SiteLinks::missing($sites, $entries('other'), $root, $project) === ["$root/projects/other"]);
check('the project itself is no missing link', SiteLinks::missing($sites, $entries('main'), $root, $project) === []);
check('a real folder is left alone', SiteLinks::missing($sites, $entries('real'), $root, $project) === []);
check('no link without its parent folder', SiteLinks::missing($sites, ['/nofolder/deep/'], $root, $project) === []);
check('nothing leaves the document root', SiteLinks::missing($sites, ['/projects/../evil/'], $root, $project) === []);
check('a site with hosts only needs none', SiteLinks::missing($sites, ['example.com'], $root, $project) === []);

$elsewhere = new Sites(['other' => []], ['/projects/{site}/'], 'h', '/projects/other/');
check('no link where the scheme does not hold', SiteLinks::missing($elsewhere, ['/projects/other/'], $root, $project) === []);

// STAGING: the entries are 'host/path'
$withHost = new Sites(['main' => [], 'other' => []], ['staging.example.com/projects/{site}/'], 'staging.example.com', '/projects/main/public/');
check('an entry with a host has the host in it', $withHost->byName('other')->entries === ['staging.example.com/projects/other/']);
check('with a host: the link is found', SiteLinks::missing($withHost, $withHost->byName('other')->entries, $root, $project) === ["$root/projects/other"]);
check('with a host: the project itself is no missing link', SiteLinks::missing($withHost, $withHost->byName('main')->entries, $root, $project) === []);
check('a host alone is no path', SiteLinks::pathsOf(['example.com', 'example.com/'], $root) === []);
check('with a host: nothing leaves the document root', SiteLinks::pathsOf(['example.com/../evil/'], $root) === []);

$result = SiteLinks::create(SiteLinks::missing($sites, $entries('other'), $root, $project), $project);
check('the link is made', $result === ["$root/projects/other" => 'created']);
check('it leads to the project', is_file("$root/projects/other/public/marker.txt"));
check('it is a link, not a copy', SiteLinks::isLinkTo("$root/projects/other", $project));
if (PHP_OS_FAMILY !== 'Windows') {
	check('beside the project it is relative', readlink("$root/projects/other") === 'main');
}
check('then nothing is missing', SiteLinks::missing($sites, $entries('other'), $root, $project) === []);

$ensured = new Sites(['main' => [], 'third' => []], ['/projects/{site}/'], 'h', '/projects/main/public/');
check('ensure makes every missing link', SiteLinks::ensure($ensured, $root, $project) === ["$root/projects/third" => 'created']);
check('and the second time none', SiteLinks::ensure($ensured, $root, $project) === []);

check('moving a link works', SiteLinks::move("$root/projects/third", "$root/projects/fourth", realpath($project)) === 'created' && is_file("$root/projects/fourth/public/marker.txt") && !file_exists("$root/projects/third"));

// links first: what they point at must stay
foreach (['other', 'fourth'] as $link) {
	@rmdir("$root/projects/$link") || @unlink("$root/projects/$link");
}
check('removing a link keeps the project', is_file("$project/public/marker.txt"));
unlink("$project/public/marker.txt");
rmdir("$project/public");
rmdir($project);
rmdir("$root/projects/real");
rmdir("$root/projects");
rmdir($root);
echo "all fine\n";
