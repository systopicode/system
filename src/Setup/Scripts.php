<?php

declare(strict_types=1);

namespace Systopic\System\Setup;

/**
 * The scripts of bin/ on the setup page: the project's (bin/*.php) and those
 * of the packages (vendor/systopic/<package>/bin/*.php). Reached with the
 * setup token (`?setup=<token>&scripts=1`), before the config is read, so it
 * works on an installation that has none yet.
 *
 * A script runs only when its docblock says `@setup` (ScriptRun has the
 * rules); the others are listed, greyed. It runs as a CLI process of the PHP
 * this site runs on, in the project folder, with the request of this page
 * (ScriptRun::server()). A question it asks ends the run; "Yes" starts the
 * same call again with the answer.
 *
 * Nothing here spends the token: running a script is not saving the setup.
 */
final class Scripts
{
    /** seconds a run may take */
    private const TIMEOUT = 300;

    /** request keys that never reach a script */
    private const HIDDEN = '~COOKIE|AUTH|PASS|SECRET|TOKEN|KEY|SIGNATURE~i';

    public function __construct(
        private readonly Setup $setup,
        private readonly string $root,
        private readonly array $server,
        private readonly string $token,
    ) {}

    /** Does the request ask for the scripts? */
    public static function asked(): bool
    {
        return (string) ($_POST['scripts'] ?? $_GET['scripts'] ?? '') !== '';
    }

    public function handle(): void
    {
        $h = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $scripts = $this->scripts();
        $result = '';
        if (($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $name = (string) ($_POST['script'] ?? '');
            $script = $scripts[$name] ?? null;
            $result = $script === null || !$script['setup']
                ? '<p class="errors">' . $h($name) . ' cannot be started here.</p>'
                : $this->result($script, (string) ($_POST['args'] ?? ''), (string) ($_POST['confirmed'] ?? ''));
        }
        $list = '';
        foreach ($scripts as $name => $script) {
            $list .= $this->entry($name, $script);
        }
        if ($list === '') {
            $list = '<p class="hint">No scripts in bin/.</p>';
        }
        $this->setup->page('Scripts of ' . basename($this->root), <<<HTML
            <p>The scripts of <code>bin/</code>, of the project and its packages. A script marked <code>@setup</code> may run here:
            as the PHP of this site, in the project folder. It checks itself whether it would break something and asks first.</p>
            $result
            $list
            HTML);
    }

    /**
     * bin/*.php of the project, then of each package: name => file, the
     * docblock's `@setup` line, its `@setup-run` calls.
     *
     * @return array<string, array{file: string, setup: bool, about: string, runs: list<string>}>
     */
    public function scripts(): array
    {
        $found = [];
        $dirs = ['bin' => $this->root . '/bin'];
        foreach (glob($this->root . '/vendor/systopic/*/bin', GLOB_ONLYDIR) ?: [] as $dir) {
            $dirs['vendor/systopic/' . basename(dirname($dir)) . '/bin'] = $dir;
        }
        foreach ($dirs as $prefix => $dir) {
            $files = glob($dir . '/*.php') ?: [];
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                $found[$prefix . '/' . basename($file)] = self::describe($file);
            }
        }
        return $found;
    }

    /** @return array{file: string, setup: bool, about: string, runs: list<string>} */
    private static function describe(string $file): array
    {
        $head = (string) @file_get_contents($file, false, null, 0, 16384);
        $doc = preg_match('~/\*\*(.*?)\*/~s', $head, $m) === 1 ? $m[1] : '';
        $about = '';
        $runs = [];
        $setup = false;
        foreach (preg_split('~\R~', $doc) ?: [] as $line) {
            $line = trim(ltrim(trim($line), '*'));
            if (preg_match('~^@setup-run\s+(.+)$~', $line, $r) === 1) {
                $runs[] = trim($r[1]);
            } elseif (preg_match('~^@setup(?:\s+(.*))?$~', $line, $r) === 1) {
                $setup = true;
                $about = trim($r[1] ?? '');
            } elseif ($about === '' && !$setup && $line !== '' && !str_starts_with($line, '@')) {
                // without @setup: the first line of the docblock says what it is
                $about = $line;
            }
        }
        return ['file' => $file, 'setup' => $setup, 'about' => $about, 'runs' => $runs];
    }

    /** @param array{file: string, setup: bool, about: string, runs: list<string>} $script */
    private function entry(string $name, array $script, string $args = ''): string
    {
        $h = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        if (!$script['setup']) {
            return '<fieldset style="opacity:.55"><legend>' . $h($name) . '</legend><p class="hint">' . $h($script['about'])
                . '</p><p class="hint">Not marked <code>@setup</code>: it does not say that it checks before it changes something. Run it on the server itself.</p></fieldset>';
        }
        $runs = '';
        foreach ($script['runs'] as $run) {
            $runs .= '<button type="submit" name="args" value="' . $h($run) . '" class="secondary" style="margin:0 8px 8px 0">' . $h($run) . '</button>';
        }
        $args = $args !== '' ? $args : (string) (($_POST['script'] ?? '') === $name ? ($_POST['args'] ?? '') : '');
        return '<fieldset><legend>' . $h($name) . '</legend><p>' . $h($script['about']) . '</p>'
            . '<form method="post">' . $this->setup->tokenField() . '<input type="hidden" name="scripts" value="1"><input type="hidden" name="script" value="' . $h($name) . '">'
            // the field first: a preset button comes later in the form and wins
            . '<label><span>arguments</span><input name="args" value="' . $h($args) . '" autocomplete="off" spellcheck="false" placeholder="--option=value"></label>'
            . ($runs === '' ? '' : '<p>' . $runs . '</p>')
            . '<button>run</button></form></fieldset>';
    }

    /**
     * Runs the script and shows what it printed; a question with "Yes".
     *
     * @param array{file: string, setup: bool, about: string, runs: list<string>} $script
     */
    private function result(array $script, string $args, string $confirmed): string
    {
        $h = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $name = (string) ($_POST['script'] ?? '');
        $confirmed = implode(',', array_filter(explode(',', $confirmed), static fn(string $key): bool => preg_match('~^[0-9a-f]{12}$~', $key) === 1));
        [$code, $output] = $this->run($script['file'], self::split($args), $confirmed);
        $questions = [];
        foreach (preg_split('~\R~', $output) ?: [] as $line) {
            if (str_starts_with($line, ScriptRun::QUESTION_PREFIX) && preg_match('~^\?\? ([0-9a-f]{12}) (.*)$~', $line, $m) === 1) {
                $questions[$m[1]] = $m[2];
            }
        }
        $shown = (string) preg_replace('~^\?\? [0-9a-f]{12} .*\R?~m', '', $output);
        $status = match (true) {
            $code === 0                         => 'done',
            $code === ScriptRun::ASK            => 'asks',
            default                             => 'ended with ' . $code,
        };
        $out = '<fieldset><legend>' . $h($name . ' ' . $args) . ' · ' . $h($status) . '</legend>'
            . ($shown === '' ? '' : '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;margin:0 0 10px">' . $h($shown) . '</pre>');
        if ($code === ScriptRun::ASK && $questions !== []) {
            $keys = implode(',', array_filter([$confirmed, ...array_keys($questions)]));
            $out .= '<form method="post">' . $this->setup->tokenField()
                . '<input type="hidden" name="scripts" value="1"><input type="hidden" name="script" value="' . $h($name) . '">'
                . '<input type="hidden" name="args" value="' . $h($args) . '"><input type="hidden" name="confirmed" value="' . $h($keys) . '">';
            foreach ($questions as $question) {
                $out .= '<p style="color:orange">' . $h($question) . '</p>';
            }
            $out .= '<button>yes, go on</button></form>';
        }
        return $out . '</fieldset>';
    }

    /**
     * `--adopt=STAGING "a b"` → ['--adopt=STAGING', 'a b']: quotes group,
     * no shell sees it (proc_open with a list).
     *
     * @return list<string>
     */
    public static function split(string $args): array
    {
        preg_match_all('~"([^"]*)"|\'([^\']*)\'|(\S+)~', $args, $m, PREG_SET_ORDER);
        return array_map(static fn(array $part): string => $part[3] ?? $part[2] ?? $part[1], $m);
    }

    /**
     * @param list<string> $args
     * @return array{0: int, 1: string} exit code, what it printed
     */
    private function run(string $file, array $args, string $confirmed): array
    {
        $php = self::php();
        if ($php === null) {
            return [-1, 'No PHP for the command line next to ' . PHP_BINARY . ' (PHP_BINDIR ' . PHP_BINDIR . ').'];
        }
        if (!self::canStart()) {
            return [-1, 'proc_open is disabled on this server: run it there by hand, ' . $php . ' ' . $file];
        }
        $env = getenv();
        $env[ScriptRun::ENV_SERVER] = (string) json_encode($this->request(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $env[ScriptRun::ENV_CONFIRMED] = $confirmed;
        $process = @proc_open([$php, $file, ...$args], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $this->root, $env);
        if (!is_resource($process)) {
            return [-1, 'The script could not be started: ' . $php . ' ' . $file];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        $output = '';
        $until = time() + self::TIMEOUT;
        set_time_limit(self::TIMEOUT + 30);
        while (true) {
            $chunk = (string) fread($pipes[1], 65536);
            $output .= $chunk;
            $status = proc_get_status($process);
            if (!$status['running']) {
                $output .= (string) stream_get_contents($pipes[1]);
                break;
            }
            if (time() > $until) {
                proc_terminate($process);
                $output .= "\n(stopped after " . self::TIMEOUT . " seconds)";
                break;
            }
            if ($chunk === '') {
                usleep(100000);
            }
        }
        fclose($pipes[1]);
        $closed = proc_close($process);
        $code = isset($status['exitcode']) && $status['exitcode'] !== -1 ? (int) $status['exitcode'] : $closed;
        return [$code, $output];
    }

    /**
     * The request of this page for the script: scalars only, without
     * cookies, credentials and the setup token.
     *
     * @return array<string, scalar>
     */
    private function request(): array
    {
        $out = [];
        foreach ($this->server as $key => $value) {
            if (!is_scalar($value) || preg_match(self::HIDDEN, (string) $key) === 1) {
                continue;
            }
            $out[(string) $key] = is_string($value) && $this->token !== '' ? str_replace($this->token, '…', $value) : $value;
        }
        return $out;
    }

    /**
     * The PHP for the command line of the version this site runs on: the
     * binary itself on the CLI, else next to the FPM/CGI binary.
     */
    public static function php(): ?string
    {
        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }
        $exe = DIRECTORY_SEPARATOR === '\\' ? 'php.exe' : 'php';
        $binary = str_replace('\\', '/', PHP_BINARY);
        $candidates = [
            (string) preg_replace('~-(?:fpm|cgi)(\.exe)?$~i', '$1', $binary),   // php-cgi.exe → php.exe, php84-fpm → php84
            dirname($binary) . '/' . $exe,
            dirname($binary, 2) . '/bin/' . $exe,                               // Plesk: …/8.4/sbin/php-fpm → …/8.4/bin/php
            str_replace('\\', '/', PHP_BINDIR) . '/' . $exe,
        ];
        $candidates = array_values(array_filter(array_unique($candidates), static fn(string $candidate): bool => $candidate !== $binary));
        foreach ($candidates as $candidate) {
            if (@is_file($candidate)) {
                return $candidate;
            }
        }
        // open_basedir hides the binary from is_file() (Plesk: /opt/plesk/… is outside the
        // vhost), starting it is no file access: the one that answers with this version
        if (self::canStart()) {
            foreach ($candidates as $candidate) {
                if (self::answers($candidate)) {
                    return $candidate;
                }
            }
        }
        return null;
    }

    /** Does $php start and say it is the version this site runs on? */
    private static function answers(string $php): bool
    {
        $process = @proc_open([$php, '-r', 'echo PHP_VERSION;'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        fclose($pipes[0]);
        $version = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0 && $version === PHP_VERSION;
    }

    private static function canStart(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }
}
