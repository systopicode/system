<?php

declare(strict_types=1);

/**
 * The setup over the token, in a real request: a project made for this test,
 * www/tmp-setup/, with the vendor/ and the database of DEV. Every state a
 * project can be in, and that no secret reaches a page. Removed afterwards.
 * Values are compared, never printed.
 *
 *   php tests/setup-maintenance.php
 */

const WWW = 'C:/laragon/www/';
const ROOT = WWW . 'tmp-setup/';
const PROJECT = WWW . 'projects/systopic.net/';
const PAGE = 'https://192.168.22.50/tmp-setup/public/';

function check(string $label, bool $ok): void
{
	if (!$ok) {
		throw new RuntimeException('FAIL: ' . $label);
	}
	echo "ok: $label\n";
}

/** @return array{0: int, 1: string} status, body */
function http(string $url, ?array $post = null): array
{
	$ch = curl_init($url);
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30]);
	if ($post !== null) {
		curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
	}
	$body = (string) curl_exec($ch);
	return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body];
}

function local(): array
{
	return json_decode((string) file_get_contents(ROOT . 'config/config.local.json'), true) ?: [];
}

function writeLocal(array $data): void
{
	file_put_contents(ROOT . 'config/config.local.json', json_encode($data, JSON_PRETTY_PRINT));
}

function token(): string
{
	$token = bin2hex(random_bytes(16));
	@mkdir(ROOT . 'var', 0775, true);
	file_put_contents(ROOT . 'var/setup.token', $token);
	return $token;
}

function isForm(string $html): bool
{
	return str_contains($html, 'Setup of tmp-setup');
}

function clean(): void
{
	if (is_dir(ROOT . 'vendor')) {
		@rmdir(ROOT . 'vendor'); // the junction, not what it points at
	}
	if (is_dir(ROOT)) {
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir(ROOT);
	}
}

if (!is_dir(PROJECT . 'vendor') || http('https://192.168.22.50/')[0] === 0) {
	echo "skipped: no DEV project to borrow from\n";
	exit(0);
}
clean();
register_shutdown_function('clean');

// the access of the project on this machine: its config.local.json
$access = static function (array $file): array {
	return array_merge((array) ($file['db'] ?? []), (array) ($file['packages']['db']['connections']['main'] ?? []));
};
$dev = $access(json_decode((string) @file_get_contents(PROJECT . 'config/config.local.json'), true) ?: []);
try {
	new PDO('mysql:host=' . ($dev['host'] ?? 'localhost') . ';dbname=' . ($dev['name'] ?? ''), (string) ($dev['user'] ?? 'root'), (string) ($dev['pass'] ?? ''));
} catch (PDOException) {
	throw new RuntimeException('FAIL: the project is here, but its config.local.json does not connect');
}

mkdir(ROOT . 'public', 0775, true);
mkdir(ROOT . 'config', 0775, true);
copy(PROJECT . 'public/index.php', ROOT . 'public/index.php');
copy(PROJECT . 'public/.htaccess', ROOT . 'public/.htaccess');
copy(PROJECT . '.htaccess', ROOT . '.htaccess');
exec('powershell.exe -NoProfile -Command "New-Item -ItemType Junction -Path \'' . ROOT . 'vendor\' -Target \'' . PROJECT . 'vendor\' | Out-Null"');
check('the test project has a vendor', is_file(ROOT . 'vendor/autoload.php'));
file_put_contents(ROOT . 'config/config.json', json_encode(['constants' => ['PROJECT_NAMESPACE' => 'TmpSetup', 'DEFAULT_TABLE_PREFIX' => 'cms', 'ACCOUNT_WHITELIST' => []]]));
$project = ROOT . 'config/config.json';
$goodProject = (string) file_get_contents($project);
$canary = 'canary-' . bin2hex(random_bytes(6));
$installation = ['system' => ['instance' => 'DEV.test'], 'db' => ['connections' => ['main' => ['host' => $dev['host'] ?? 'localhost', 'name' => $dev['name'], 'user' => $dev['user'] ?? 'root']]]];
$good = ['//' => 'a note', 'db' => ['pass' => (string) ($dev['pass'] ?? '')], 'auth' => ['secret' => str_repeat('k', 40) . $canary], 'custom' => ['secret' => $canary, 'open' => 'visible-value'], 'packages' => $installation];
writeLocal($good);

// ---- who gets in
$plain = http(PAGE);
check('without a token the site answers', !isForm($plain[1]));
token();
check('a wrong token opens nothing', !isForm(http(PAGE . '?setup=wrong')[1]));
touch(ROOT . 'var/setup.token', time() - 3700);
$old = trim((string) file_get_contents(ROOT . 'var/setup.token'));
check('an old token opens nothing', !isForm(http(PAGE . "?setup=$old")[1]));

$token = token();
[$status, $form] = http(PAGE . "?setup=$token");
check('the token opens the form on a healthy site', $status === 200 && isForm($form));
check('admin/ goes on to the system', !isForm(http(PAGE . "admin/?setup=$token")[1]));
check('the form has the fields of the definitions', str_contains($form, 'f[db.connections.main.host]') && str_contains($form, 'f[db.connections.main.pass]') && str_contains($form, 'f[system.auth.secret]') && str_contains($form, 'f[system.instance]'));
check('no relay without a relay', !str_contains($form, 'f[relay.'));
check('no secret reaches the page', !str_contains($form, $canary));
// the password of DEV may be any word: one that can be found. The form opens
// no connection, so it need not work here
$passCanary = 'pass-' . $canary;
writeLocal(['db' => ['pass' => $passCanary]] + $good);
check('no password reaches the page', !str_contains(http(PAGE . "?setup=$token")[1], $passCanary));
writeLocal($good);
check('the rest of the file is there', str_contains($form, 'visible-value') && str_contains($form, 'a note'));

// ---- saving
$before = (string) file_get_contents(ROOT . 'config/config.local.json');
[, $wrong] = http(PAGE, ['setup' => $token, 'f' => ['db.connections.main.pass' => 'not-the-password-' . $canary], 'rest' => '{}']);
check('a password that does not work is refused', str_contains($wrong, 'not saved, the database does not answer'));
check('and it changes nothing', (string) file_get_contents(ROOT . 'config/config.local.json') === $before && is_file(ROOT . 'var/setup.token'));
check('and it does not come back in the page', !str_contains($wrong, 'not-the-password-'));
[, $badJson] = http(PAGE, ['setup' => $token, 'rest' => '{nope']);
check('broken JSON is refused', str_contains($badJson, 'no JSON object') && (string) file_get_contents(ROOT . 'config/config.local.json') === $before);
[, $badPort] = http(PAGE, ['setup' => $token, 'f' => ['db.connections.main.port' => 'abc'], 'rest' => '']);
check('a port is a number', str_contains($badPort, 'not a number'));

[, $saved] = http(PAGE, ['setup' => $token, 'rest' => json_encode(['//' => 'a new note', 'custom' => ['open' => 'changed']])]);
$file = local();
check('saving works', str_contains($saved, 'config/config.local.json is written'));
check('the token is spent', !is_file(ROOT . 'var/setup.token'));
check('an empty field keeps the password', ($file['packages']['db']['connections']['main']['pass'] ?? null) === ($dev['pass'] ?? ''));
check('the file has moved to the new layout', !isset($file['db']) && !isset($file['auth']));
check('secrets no definition knows are kept', ($file['custom']['secret'] ?? '') === $canary && ($file['packages']['system']['auth']['secret'] ?? '') === str_repeat('k', 40) . $canary);
check('the block of JSON is written', ($file['custom']['open'] ?? '') === 'changed' && ($file['//'] ?? '') === 'a new note');
check('the site reads the new layout', !str_contains(http(PAGE)[1], 'unable to connect'));

// ---- states that used to lock everybody out
$file['packages']['db']['connections']['main']['pass'] = 'broken-' . $canary;
writeLocal($file);
$token = token();
[$status, $form] = http(PAGE . "?setup=$token");
check('a wrong password: the form is there', $status === 200 && isForm($form));
[, $saved] = http(PAGE, ['setup' => $token, 'f' => ['db.connections.main.pass' => (string) ($dev['pass'] ?? '')], 'rest' => '']);
check('and the right one repairs it', str_contains($saved, 'is written') && (local()['packages']['db']['connections']['main']['pass'] ?? null) === ($dev['pass'] ?? ''));

file_put_contents(ROOT . 'config/config.local.json', '{ broken');
$token = token();
[, $broken] = http(PAGE . "?setup=$token");
check('a broken config.local.json says so', str_contains($broken, 'The config cannot be read') && str_contains($broken, 'set config.local.json aside'));
[, $aside] = http(PAGE, ['setup' => $token]);
check('it can be set aside', str_contains($aside, 'Set aside') && !is_file(ROOT . 'config/config.local.json') && count(glob(ROOT . 'var/scaffold/config.local.json.*') ?: []) === 1);
check('and the setup asks for the name of the installation again', str_contains(http(PAGE . "?setup=$token")[1], 'name="stage"'));

writeLocal(['db' => ['pass' => (string) ($dev['pass'] ?? '')], 'packages' => $installation]);
file_put_contents($project, '{ broken');
[, $broken] = http(PAGE . "?setup=$token");
check('a broken config.json says so', str_contains($broken, 'The config cannot be read') && str_contains($broken, 'with sync') && !str_contains($broken, '<button'));
file_put_contents($project, $goodProject);

unlink($project);
[, $fresh] = http(PAGE . "?setup=$token");
check('no config: the first setup asks', !isForm($fresh) && str_contains($fresh, 'name="') && !str_contains($fresh, 'cannot be read'));

echo "all fine\n";
