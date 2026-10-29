<?php

declare(strict_types=1);
namespace Systopic\System\Maintenance;

final class Overview
{
    public static function state(): array
    {
        try { return Runner::status(); }
        catch (\Throwable) { return ['unreadable' => true]; }
    }

    public static function storageWritable(): bool
    {
        $dir = Runner::directory();
        if (is_file($dir . '/runner.lock') && !is_writable($dir . '/runner.lock')) return false;
        return is_dir($dir) ? is_writable($dir) : is_dir(dirname($dir)) && is_writable(dirname($dir));
    }

    public static function successTime(array $run): int
    {
        return (int) ($run['lastSuccess'] ?? (!empty($run['ok']) ? ($run['time'] ?? 0) : 0));
    }

    public static function time(int $stamp): string { return $stamp > 0 ? date('j M Y, H:i T', $stamp) : 'Not run yet'; }

    public static function needsAttention(): bool
    {
        $state = self::state();
        if (($state['unreadable'] ?? false) || !self::storageWritable()) return true;
        foreach ($state['jobs'] ?? [] as $id => $job) if (isset(Runner::jobs()[$id]) && !($job['ok'] ?? true)) return true;
        $runs = $state['runs'] ?? [];
        $external = max((int) ($runs['cli']['time'] ?? 0), (int) ($runs['webcron']['time'] ?? 0));
        $automatic = max($external, (int) ($runs['request']['time'] ?? 0));
        return ($external > 0 && $external < time() - 86400) || ($automatic > 0 && $automatic < time() - 86400);
    }

    public static function summary(): string
    {
        $state = self::state();
        if ($state['unreadable'] ?? false) return 'Cleanup status cannot be read. Check project file permissions.';
        if (!self::storageWritable()) return 'Cleanup cannot save its progress. Check project file permissions.';
        foreach ($state['jobs'] ?? [] as $id => $job) if (isset(Runner::jobs()[$id]) && !($job['ok'] ?? true)) return 'A cleanup task needs attention. See the tasks below.';
        $runs = $state['runs'] ?? [];
        $external = max((int) ($runs['cli']['time'] ?? 0), (int) ($runs['webcron']['time'] ?? 0));
        if ($external > 0 && $external < time() - 86400) return 'The external scheduler has not run in over 24 hours. Cleanup during site visits is still enabled.';
        if ($external > 0) return 'Your external scheduler is running. See the tasks for any remaining work.';
        if ((int) ($runs['request']['time'] ?? 0) > 0 && (int) $runs['request']['time'] < time() - 86400) return 'No automatic cleanup in over 24 hours. Visit the site or check your scheduler.';
        if (($runs['request']['time'] ?? 0) > 0) return 'Automatic cleanup is working during site visits.';
        return 'Automatic cleanup is enabled. Waiting for the first run.';
    }
}
