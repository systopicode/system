<?php

declare(strict_types=1);

/**
 * The directory links of the sites: which are missing, and making them - a
 * junction on windows, a symlink everywhere else. Stands alone, so it also
 * runs where only PHP is there:
 *
 *   php tests/site-links.php
 */

require_once __DIR__ . '/../src/Config/Instance.php';
require_once __DIR__ . '/../src/Config/Site.php';
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

$root = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/site-links-' . bin2hex(random_bytes(4));
$project = "$root/projects/main";
mkdir("$project/public", 0775, true);
file_put_contents("$project/public/marker.txt", 'here');
mkdir("$root/projects/real");

$matches = [
	['path' => '/projects/main/', 'site' => 'main'],
	['path' => '/projects/other/', 'site' => 'other'],
	['path' => '/projects/real/', 'site' => 'real'],
	['path' => '/nofolder/deep/', 'site' => 'deep'],
	['path' => '/projects/../evil/', 'site' => 'evil'],
	['serverName' => 'example.com', 'site' => 'hostonly'],
];
$names = ['main', 'other', 'real', 'deep', 'evil', 'hostonly'];
$sites = new Sites(array_fill_keys($names, []), $matches);
$entries = static fn(string $name): array => $sites->byName($name)->entries;

check('the site has its path', in_array('/projects/other/', $entries('other'), true));
check('a missing link is found', SiteLinks::missing($sites, $entries('other'), $root, $project) === ["$root/projects/other"]);
check('the project itself is no missing link', SiteLinks::missing($sites, $entries('main'), $root, $project) === []);
check('a real folder is left alone', SiteLinks::missing($sites, $entries('real'), $root, $project) === []);
check('no link without its parent folder', SiteLinks::missing($sites, $entries('deep'), $root, $project) === []);
check('nothing leaves the document root', SiteLinks::missing($sites, $entries('evil'), $root, $project) === []);
check('a site with hosts only needs none', SiteLinks::missing($sites, $entries('hostonly'), $root, $project) === []);

$elsewhere = new Sites(['other' => []], [['path' => '/projects/other/', 'site' => 'other']]);
check('no link where the scheme does not hold', SiteLinks::missing($elsewhere, ['/projects/other/'], $root, $project) === []);

// STAGING: the rules name the host as well, the entries are 'host/path'
$withHost = new Sites(['main' => [], 'other' => [], 'hostonly' => []], [
	['serverName' => 'staging.example.com', 'path' => '/projects/main/', 'site' => 'main'],
	['serverName' => 'staging.example.com', 'path' => '/projects/other/', 'site' => 'other'],
	['serverName' => 'example.com', 'site' => 'hostonly'],
]);
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

$ensured = new Sites(['main' => [], 'third' => []], [['path' => '/projects/main/', 'site' => 'main'], ['path' => '/projects/third/', 'site' => 'third']]);
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
