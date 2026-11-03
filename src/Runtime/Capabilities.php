<?php

declare(strict_types=1);
namespace Systopic\System\Runtime;

/** Shared-host capabilities. Discovery is passive; execution tests are explicit. */
final class Capabilities
{
    public const TOOLS = ['ffmpeg' => 'Video processing and frames', 'ffprobe' => 'Video information', 'identify' => 'Optional ImageMagick command', 'magick' => 'Optional ImageMagick command'];

    public static function functionEnabled(string $name): bool
    {
        return function_exists($name) && !in_array($name, array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
    }

    public static function inspect(string $tool): array
    {
        if (!isset(self::TOOLS[$tool])) throw new \InvalidArgumentException('Unknown media tool');
        $path = \shellBinary::find($tool);
        return ['path' => $path, 'probeAvailable' => self::functionEnabled('proc_open') && self::functionEnabled('proc_get_status') && self::functionEnabled('proc_terminate') && self::functionEnabled('proc_close')];
    }

    /** Only fixed version commands; no shell, installation or permissions changes. */
    public static function probe(string $tool, float $timeout = 2.0): array
    {
        $info = self::inspect($tool);
        if ($info['path'] === null) return $info + ['status' => 'missing', 'detail' => 'Not found. This optional media function is unavailable.'];
        if (!$info['probeAvailable']) return $info + ['status' => 'untested', 'detail' => 'Located, but this host does not allow the bounded process test.'];
        $process = @proc_open([$info['path'], '-version'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) return $info + ['status' => 'failed', 'detail' => 'Located, but could not be started. Check permissions and host restrictions.'];
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + max(.1, min(3.0, $timeout));
        $output = ''; $exit = -1; $timedOut = false;
        try {
            do {
                foreach ([1, 2] as $channel) {
                    $chunk = (string) fread($pipes[$channel], 4096);
                    if (strlen($output) < 8192) $output .= substr($chunk, 0, 8192 - strlen($output));
                }
                $state = proc_get_status($process);
                if (!$state['running']) { $exit = (int) $state['exitcode']; break; }
                if (microtime(true) >= $deadline) { $timedOut = true; break; }
                usleep(10000);
            } while (true);
            if ($timedOut) {
                proc_terminate($process);
                usleep(50000);
                if (proc_get_status($process)['running']) proc_terminate($process, 9);
            }
        } finally {
            fclose($pipes[1]); fclose($pipes[2]);
            $closed = proc_close($process);
            if ($exit < 0 && $closed >= 0) $exit = $closed;
        }
        if ($timedOut) return $info + ['status' => 'timeout', 'detail' => 'Version test exceeded its time limit. No media file was processed.'];
        if ($exit !== 0) return $info + ['status' => 'failed', 'detail' => 'Version test failed. Check binary compatibility and permissions.'];
        $line = trim((string) (preg_split('/\R/', $output)[0] ?? ''));
        $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $line);
        $expected = match ($tool) { 'ffmpeg' => '/^ffmpeg version\b/i', 'ffprobe' => '/^ffprobe version\b/i', default => '/ImageMagick/i' };
        if (!preg_match($expected, $line)) return $info + ['status' => 'failed', 'detail' => 'The command responded, but did not identify itself as the expected tool.'];
        return $info + ['status' => 'ok', 'detail' => substr($line, 0, 240) ?: 'Version command completed.'];
    }
}
