<?php

declare(strict_types=1);
namespace Systopic\System\Maintenance;

/** Optional machine endpoint: POST plus a separate revocable key, never a CMS login. */
final class Webcron
{
    private static function file(): string { return Runner::directory() . '/webcron-' . Runner::instanceKey() . '.key'; }
    public static function enabled(): bool { return is_file(self::file()); }
    public static function rotate(): string
    {
        $dir = Runner::directory();
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('Maintenance folder unavailable.');
        $secret = bin2hex(random_bytes(32));
        if (!\Systopic\System\Config\FileWriter::write(self::file(), hash('sha256', $secret), 0600)) {
            throw new \RuntimeException('Webcron key could not be saved.');
        }
        return $secret;
    }
    public static function disable(): void { if (self::enabled() && !unlink(self::file())) throw new \RuntimeException('Webcron key could not be removed.'); }
    public static function accepts(string $secret): bool
    {
        return strlen($secret) === 64 && self::enabled() && hash_equals(trim((string) file_get_contents(self::file())), hash('sha256', $secret));
    }
    public static function serve(): void
    {
        if (PHP_SAPI === 'cli') return;
        $path = rtrim((string) \http::$root, '/') . '/_maintenance/';
        if (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) !== $path) return;
        Runner::machineResponse();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $https = $https || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https';
        if (!$https) {
            http_response_code(403); echo '{"error":"HTTPS required"}'; exit;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !self::accepts((string) ($_SERVER['HTTP_X_MAINTENANCE_KEY'] ?? ''))) {
            http_response_code(403); echo '{"error":"Forbidden"}'; exit;
        }
        try {
            $state = Runner::status();
            if ((int) ($state['runs']['webcron']['time'] ?? 0) > time() - 60) { http_response_code(429); header('Retry-After: 60'); echo '{"error":"Retry later"}'; exit; }
            $result = Runner::run('webcron');
            http_response_code($result['busy'] ? 409 : (in_array(false, array_column($result['results'], 'ok'), true) ? 500 : 200));
            echo json_encode($result, JSON_THROW_ON_ERROR);
        } catch (\Throwable) { http_response_code(500); echo '{"error":"Maintenance failed"}'; }
        exit;
    }
}
