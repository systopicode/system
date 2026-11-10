<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/**
 * The Centrifugo hub on this machine (`~/.centrifugo` of the user who owns
 * the project). One hub for every project. The environment file only stores
 * the key that lets a site talk to it, and that key is never shown.
 */
final class CentrifugoHub
{
    /**
     * @return array{installed: bool, configured: bool, running: bool}
     */
    public static function status(): array
    {
        $home = self::home();
        $config = self::readConfig();
        return [
            'installed' => $home !== null && is_executable($home . '/.centrifugo/centrifugo'),
            'configured' => $config !== null,
            'running' => self::running($config),
        ];
    }

    /** A Mac can install the hub from here. A server uses its own script. */
    public static function canInstall(): bool
    {
        return PHP_OS_FAMILY === 'Darwin' && self::installScript() !== null && !self::status()['installed'];
    }

    /**
     * The relay block for an environment file. Null when the hub config
     * cannot be read. The values are secrets and must not be shown.
     *
     * @return array{api: string, apiKey: string, secret: string, client: string, plainTopics: bool}|null
     */
    public static function relayBlock(bool $plainTopics): ?array
    {
        $config = self::readConfig();
        if ($config === null) {
            return null;
        }
        $port = (string) ($config['http_server']['internal_port'] ?? '');
        $key = (string) ($config['http_api']['key'] ?? '');
        $secret = (string) ($config['client']['token']['hmac_secret_key'] ?? '');
        if ($port === '' || $key === '' || strlen($secret) < 16) {
            return null;
        }
        return [
            'api' => 'http://127.0.0.1:' . $port,
            'apiKey' => $key,
            'secret' => $secret,
            'client' => '/connection/',
            'plainTopics' => $plainTopics,
        ];
    }

    /**
     * First install only. Running it again would restart the hub every
     * project on this machine uses, so an installed hub is left as it is.
     */
    public static function install(): bool
    {
        if (self::status()['installed']) {
            return true;
        }
        $script = self::installScript();
        $home = self::home();
        if ($script === null || $home === null || !is_dir($home)) {
            return false;
        }
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
            $host = 'localhost';
        }
        $code = self::command(['bash', $script, '--origins=https://' . $host . ',https://localhost'], $home);
        return $code === 0 && self::status()['installed'];
    }

    public static function originOwner(string $file): string
    {
        return hash('sha256', rtrim((string) \fs::$projectRoot, '/') . '|' . $file);
    }

    /** @return list<string> */
    public static function ownedOrigins(string $file): array
    {
        $home = self::home();
        $registry = $home ? CentrifugoOrigins::read($home . '/.centrifugo/project-origins.json') : null;
        return $registry['owners'][self::originOwner($file)] ?? [];
    }

    /** Only the permission list changes; this never restarts the shared process. */
    public static function changeOrigin(string $file, string $origin, bool $add): bool
    {
        $home = self::home();
        return $home !== null && CentrifugoOrigins::change($home . '/.centrifugo/config.json', $home . '/.centrifugo/project-origins.json', self::originOwner($file), $origin, $add);
    }

    /** @return list<string>|null */
    public static function allowedOrigins(): ?array
    {
        $config = self::readConfig();
        if ($config === null) return null;
        $allowed = $config['client']['allowed_origins'] ?? [];
        return is_array($allowed) ? array_values(array_filter($allowed, 'is_string')) : null;
    }

    /** @param list<mixed> $rules @return list<string> */
    public static function projectOrigins(array $rules, string $scheme): array
    {
        $origins = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;
            foreach (['serverName', 'serverIp'] as $key) {
                foreach ((array) ($rule[$key] ?? []) as $value) {
                $host = strtolower(trim((string) $value));
                if ($host === '' || str_contains($host, '*')) continue;
                if (preg_match('/^[0-9.]+$/', $host) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $host = '[' . $host . ']';
                $url = $scheme . '://' . $host;
                $parts = parse_url($url);
                if ($parts === false || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) continue;
                if (!preg_match('/^(?:[a-z0-9.-]+|\[[a-f0-9:]+\])(?::[0-9]+)?$/', $host)) continue;
                $origins[] = $url;
                }
            }
        }
        return array_values(array_unique($origins));
    }

    /** @param list<string> $allowed */
    public static function originAllowed(string $origin, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if (preg_match('~^' . str_replace('\*', '.*', preg_quote($pattern, '~')) . '$~iD', $origin)) return true;
        }
        return false;
    }

    private static function installScript(): ?string
    {
        $roots = [dirname(__DIR__, 3)];
        $real = realpath(dirname(__DIR__, 3));
        if ($real !== false) {
            $roots[] = $real;
        }
        foreach ($roots as $root) {
            $path = dirname($root) . '/relay/bin/macos/install.sh';
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /** @param array<string, mixed>|null $config */
    private static function running(?array $config): bool
    {
        $port = (int) ($config['http_server']['internal_port'] ?? 8001);
        if ($port < 1) {
            return false;
        }
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
        if ($socket === false) {
            return false;
        }
        fclose($socket);
        return true;
    }

    /** @return array<string, mixed>|null */
    private static function readConfig(): ?array
    {
        $home = self::home();
        if ($home === null) {
            return null;
        }
        $path = $home . '/.centrifugo/config.json';
        if (!is_readable($path)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($data) ? $data : null;
    }

    /** Home of the user who owns this project. The hub lives in ~/.centrifugo. */
    private static function home(): ?string
    {
        $root = rtrim(str_replace('\\', '/', (string) \fs::$projectRoot), '/');
        $candidates = [];
        $config = $root . '/config';
        if (is_dir($config) && function_exists('posix_getpwuid')) {
            $owner = @fileowner($config);
            if ($owner !== false) {
                $info = @posix_getpwuid($owner);
                if (is_array($info) && ($info['dir'] ?? '') !== '') {
                    $candidates[] = $info['dir'];
                }
            }
        }
        if (preg_match('#^/Users/[^/]+#', $root, $match)) {
            $candidates[] = $match[0];
        }
        $env = getenv('HOME');
        if (is_string($env) && $env !== '') {
            $candidates[] = $env;
        }
        foreach ($candidates as $home) {
            if (is_dir($home . '/.centrifugo')) {
                return $home;
            }
        }
        return $candidates[0] ?? null;
    }

    /** @param list<string> $args */
    private static function command(array $args, ?string $home = null): int
    {
        $env = null;
        if ($home !== null) {
            $env = [
                'HOME' => $home,
                'USER' => basename($home),
                'LOGNAME' => basename($home),
                'PATH' => '/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:/Applications/ServBay/bin',
                'TMPDIR' => sys_get_temp_dir(),
            ];
        }
        $pipes = [];
        $process = proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $home, $env);
        if (!is_resource($process)) {
            return 1;
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process);
    }
}
