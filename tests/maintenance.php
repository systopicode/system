<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Maintenance\Runner;
use Systopic\System\Maintenance\Webcron;
class fs { public static string $projectRoot; public static function toNative(string $path): string { return $path; } }
$base = sys_get_temp_dir() . '/fluxo-maintenance-' . bin2hex(random_bytes(6));
mkdir($base, 0700, true); fs::$projectRoot = $base;
$count = 0;
Runner::register('sample', 'Sample', 'Test only', static function (bool $preview, int $limit) use (&$count): array { if (!$preview) $count++; return ['count' => 2, 'more' => false]; });
try {
    $preview = Runner::run('manual', 'sample', true);
    check('preview does not mutate task data or status', $preview['results']['sample']['count'] === 2 && $count === 0 && Runner::status() === []);
    $run = Runner::run('request');
    check('request cleanup executes a bounded task', $count === 1 && $run['results']['sample']['ok']);
    Runner::run('request');
    check('recently completed task is not repeated on each request', $count === 1);
    Runner::run('manual', 'sample');
    $status = Runner::status();
    check('manual does not overwrite automatic execution evidence', isset($status['runs']['request'], $status['runs']['manual']) && !isset($status['runs']['cli']));
    $handle = fopen(Runner::directory() . '/runner.lock', 'c'); flock($handle, LOCK_EX | LOCK_NB);
    check('parallel execution is refused without mutation', Runner::run()['busy'] && $count === 2);
    flock($handle, LOCK_UN); fclose($handle);
    $key = Webcron::rotate();
    check('webcron key is accepted but wrong keys are refused', Webcron::accepts($key) && !Webcron::accepts(str_repeat('a', 64)));
    $next = Webcron::rotate();
    check('key rotation revokes previous access', !Webcron::accepts($key) && Webcron::accepts($next));
    check('webcron stores only a digest', !str_contains((string) file_get_contents(Runner::directory() . '/webcron-' . Runner::instanceKey() . '.key'), $next));
    Webcron::disable(); check('webcron can be disabled', !Webcron::accepts($next));
    rejects('unknown task cannot run arbitrary callbacks', static fn() => Runner::run('manual', 'unknown'));
    fs::$projectRoot = $base . '/other'; mkdir(fs::$projectRoot);
    check('another project has independent status', Runner::status() === []);
    fs::$projectRoot = $base;
    Runner::register('failure', 'Failure', 'Test', static fn() => throw new RuntimeException('private credentials'));
    $failed = Runner::run('manual', 'failure');
    check('task failures are recorded without leaking exception text', !$failed['results']['failure']['ok'] && !str_contains(json_encode(Runner::status()), 'private credentials'));
    Runner::register('following', 'Following task', 'Test', static fn() => ['count' => 0]);
    $stateFile = glob(Runner::directory() . '/state-*.json')[0];
    $state = Runner::status(); $state['jobs']['failure']['attempt'] = 0;
    file_put_contents($stateFile, json_encode($state));
    $first = Runner::run('request');
    check('deferred tasks are explicitly returned', in_array('following', $first['remaining'], true));
    $next = Runner::run('request');
    check('failing task does not starve subsequent jobs', isset($next['results']['following']) && $next['results']['following']['ok']);
    Runner::register('bad-contract', 'Invalid result', 'Test', static fn() => ['count' => -1]);
    check('invalid task results are failures', !Runner::run('manual', 'bad-contract')['results']['bad-contract']['ok']);
    $saved = file_get_contents($stateFile); file_put_contents($stateFile, '{"jobs":"invalid"}');
    rejects('malformed nested status is refused', static fn() => Runner::status());
    file_put_contents($stateFile, $saved);
    echo "All maintenance checks passed.\n";
} finally {
    $remove = static function (string $dir) use (&$remove): void { foreach (scandir($dir) as $name) { if ($name === '.' || $name === '..') continue; $path = $dir . '/' . $name; is_dir($path) ? $remove($path) : unlink($path); } rmdir($dir); };
    $remove($base);
}
