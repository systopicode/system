<?php

declare(strict_types=1);
namespace Systopic\System\Maintenance;

/** Project-local, bounded maintenance. No daemon, shell execution or schema changes. */
final class Runner
{
    private static array $jobs = [];

    public static function register(string $id, string $label, string $policy, callable $run, int $interval = 3600): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/D', $id) || isset(self::$jobs[$id]) || $interval < 60) throw new \InvalidArgumentException('Invalid or duplicate maintenance job');
        self::$jobs[$id] = compact('label', 'policy', 'run', 'interval');
    }

    public static function jobs(): array { return self::$jobs; }

    public static function directory(): string
    {
        return rtrim(\fs::toNative((string) \fs::$projectRoot), '/\\') . '/var/maintenance';
    }

    public static function instanceKey(): string
    {
        $instance = (defined('INSTANCE') ? (string) INSTANCE : '') . ':' . (defined('STAGE') ? (string) STAGE : '');
        return substr(hash('sha256', $instance), 0, 16);
    }

    private static function statePath(): string
    {
        return self::directory() . '/state-' . self::instanceKey() . '.json';
    }

    public static function status(): array
    {
        $path = self::statePath();
        if (!is_file($path)) return [];
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || (isset($data['jobs']) && !is_array($data['jobs'])) || (isset($data['runs']) && !is_array($data['runs']))) throw new \RuntimeException('Maintenance status is unreadable.');
        foreach (['jobs', 'runs'] as $section) {
            foreach ($data[$section] ?? [] as $entry) {
                if (!is_array($entry) || (isset($entry['cursor']) && (!is_int($entry['cursor']) || $entry['cursor'] < 0))) throw new \RuntimeException('Maintenance status is unreadable.');
                foreach (['attempt', 'completed', 'time', 'lastSuccess', 'count'] as $field) {
                    if (isset($entry[$field]) && (!is_int($entry[$field]) || $entry[$field] < 0)) throw new \RuntimeException('Maintenance status is unreadable.');
                }
                foreach (['ok', 'more'] as $field) {
                    if (isset($entry[$field]) && !is_bool($entry[$field])) throw new \RuntimeException('Maintenance status is unreadable.');
                }

            }
        }
        return $data;
    }

    /** One batch per job; callers repeat if more=true. Preview never writes state. */
    public static function run(string $mode = 'manual', ?string $only = null, bool $preview = false): array
    {
        if (!in_array($mode, ['manual', 'request', 'cli', 'webcron'], true)) throw new \InvalidArgumentException('Unknown maintenance mode');
        if ($only !== null && !isset(self::$jobs[$only])) throw new \InvalidArgumentException('Unknown maintenance task');
        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('Maintenance folder is not writable.');
        $lock = fopen($dir . '/runner.lock', 'c');
        if (!$lock) throw new \RuntimeException('Maintenance lock is unavailable.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return ['busy' => true, 'results' => []]; }
        try {
            $state = self::status();
            $results = [];
            $remaining = [];
            $start = microtime(true);
            $jobs = self::$jobs;
            // Oldest attempted task first: a failing first task must not starve others.
            uksort($jobs, static fn($a, $b) => ((int) ($state['jobs'][$a]['attempt'] ?? 0)) <=> ((int) ($state['jobs'][$b]['attempt'] ?? 0)));
            foreach ($jobs as $id => $job) {
                if ($only !== null && $id !== $only) continue;
                $last = $state['jobs'][$id] ?? [];
                if ($mode === 'request' && ((int) ($last['attempt'] ?? 0) + 300 > time() || (empty($last['more']) && (int) ($last['completed'] ?? 0) + $job['interval'] > time()))) continue;
                if ($results !== [] && ($mode === 'request' || microtime(true) - $start > 2.0)) { $remaining[] = $id; continue; }
                try {
                    $result = ($job['run'])($preview, 100, microtime(true) + ($mode === 'request' ? .15 : 1.5), $last['cursor'] ?? 0);
                    if (!is_array($result) || !isset($result['count']) || !is_int($result['count']) || $result['count'] < 0 || $result['count'] > 100 || (isset($result['cursor']) && (!is_int($result['cursor']) || $result['cursor'] < 0)) || (isset($result['more']) && !is_bool($result['more']))) throw new \UnexpectedValueException('Invalid maintenance task result.');
                    $results[$id] = ['count' => (int) $result['count'], 'more' => (bool) ($result['more'] ?? false), 'ok' => true, 'cursor' => $result['cursor'] ?? 0];
                } catch (\Throwable $error) {
                    // Detailed exceptions can contain database addresses; keep them out of the public status.
                    $results[$id] = ['count' => 0, 'more' => true, 'ok' => false, 'cursor' => $last['cursor'] ?? 0];
                    error_log('Maintenance task failed: ' . $id . ' (' . get_class($error) . ')');
                }
                if (!$preview) {
                    $entry = ['attempt' => time(), 'mode' => $mode] + $results[$id];
                    if ($results[$id]['ok'] && !$results[$id]['more']) $entry['completed'] = time();
                    else $entry['completed'] = (int) ($last['completed'] ?? 0);
                    $state['jobs'][$id] = $entry;
                }
            }
            if (!$preview && $results !== []) {
                $success = !in_array(false, array_column($results, 'ok'), true);
                $previousRun = $state['runs'][$mode] ?? [];
                $lastSuccess = Overview::successTime($previousRun);
                $state['runs'][$mode] = ['time' => time(), 'ok' => $success, 'lastSuccess' => $success ? time() : $lastSuccess, 'remaining' => $remaining, 'php' => PHP_VERSION, 'sapi' => PHP_SAPI];
                $temp = self::statePath() . '.tmp';
                if (file_put_contents($temp, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temp, self::statePath())) throw new \RuntimeException('Maintenance status could not be saved.');
                @chmod(self::statePath(), 0600);
            }
            return ['busy' => false, 'preview' => $preview, 'remaining' => $remaining, 'results' => $results];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    /** Machine responses must not acquire a diagnostic footer on shutdown. */
    public static function machineResponse(): void
    {
        if (class_exists(\systopic\debug\sysdebug::class, false) && \systopic\debug\sysdebug::$debug !== null) {
            \systopic\debug\sysdebug::$debug->isErased = true;
            \systopic\debug\sysdebug::$debug->isErasedIgnoreErrors = true;
        }
        while (ob_get_level() > 0) { if (!ob_end_clean()) break; }
    }

    public static function boot(): void
    {
        if (PHP_SAPI === 'cli') {
            $input = \Systopic\System\Agent\AgentCli::input();
            if (($input['maintenance'] ?? false) === true) {
                try {
                    $result = self::run('cli', $input['job'] ?? null, (bool) ($input['preview'] ?? false));
                    self::machineResponse();
                    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
                    exit($result['busy'] || in_array(false, array_column($result['results'], 'ok'), true) ? 1 : 0);
                } catch (\Throwable $error) { self::machineResponse(); fwrite(STDERR, "Maintenance failed; check project permissions and logs.\n"); exit(1); }
            }
            return;
        }
        Webcron::serve();
        // Only ordinary reads can trigger housekeeping; never extend uploads or mutations.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') return;
        register_shutdown_function(static function (): void {
            $last = error_get_last();
            if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
            try {
                // Cheap disk check; the shared lock and per-job timestamps decide what is due.
                $dir = self::directory();
                if ((is_dir($dir) && !is_writable($dir)) || (!is_dir($dir) && !is_writable(dirname($dir)))) return;
                $path = self::statePath();
                if (is_file($path) && filemtime($path) > time() - 300) return;
                self::run('request');
            } catch (\Throwable $error) { error_log('Maintenance request fallback failed (' . get_class($error) . ')'); }
        });
    }
}
