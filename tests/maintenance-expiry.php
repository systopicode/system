<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 2) . '/relay/src/RemoteDebug.php';
use Systopic\System\Maintenance\Runner;
use Systopic\System\Maintenance\Tasks;
use Systopic\Relay\RemoteDebug;
class fs { public static string $projectRoot; public static string $root; public static function toNative(string $path): string { return $path; } }
$base = sys_get_temp_dir() . '/fluxo-expiry-' . bin2hex(random_bytes(6)) . '/';
fs::$projectRoot = fs::$root = $base;
mkdir($base . 'var/log/sessions', 0700, true); mkdir($base . 'var/debug', 0700, true);
$old = $base . 'var/log/sessions/' . str_repeat('a', 64);
$fresh = $base . 'var/log/sessions/' . str_repeat('b', 64);
file_put_contents($old, 'old'); touch($old, time() - 91 * 86400);
file_put_contents($fresh, 'fresh');
file_put_contents($base . 'var/log/signin.log', 'Must survive');
$registry = $base . 'var/debug/remote.json';
$data = ['clients' => ['old' => ['seen' => time() - 90000], 'active' => ['seen' => time()]], 'sessions' => ['expired' => ['expires' => time() - 10], 'active' => ['expires' => time() + 120]]];
file_put_contents($registry, json_encode($data));
try {
    Tasks::register();
    Runner::run('manual', 'session-markers', true);
    check('session preview leaves expired files intact', is_file($old));
    Runner::run('manual', 'session-markers');
    check('only old hashed attribution markers are deleted', !is_file($old) && is_file($fresh));
    check('login log is never part of marker cleanup', file_get_contents($base . 'var/log/signin.log') === 'Must survive');
    for ($i = 1; $i <= 550; $i++) file_put_contents($base . 'var/log/sessions/' . str_pad(dechex($i), 64, '0', STR_PAD_LEFT), 'fresh');
    $late = $base . 'var/log/sessions/' . str_repeat('f', 64);
    file_put_contents($late, 'expired'); touch($late, time() - 91 * 86400);
    for ($i = 0; $i < 4; $i++) Runner::run('manual', 'session-markers');
    check('bounded marker scans progress past retained files', !is_file($late) && is_file($fresh));
    $preview = RemoteDebug::cleanup(true);
    check('debug preview counts expiry without rewriting', $preview['count'] === 2 && json_decode(file_get_contents($registry), true) === $data);
    $first = RemoteDebug::cleanup(false, 1);
    check('debug cleanup respects batch size', $first['count'] === 1 && $first['more']);
    RemoteDebug::cleanup(false);
    $left = json_decode(file_get_contents($registry), true);
    check('active debug clients and sessions survive', array_keys($left['clients']) === ['active'] && array_keys($left['sessions']) === ['active']);
    file_put_contents($registry, '{broken');
    rejects('invalid registry is not silently overwritten', static fn() => RemoteDebug::cleanup(false));
    check('broken diagnostic data remains available for repair', file_get_contents($registry) === '{broken');
    echo "All expiry checks passed.\n";
} finally {
    $remove = static function (string $dir) use (&$remove): void { foreach (scandir($dir) as $name) { if ($name === '.' || $name === '..') continue; $p = $dir . '/' . $name; is_dir($p) ? $remove($p) : unlink($p); } rmdir($dir); };
    $remove($base);
}
