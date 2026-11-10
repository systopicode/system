<?php

declare(strict_types=1);

namespace Systopic\System\Setup;

/**
 * What a script in bin/ knows about the run it is in: from a terminal, or
 * from the setup page (Setup\Scripts), which starts it as a CLI process.
 *
 * A script that the setup may start says so in its docblock:
 *
 *   @setup      what it does, one line
 *   @setup-run  --check            a call offered as a button (any number)
 *
 * The rules for such a script:
 *
 *   - It finds out itself whether it would break something, and asks first:
 *     ScriptRun::confirm('config.local.json is DEV.ojhome already. Take STAGING over it?').
 *     In a terminal that is a question on the console, `--yes` answers it in
 *     advance. From the setup the run ends with ASK, the page shows the
 *     question, and "Yes" starts the same call again with the answer.
 *   - The request the setup came with is in ScriptRun::server(): host,
 *     address, path. That is how a script tells which installation it is on,
 *     where a terminal knows no host. Cookies, credentials and the setup
 *     token are taken out.
 *   - It runs in the project folder; exit 0 is success, ASK a question,
 *     anything else a failure. What it prints is shown as it is.
 */
final class ScriptRun
{
    /** the exit code of a run that waits for an answer */
    public const ASK = 10;

    /** the request of the setup page, JSON */
    public const ENV_SERVER = 'SYSTOPIC_SETUP_SERVER';

    /** the questions answered with yes, their keys comma separated */
    public const ENV_CONFIRMED = 'SYSTOPIC_SETUP_CONFIRMED';

    /** the line a question is printed on: `?? <key> <question>` */
    public const QUESTION_PREFIX = '?? ';

    /** Was the script started by the setup page? */
    public static function fromSetup(): bool
    {
        return getenv(self::ENV_SERVER) !== false;
    }

    /**
     * The request this run is about: the one of the setup page when it
     * started the script, else what PHP has ($_SERVER of the CLI).
     *
     * @return array<string, mixed>
     */
    public static function server(): array
    {
        $json = getenv(self::ENV_SERVER);
        $server = is_string($json) ? json_decode($json, true) : null;
        return is_array($server) ? $server : $_SERVER;
    }

    /**
     * Go on? Asks once per question. True when answered with yes - in the
     * terminal, in advance with --yes, or in the setup on its second run.
     * A run that cannot ask (no terminal, no --yes) prints the question and
     * ends with ASK, so nothing happens without an answer.
     */
    public static function confirm(string $question): bool
    {
        global $argv;
        if (in_array('--yes', (array) ($argv ?? []), true)) {
            return true;
        }
        $key = self::key($question);
        if (self::fromSetup()) {
            $given = explode(',', (string) getenv(self::ENV_CONFIRMED));
            if (in_array($key, $given, true)) {
                return true;
            }
            echo self::QUESTION_PREFIX . $key . ' ' . str_replace(["\r", "\n"], ' ', $question) . "\n";
            exit(self::ASK);
        }
        if (defined('STDIN') && stream_isatty(STDIN)) {
            echo $question . ' [y/N] ';
            return in_array(strtolower(trim((string) fgets(STDIN))), ['y', 'yes', 'j', 'ja'], true);
        }
        echo self::QUESTION_PREFIX . $key . ' ' . $question . " (run again with --yes)\n";
        exit(self::ASK);
    }

    /** the short key of a question, the same on every run */
    public static function key(string $question): string
    {
        return substr(sha1($question), 0, 12);
    }
}
