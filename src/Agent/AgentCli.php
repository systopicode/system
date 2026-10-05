<?php

declare(strict_types=1);

namespace Systopic\System\Agent;

use Systopic\System\Http;

/**
 * Runs a web request from the command line — the entry point agents (Claude,
 * Cursor, test scripts) use to reproduce what a browser does, without a web
 * server. See .cursor/docs/cli-testing.md.
 *
 *     php public/index.php @request.json      payload from a file
 *     php public/index.php - < request.json   payload from STDIN
 *     php public/index.php '{"path":"/"}'     inline (avoid: shells mangle JSON)
 *
 * The payload describes the request:
 *
 *     {
 *       "path":     "/projects/x/public/kunden/",   REQUEST_URI
 *       "method":   "GET",                           default GET, "put" forces PUT
 *       "output":   "json" | "html",                 OUTPUT_FORMAT, default json
 *       "ajax":     true,                            sets X-Requested-With
 *       "user":     2,                               AUTOLOGON_ID — log in as this user
 *       "instance": "STAGING",                       pin the config/ instance
 *       "get":  {…}, "post": {…}, "put": {…},        request data
 *       "session": {…},                              merged into $_SESSION on start
 *       "sessionId": "…", "sessionSavePath": "…",    reuse a session across calls
 *       "server": {…}                                $_SERVER overrides (HTTP_HOST, SCRIPT_NAME …)
 *     }
 *
 * Moved out of every project's public/index.php, where it sat as ~75 lines
 * of copy. The effects are unchanged: the same constants, the same two
 * globals http.php picks up (`__cli_put_data`, `__cli_session_merge`), the
 * same synthesized $_SERVER from Http::getCliEnvironment().
 */
final class AgentCli
{
    /** The payload of the current CLI request — empty on the web. */
    private static array $input = [];

    /**
     * Prepares the request. On the web it only defaults OUTPUT_FORMAT; on the
     * CLI it reads the payload, defines the CLI constants and replaces
     * $_SERVER / $_GET / $_POST with the described request.
     *
     * @param string $entryFile __FILE__ of public/index.php
     */
    public static function boot(string $entryFile): void
    {
        if (PHP_SAPI === 'cli') {
            $publicDir   = dirname($entryFile);
            self::$input = self::readPayload($_SERVER['argv'] ?? [], $publicDir);
            self::apply(self::$input, $entryFile, $publicDir);
        }
        defined('OUTPUT_FORMAT') || define('OUTPUT_FORMAT', 'html');
    }

    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /** @return array<string, mixed> the decoded payload ([] on the web) */
    public static function input(): array
    {
        return self::$input;
    }

    // -------------------------------------------------------------------------
    // Payload
    // -------------------------------------------------------------------------

    /**
     * Windows cmd.exe / PowerShell split JSON in argv or strip its quotes, so
     * inline JSON arrives broken and REQUEST_URI stays "/". Reliable are
     * `@file.json` and `-` (STDIN).
     *
     * `@file` is looked up, in order:
     *   1. literal path (relative to the cwd, or absolute)
     *   2. <publicDir>/var/cursor/<file>   agent convention, see cli-testing.md
     *   3. <publicDir>/<file>              legacy fallback
     *
     * @param list<string> $argv
     * @return array<string, mixed>
     */
    public static function readPayload(array $argv, string $publicDir): array
    {
        $raw = '{}';
        $arg = $argv[1] ?? null;
        if ($arg === '-') {
            $raw = stream_get_contents(STDIN) ?: '{}';
        } elseif (is_string($arg) && str_starts_with($arg, '@')) {
            $path = substr($arg, 1);
            $rel  = ltrim($path, '/\\');
            foreach ([$path, $publicDir . '/var/cursor/' . $rel, $publicDir . '/' . $rel] as $candidate) {
                if (is_file($candidate) && is_readable($candidate)) {
                    $raw = (string) file_get_contents($candidate);
                    break;
                }
            }
        } elseif ($arg !== null) {
            $raw = implode('', array_slice($argv, 1));
        }
        $decoded = json_decode(trim($raw, "\xEF\xBB\xBF \t\r\n"), true);
        return is_array($decoded) ? $decoded : [];
    }

    // -------------------------------------------------------------------------
    // Apply
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $in */
    private static function apply(array $in, string $entryFile, string $publicDir): void
    {
        define('OUTPUT_FORMAT', $in['output'] ?? 'json');

        if (isset($in['user'])) {
            define('AUTOLOGON_ID', (int) $in['user']);
        }
        if (!empty($in['instance']) && is_string($in['instance'])) {
            // Which config/ instance to run as ('DEV.ojoffice', …); default:
            // matched by serverIp / rootDir like a web request.
            define('CONFIG_INSTANCE', $in['instance']);
        }

        if (!empty($in['sessionSavePath']) && is_string($in['sessionSavePath'])) {
            session_save_path($in['sessionSavePath']);
        }
        if (!empty($in['sessionId']) && is_string($in['sessionId'])) {
            session_id($in['sessionId']);
        }
        if (!empty($in['session']) && is_array($in['session'])) {
            $GLOBALS['__cli_session_merge'] = $in['session'];   // read by http::sessionStartOnce()
        }

        if (!empty($in['ajax'])) {
            // http::ajax() only fires on X-Requested-With: XMLHttpRequest.
            // Explicit "server" overrides win.
            $in['server'] = ($in['server'] ?? []) + ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        }
        if (!empty($in['get']) && is_array($in['get'])) {
            $_GET     = array_merge($_GET, $in['get']);
            $_REQUEST = array_merge($_REQUEST, $in['get']);
        }
        if (!empty($in['post']) && is_array($in['post'])) {
            $_POST    = array_merge($_POST, $in['post']);
            $_REQUEST = array_merge($_REQUEST, $in['post']);
        }
        if (!empty($in['put']) && is_array($in['put'])) {
            $GLOBALS['__cli_put_data'] = $in['put'];            // read by http::readPut()
            $in['method'] = 'PUT';
        }
        // The binary part of a PUT (a file upload span): "binaryFile": "<path>"
        // — what the browser sends after the JSON (`/*json:<n>*/…`).
        if (!empty($in['binaryFile']) && is_string($in['binaryFile']) && is_file($in['binaryFile'])) {
            $GLOBALS['__cli_binary'] = (string) file_get_contents($in['binaryFile']);   // read by http::readPut()
        }

        $_SERVER = array_replace($_SERVER, Http::getCliEnvironment($entryFile, $publicDir, $in));
    }
}
