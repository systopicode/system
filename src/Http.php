<?php

namespace Systopic\System;

class Http
{
    private static ?string $socketIp = null;

    static function getSocketIp(): string
    {
        if (self::$socketIp !== null) {
            return self::$socketIp;
        }

        if (extension_loaded('sockets')) {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket && @socket_connect($socket, '8.8.8.8', 53)) {
                socket_getsockname($socket, $ip);
                socket_close($socket);
                if ($ip && $ip !== '0.0.0.0') {
                    return self::$socketIp = $ip;
                }
            }
            $socket && socket_close($socket);
        } else {
            $sock = @fsockopen('udp://8.8.8.8', 53, $errno, $errstr, 2);
            if ($sock) {
                $name = explode(':', stream_socket_get_name($sock, false))[0];
                fclose($sock);
                if ($name && $name !== '0.0.0.0') {
                    return self::$socketIp = $name;
                }
            }
        }

        $hostname = gethostname();
        if ($hostname) {
            $resolved = gethostbyname($hostname);
            if ($resolved !== $hostname) {
                return self::$socketIp = $resolved;
            }
        }

        return self::$socketIp = '127.0.0.1';
    }

    /**
     * Detect the web server document root from __DIR__ by finding
     * common webroot directory names (www, htdocs, public_html, html).
     * Returns [documentRoot, scriptName] or null if no marker found.
     */
    private static function detectWebRoot(string $file, string $dir): ?array
    {
        $normalized = str_replace('\\', '/', $dir);
        $markers = ['www', 'htdocs', 'public_html', 'html'];
        foreach ($markers as $marker) {
            $needle = '/' . $marker . '/';
            $pos = strpos($normalized, $needle);
            if ($pos !== false) {
                $markerEnd = $pos + strlen($needle);
                $docRoot = substr($normalized, 0, $markerEnd - 1);
                $scriptName = substr($normalized, $markerEnd - 1) . '/' . basename($file);
                return [$docRoot, $scriptName];
            }
            if (str_ends_with($normalized, '/' . $marker)) {
                return [$normalized, '/' . basename($file)];
            }
        }
        return null;
    }

    /**
     * Build a $_SERVER-compatible array for CLI execution.
     * 
     * @param string $file  Pass __FILE__ from the entry point
     * @param string $dir   Pass __DIR__ from the entry point
     * @param array  $overrides  Merged CLI input (path, method, server, output)
     */
    static function getCliEnvironment(string $file, string $dir, array $overrides = []): array
    {
        $localIp = self::getSocketIp();
        $filePath = str_replace('\\', '/', $file);
        $dirPath = str_replace('\\', '/', $dir);

        $webRoot = self::detectWebRoot($file, $dir);
        $documentRoot = $webRoot ? $webRoot[0] : $dirPath;
        $scriptName = $webRoot ? $webRoot[1] : ('/' . basename($file));

        return array_replace([
            'SERVER_NAME'        => $localIp,
            'HTTP_HOST'          => $localIp,
            'REMOTE_ADDR'        => $localIp,
            'SCRIPT_NAME'        => $scriptName,
            'SCRIPT_FILENAME'    => $filePath,
            'DOCUMENT_ROOT'      => rtrim($documentRoot, '/'),
            'REQUEST_URI'        => $overrides['path'] ?? '/',
            'REQUEST_METHOD'     => $overrides['method'] ?? 'GET',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'HTTP_USER_AGENT'    => 'Cursor-AI/1.0',
        ], $overrides['server'] ?? []);
    }

    /**
     * Compare actual $_SERVER with the emulated CLI environment.
     * Only includes keys that are present in the emulated set.
     */
    static function compareEnvironment(string $file, string $dir): array
    {
        $emulated = self::getCliEnvironment($file, $dir, [
            'path'   => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ]);

        $comparison = [];
        foreach ($emulated as $key => $emulatedValue) {
            $actualValue = $_SERVER[$key] ?? null;
            $match = $actualValue === $emulatedValue;
            $entry = [
                'emulated' => $emulatedValue,
                'actual'   => $actualValue,
            ];
            if (!$match) {
                $entry['match'] = false;
            }
            $comparison[$key] = $entry;
        }

        $extraKeys = array_diff_key($_SERVER, $emulated);
        $relevantExtra = array_filter($extraKeys, function ($key) {
            return str_starts_with($key, 'HTTP_') || in_array($key, [
                'HTTPS', 'SERVER_PORT', 'SERVER_ADDR', 'QUERY_STRING',
                'CONTENT_TYPE', 'CONTENT_LENGTH', 'PHP_SELF',
            ]);
        }, ARRAY_FILTER_USE_KEY);

        if ($relevantExtra) {
            $comparison['_extra_server_keys'] = $relevantExtra;
        }

        return $comparison;
    }
}
