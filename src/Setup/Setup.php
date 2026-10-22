<?php

declare(strict_types=1);

namespace Systopic\System\Setup;

use PDO;
use PDOException;
use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\AuditKind;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Config\Config;
use Systopic\System\Config\Instance;
use Systopic\System\Config\NotConfigured;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

/**
 * The setup page — what a fresh installation shows instead of an error.
 *
 * index.php hands over in two cases:
 *
 *   1. Config::boot() threw NotConfigured: no config.json, no instance, none
 *      matching, or a local request that would land on the LIVE default.
 *      Asked: project name, namespace, stage, machine. Written:
 *      config/config.json (when missing) and config/<STAGE>[.<machine>].config.json
 *      with a rule made from this very request — measured, not guessed.
 *   2. The instance has no database: host, user, password, name. Without a
 *      name the server's databases are listed; a name that does not exist
 *      can be created. Written into the instance file or config.local.json.
 *   3. That database has no account (var/setup.account, left by step 2):
 *      the first one, a superuser — or skip it to restore a backup in the
 *      cms's recovery mode instead.
 *
 * Who may use it: a local request (an IP / localhost / *.local / *.test host
 * and a private client address) — or anyone holding the token that
 * bin/scaffold.php leaves in var/setup.token and prints with the composer
 * output. A page that writes config must not serve the first visitor of a
 * fresh public install.
 *
 * Plain HTML, no panels, no database, no system bootstrap: nothing of that
 * works before the config exists.
 */
final class Setup
{
    public const TOKEN_FILE = 'var/setup.token';

    /** left by the database step when the database has no account yet */
    public const ACCOUNT_FILE = 'var/setup.account';

    public const STAGES = ['DEV', 'STAGING', 'RC', 'LIVE'];

    /** databases nobody installs a project into */
    private const SYSTEM_DATABASES = ['information_schema', 'mysql', 'performance_schema', 'sys'];

    /** @var list<string> */
    private array $errors = [];

    private function __construct(
        private readonly string $root,
        private readonly array $server,
        private readonly ?NotConfigured $reason,
    ) {}

    /** does the selected instance name a database? */
    public static function hasDatabase(Config $config): bool
    {
        $db = $config->instance->get('db', []);
        return is_array($db) && trim((string) ($db['name'] ?? '')) !== '';
    }

    /**
     * Is the first account still to be made? A file check, nothing more —
     * it runs on every request.
     */
    public static function accountPending(string $projectRoot): bool
    {
        return is_file($projectRoot . '/' . self::ACCOUNT_FILE);
    }

    /** answers the request and ends it */
    public static function run(string $projectRoot, array $server, ?NotConfigured $reason): never
    {
        $setup = new self(rtrim(str_replace('\\', '/', $projectRoot), '/'), $server, $reason);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, ($reason?->getMessage() ?? 'config: the instance has no database')
                . "\nopen the project's public/ in a browser to set it up\n");
            exit(1);
        }
        $setup->handle();
        exit;
    }

    // -------------------------------------------------------------------------------------
    // Request
    // -------------------------------------------------------------------------------------

    private function handle(): void
    {
        header('Cache-Control: no-store');
        if (!$this->allowed()) {
            http_response_code(403);
            $this->page('Not set up', '<p>This installation has no configuration yet.</p>'
                . '<p>Open it from a local address, or with the link <code>composer install</code> / '
                . '<code>composer update</code> printed (<code>?setup=…</code>).</p>');
            return;
        }
        $config = Config::current();
        $step = match (true) {
            $this->reason !== null                              => 'project',
            $config === null || !self::hasDatabase($config)     => 'database',
            default                                             => 'account',
        };
        if (($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $done = match ($step) {
                'project'  => $this->saveProject(),
                'database' => $this->saveDatabase(),
                'account'  => $this->saveAccount(),
            };
            if ($done) {
                $this->redirect($step);
                return;
            }
        }
        match ($step) {
            'project'  => $this->projectForm(),
            'database' => $this->databaseForm(),
            'account'  => $this->accountForm(),
        };
    }

    /** the setup is complete: the link is spent */
    private function finish(): void
    {
        @unlink($this->root . '/' . self::TOKEN_FILE);
        @unlink($this->root . '/' . self::ACCOUNT_FILE);
    }

    private function allowed(): bool
    {
        return self::trusts($this->root, $this->server, (string) ($_POST['setup'] ?? $_GET['setup'] ?? ''));
    }

    /**
     * May this request set the installation up — or, before its first
     * account, restore a backup as the recovery user (Auth\Session)? A local
     * request (an IP / localhost / *.local / *.test host and a private
     * client address), or one that brings the token of var/setup.token.
     */
    public static function trusts(string $projectRoot, array $server, string $given): bool
    {
        $token = self::tokenIn($projectRoot);
        if ($token !== null && $given !== '' && hash_equals($token, $given)) {
            return true;
        }
        $client = (string) ($server['REMOTE_ADDR'] ?? '');
        return Instance::isLocalHost($server) && self::isPrivateIp($client);
    }

    /** the backup is in: the link the setup left for the recovery user is spent */
    public static function spendToken(string $projectRoot): void
    {
        @unlink(rtrim(str_replace('\\', '/', $projectRoot), '/') . '/' . self::TOKEN_FILE);
    }

    private function token(): ?string
    {
        return self::tokenIn($this->root);
    }

    private static function tokenIn(string $projectRoot): ?string
    {
        $file = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/' . self::TOKEN_FILE;
        $token = is_file($file) ? trim((string) file_get_contents($file)) : '';
        return $token === '' ? null : $token;
    }

    private static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        // loopback is neither private nor reserved for filter_var - ask separately
        if ($ip === '::1' || str_starts_with($ip, '127.')) {
            return true;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }

    /**
     * Another step to go: the same url, with the token it came with. Done:
     * the site — or, after the first account, the cms to log in to.
     */
    private function redirect(string $step): void
    {
        $root = $this->urlRoot() . '/';
        $more = $step === 'project' || ($step === 'database' && self::accountPending($this->root));
        $token = $this->token();
        $withToken = $token !== null && isset($_POST['setup']);
        $url = match (true) {
            $more && $withToken                                 => $root . '?setup=' . rawurlencode($token),
            $more                                               => $root,
            // skipped: on to the recovery user, who needs the token as well
            $step === 'account' && $withToken && !empty($_POST['skip']) => $root . 'cms/?setup=' . rawurlencode($token),
            $step === 'account'                                 => $root . 'cms/',
            default                                             => $root,
        };
        header('Location: ' . $url, true, 303);
    }

    // -------------------------------------------------------------------------------------
    // What the request says about this installation
    // -------------------------------------------------------------------------------------

    private function host(): string
    {
        $host = strtolower(trim((string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? '')));
        return preg_replace('~:\d+$~', '', $host) ?? $host;
    }

    /** the url root as the browser sees it, without trailing slash: /projects/smp.de/public */
    private function urlRoot(): string
    {
        $script = (string) ($this->server['SCRIPT_NAME'] ?? '/index.php');
        $root = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $root === '.' ? '' : $root;
    }

    /** the project's url path - the url root without public/: /projects/smp.de/ */
    private function projectPath(): string
    {
        return (string) preg_replace('~/public$~', '', $this->urlRoot()) . '/';
    }

    /** the folder name - a string operation, open_basedir does not get in the way */
    private function projectName(): string
    {
        return basename($this->root);
    }

    /** smp.de → Smp, spaceticker.app → Spaceticker, my-site.com → MySite */
    public static function namespaceFor(string $name): string
    {
        $first = explode('.', $name)[0];
        $parts = preg_split('~[^A-Za-z0-9]+~', $first) ?: [];
        $ns = implode('', array_map(fn($part) => ucfirst(strtolower($part)), $parts));
        return preg_match('~^[A-Za-z]~', $ns) ? $ns : 'App' . $ns;
    }

    /** DEV for a local host, STAGING / RC by the first label, LIVE otherwise */
    private function guessStage(): string
    {
        $host = $this->host();
        return match (true) {
            Instance::isLocalHost($this->server) => 'DEV',
            str_starts_with($host, 'staging.')   => 'STAGING',
            str_starts_with($host, 'rc.')        => 'RC',
            default                              => 'LIVE',
        };
    }

    /**
     * The machine's name, as far as this box already knows it:
     *   1. another project here has a DEV instance with a rule on our ip
     *   2. the project manager next door (packages/tools/projects/config/)
     *      has a DEV server whose address is our host
     *   3. the host name of the computer
     */
    private function guessMachine(): string
    {
        $ips = $this->facts()['serverIp'] ?? [];
        foreach (glob(dirname($this->root) . '/*/config/DEV.*.config.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            foreach ((array) ($data['matches'] ?? []) as $rule) {
                foreach ((array) ($rule['serverIp'] ?? []) as $ip) {
                    if (in_array($ip, $ips, true)) {
                        return substr(basename($file, '.config.json'), 4);
                    }
                }
            }
        }
        $host = $this->host();
        foreach (glob(dirname($this->root, 2) . '/packages/tools/projects/config/DEV.*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if ($host !== '' && strtolower((string) parse_url((string) ($data['address'] ?? ''), PHP_URL_HOST)) === $host) {
                return substr(basename($file, '.json'), 4);
            }
        }
        $name = strtolower((string) preg_replace('~[^A-Za-z0-9-]+~', '-', (string) gethostname()));
        return trim($name, '-') ?: 'thismachine';
    }

    private function facts(): array
    {
        return $this->reason?->facts ?: Instance::requestFacts($this->server, $this->root);
    }

    // -------------------------------------------------------------------------------------
    // Step 1: project and instance
    // -------------------------------------------------------------------------------------

    private function configFile(string $name): string
    {
        return $this->root . '/config/' . $name;
    }

    /** the namespace config.json already has, NULL without one */
    private function existingNamespace(): ?string
    {
        $data = self::readJson($this->configFile('config.json')) ?? [];
        $ns = $data['constants']['PROJECT_NAMESPACE'] ?? null;
        return is_string($ns) && $ns !== '' ? $ns : null;
    }

    private function saveProject(): bool
    {
        $namespace = trim((string) ($_POST['namespace'] ?? ''));
        $stage     = strtoupper(trim((string) ($_POST['stage'] ?? '')));
        $machine   = strtolower(trim((string) ($_POST['machine'] ?? '')));

        if ($this->existingNamespace() === null && !preg_match('~^[A-Z][A-Za-z0-9_]*(\\\\[A-Z][A-Za-z0-9_]*)*$~', $namespace)) {
            $this->errors[] = 'namespace: letters and digits, starting with a capital letter';
        }
        if (!in_array($stage, self::STAGES, true)) {
            $this->errors[] = 'stage: one of ' . implode(', ', self::STAGES);
        }
        if ($machine !== '' && !preg_match('~^[a-z0-9][a-z0-9-]*$~', $machine)) {
            $this->errors[] = 'machine: a-z, 0-9 and -';
        }
        if ($stage === 'LIVE' && Instance::isLocalHost($this->server)) {
            $this->errors[] = 'LIVE from a local address would be refused on the next request — choose DEV';
        }
        if ($this->errors) {
            return false;
        }

        if (!is_dir($this->root . '/config') && !mkdir($this->root . '/config', 0775, true)) {
            $this->errors[] = 'cannot create ' . $this->root . '/config/';
            return false;
        }
        if (!is_file($this->configFile('config.json'))) {
            $ok = self::writeJson($this->configFile('config.json'), [
                '//'        => 'Nothing installation specific. Instances: <stage>[.<machine>].config.json, this machine: config.local.json. See SYS/.cursor/docs/config.md',
                'constants' => [
                    'DEFAULT_TABLE_PREFIX' => 'cms',
                    'PROJECT_NAMESPACE'    => $namespace,
                ],
            ]);
            if (!$ok) {
                $this->errors[] = 'cannot write config/config.json';
                return false;
            }
        }

        $instance = $stage . ($machine === '' ? '' : '.' . $machine);
        $file     = $this->configFile($instance . '.config.json');
        $data     = [];
        if (is_file($file)) {
            $data = self::readJson($file);
            if ($data === null) {
                $this->errors[] = "config/$instance.config.json exists but is no plain JSON (comments?) — add the rule by hand";
                return false;
            }
        }
        if ($stage === 'LIVE') {
            $data['default'] = true;
        } else {
            $rule = $this->rule();
            $rules = array_values(array_filter((array) ($data['matches'] ?? []), 'is_array'));
            if (!in_array($rule, $rules, true)) {
                $rules[] = $rule;
            }
            $data['matches'] = $rules;
        }
        if ($stage === 'DEV') {
            $data += [
                'debug'           => ['debug' => true, 'render' => true, 'time' => true, 'displayErrors' => true],
                'versionAppendix' => 'time',
            ];
        }
        if (!self::writeJson($file, $data)) {
            $this->errors[] = "cannot write config/$instance.config.json";
            return false;
        }
        return true;
    }

    /**
     * The rule that recognises this very request: the host (as IP or name)
     * and the url path of the project, when it is not the document root.
     */
    private function rule(): array
    {
        $host = $this->host();
        $rule = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? ['serverIp' => $host]
            : ['serverName' => $host];
        $path = $this->projectPath();
        if ($path !== '/') {
            $rule['path'] = $path;
        }
        return $rule;
    }

    private function projectForm(): void
    {
        $existingNs = $this->existingNamespace();
        $name       = (string) ($_POST['name'] ?? $this->projectName());
        $namespace  = (string) ($_POST['namespace'] ?? $existingNs ?? self::namespaceFor($name));
        $stage      = (string) ($_POST['stage'] ?? $this->guessStage());
        $machine    = (string) ($_POST['machine'] ?? ($stage === 'DEV' ? $this->guessMachine() : ''));

        $why = match ($this->reason?->reason) {
            NotConfigured::NO_CONFIG     => 'config/ has no config.json yet.',
            NotConfigured::NO_INSTANCES  => 'config/ describes no installation yet.',
            NotConfigured::LOCAL_DEFAULT => 'No installation in config/ matches this machine. The only candidate is the '
                . 'default <b>' . self::e((string) $this->reason?->refusedDefault) . '</b> — that is LIVE, with its '
                . 'database, and a local address does not get it.',
            default                      => 'No installation in config/ matches this request.',
        };
        $options = '';
        foreach (self::STAGES as $each) {
            $options .= '<option' . ($each === $stage ? ' selected' : '') . '>' . $each . '</option>';
        }
        $rule = json_encode($this->rule(), JSON_UNESCAPED_SLASHES);

        $this->page('Set up ' . $this->projectName(), <<<HTML
            <p>$why</p>
            {$this->errorList()}
            <form method="post">
                {$this->tokenField()}
                <label><span>project</span>
                    <input name="name" value="{$this->h($name)}" data-namespace-from></label>
                <label><span>namespace (PROJECT_NAMESPACE)</span>
                    <input name="namespace" value="{$this->h($namespace)}" {$this->readonly($existingNs !== null)}></label>
                <label><span>stage</span>
                    <select name="stage">$options</select></label>
                <label><span>machine — the instance becomes <code>STAGE.machine</code>; empty: just <code>STAGE</code></span>
                    <input name="machine" value="{$this->h($machine)}" placeholder="thismachine"></label>
                <p class="hint">recognised by <code>{$this->h((string) $rule)}</code> · project folder <code>{$this->h($this->root)}</code></p>
                <button>write config/</button>
            </form>
            <script>
                // the namespace follows the project name until it is edited
                (() => {
                    const name = document.querySelector('[data-namespace-from]');
                    const ns = document.querySelector('[name=namespace]');
                    if (ns.readOnly) return;
                    let touched = false;
                    ns.addEventListener('input', () => touched = true);
                    name.addEventListener('input', () => {
                        if (touched) return;
                        const first = name.value.split('.')[0].split(/[^A-Za-z0-9]+/).filter(Boolean);
                        ns.value = first.map(p => p[0].toUpperCase() + p.slice(1).toLowerCase()).join('');
                    });
                })();
            </script>
            HTML);
    }

    // -------------------------------------------------------------------------------------
    // Step 2: database
    // -------------------------------------------------------------------------------------

    /** @var list<string>|null the server's databases, when asked for */
    private ?array $databases = null;

    private function saveDatabase(): bool
    {
        $host   = trim((string) ($_POST['host'] ?? '')) ?: 'localhost';
        $user   = trim((string) ($_POST['user'] ?? ''));
        $pass   = (string) ($_POST['pass'] ?? '');
        $name   = trim((string) ($_POST['name'] ?? ''));
        $target = (string) ($_POST['target'] ?? 'instance');

        if ($user === '') {
            $this->errors[] = 'user is missing';
            return false;
        }
        if ($name !== '' && !preg_match('~^[A-Za-z0-9_.\-]{1,64}$~', $name)) {
            $this->errors[] = 'database name: letters, digits, _ . -';
            return false;
        }
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $all = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $this->errors[] = 'connection failed: ' . $e->getMessage();
            return false;
        }
        $this->databases = array_values(array_diff($all, self::SYSTEM_DATABASES));
        if ($name === '') {
            return false; // the form comes back with the list to choose from
        }
        if (!in_array($name, $all, true)) {
            if (empty($_POST['create'])) {
                $this->errors[] = "database '$name' does not exist — tick 'create' or choose one below";
                return false;
            }
            try {
                $pdo->exec('CREATE DATABASE `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (PDOException $e) {
                $this->errors[] = "cannot create '$name': " . $e->getMessage();
                return false;
            }
        }

        $db = ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass];
        $instance = Config::current()?->instance->name;
        $file = $target === 'local' || $instance === null
            ? $this->configFile('config.local.json')
            : $this->configFile($instance . '.config.json');
        $data = is_file($file) ? self::readJson($file) : [];
        if ($data === null) {
            $this->errors[] = basename($file) . ' is no plain JSON (comments?) — add the db block by hand';
            return false;
        }
        $data['db'] = $db;
        if (!self::writeJson($file, $data)) {
            $this->errors[] = 'cannot write config/' . basename($file);
            return false;
        }
        // a database without an account: nobody could log in - one more step
        if ($this->hasAccounts($pdo, $name)) {
            $this->finish();
        } else {
            @mkdir(dirname($this->root . '/' . self::ACCOUNT_FILE), 0775, true);
            file_put_contents($this->root . '/' . self::ACCOUNT_FILE, date('c'));
        }
        return true;
    }

    /** is there a users table with at least one row? */
    private function hasAccounts(PDO $pdo, string $database): bool
    {
        try {
            $table = Schema::tableNameOf(User::class);
            $count = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $database) . '`.`' . $table . '`')->fetchColumn();
            return (int) $count > 0;
        } catch (PDOException) {
            return false; // no table yet
        }
    }

    // -------------------------------------------------------------------------------------
    // Step 3: the first account
    // -------------------------------------------------------------------------------------

    /**
     * The first account of the installation, a superuser — what cms/auth/root
     * does, but that panel cannot be reached here: without a users table the
     * cms runs in recovery mode and offers only to restore a backup. Which is
     * the other way out, hence "skip". The token stays for it: the recovery
     * user is whoever may set up (trusts()), the restore spends it.
     */
    private function saveAccount(): bool
    {
        if (!empty($_POST['skip'])) {
            @unlink($this->root . '/' . self::ACCOUNT_FILE);
            return true;
        }
        $name     = trim((string) ($_POST['name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $repeat   = (string) ($_POST['password1'] ?? '');
        if ($name === '') {
            $this->errors[] = 'name is missing';
            return false;
        }
        $this->errors = UserOperator::passwordProblems($password, $repeat, Config::current()?->instance->stage === 'LIVE');
        if ($this->errors) {
            return false;
        }
        try {
            $connection = Connection::open((array) Config::current()?->instance->get('db', []));
            $scope = Scope::default();
            $this->createTables($connection);
            if ($this->errors) {
                return false;
            }
            // the lookup provisions what is still missing; then: is it still empty?
            UserLookup::byName($scope, $name);
            $table = Schema::tableNameOf(User::class);
            $count = (int) $scope->connection->value('SELECT COUNT(*) FROM ' . $table);
            if ($count > 0) {
                $this->finish();
                $this->errors[] = 'there is an account already — log in with it';
                return false;
            }
            // user 1 is the system's own row - the login never finds it
            // (UserLookup.sql), the legacy record wrote it as its default.
            // Taken first, so the account gets 2 and can log in.
            $scope->connection->run("INSERT INTO $table (user_id, user_is_active) VALUES (1, 0)");
            $scope->save(UserOperator::firstSuperuser($name, $password));
        } catch (\Throwable $e) {
            $this->errors[] = 'cannot create the account: ' . $e->getMessage();
            return false;
        }
        $this->finish();
        return true;
    }

    /**
     * Every table the packages and the project declare, created now. The
     * provisioner only creates a table when a query that brings it along
     * fails on it — and many queries do not bring one (cms/pages fails on
     * usm_roles). A fresh database gets the whole schema at once instead:
     * the lossless half of Audit (create, add, index), never a drop.
     */
    private function createTables(Connection $connection): void
    {
        $audit = new Audit($connection);
        $lossless = [AuditKind::CreateTable, AuditKind::AddColumn, AuditKind::AddConstraint, AuditKind::CreateIndex];
        $actions = array_values(array_filter($audit->run(), fn($action) => in_array($action->kind, $lossless, true)));
        $audit->apply($actions, withoutForeignKeyChecks: true);
        foreach ($audit->failures() as $failure) {
            $this->errors[] = $failure['action']->subject . ': ' . $failure['message'];
        }
    }

    private function accountForm(): void
    {
        $name = (string) ($_POST['name'] ?? '');
        $this->page('First account for ' . $this->projectName(), <<<HTML
            <p>The database has no account yet. This one becomes the superuser — log in with it at
                <code>cms/</code> and create the first page in <code>cms/pages</code>.</p>
            {$this->errorList()}
            <form method="post">
                {$this->tokenField()}
                <label><span>name</span><input name="name" value="{$this->h($name)}" autocomplete="username"></label>
                <label><span>password</span><input name="password" type="password" autocomplete="new-password"></label>
                <label><span>password again</span><input name="password1" type="password" autocomplete="new-password"></label>
                <button>create account</button>
                <button name="skip" value="1" class="secondary">skip — I restore a backup instead</button>
            </form>
            HTML);
    }

    private function databaseForm(): void
    {
        $instance = Config::current()?->instance->name ?? '?';
        $host     = (string) ($_POST['host'] ?? 'localhost');
        $user     = (string) ($_POST['user'] ?? '');
        $pass     = (string) ($_POST['pass'] ?? '');
        $name     = (string) ($_POST['name'] ?? $this->projectName());
        $target   = (string) ($_POST['target'] ?? (str_starts_with($instance, 'DEV') ? 'instance' : 'local'));

        $list = '';
        if ($this->databases !== null) {
            $items = '';
            foreach ($this->databases as $each) {
                $items .= '<label class="choice"><input type="radio" name="name" value="' . $this->h($each) . '"'
                    . ($each === $name ? ' checked' : '') . '> ' . $this->h($each) . '</label>';
            }
            $list = '<fieldset><legend>databases on ' . $this->h($host) . '</legend>'
                . ($items ?: '<p class="hint">none yet</p>') . '</fieldset>';
        }
        $local    = $target === 'local' ? ' checked' : '';
        $inFile   = $target === 'local' ? '' : ' checked';

        $this->page('Database for ' . $this->projectName(), <<<HTML
            <p>Installation <b>{$this->h($instance)}</b> has no database yet. Tables are created on first use.</p>
            {$this->errorList()}
            <form method="post">
                {$this->tokenField()}
                <label><span>host</span><input name="host" value="{$this->h($host)}"></label>
                <label><span>user</span><input name="user" value="{$this->h($user)}" autocomplete="off"></label>
                <label><span>password</span><input name="pass" type="password" value="{$this->h($pass)}" autocomplete="new-password"></label>
                <label><span>database — empty: list the databases of this server</span>
                    <input name="name" value="{$this->h($name)}"></label>
                $list
                <label class="choice"><input type="checkbox" name="create" value="1"> create it if it does not exist</label>
                <fieldset><legend>write the access to</legend>
                    <label class="choice"><input type="radio" name="target" value="instance"$inFile>
                        config/{$this->h($instance)}.config.json — synced with the project</label>
                    <label class="choice"><input type="radio" name="target" value="local"$local>
                        config/config.local.json — this machine only, never synced (LIVE passwords)</label>
                </fieldset>
                <button>connect</button>
            </form>
            HTML);
    }

    // -------------------------------------------------------------------------------------
    // Output and files
    // -------------------------------------------------------------------------------------

    private function page(string $title, string $body): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $title = $this->h($title);
        echo <<<HTML
            <!DOCTYPE html>
            <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">
            <title>$title</title>
            <style>
                body { margin: 0; font: 15px/1.45 system-ui, sans-serif; background: #111; color: #ddd; }
                main { max-width: 640px; margin: 48px auto; padding: 0 16px; }
                h1 { font-size: 18px; color: orange; letter-spacing: .04em; }
                label { display: block; margin: 0 0 14px; }
                label > span { display: block; color: #999; font-size: 13px; margin-bottom: 4px; }
                input, select { font: inherit; width: 100%; box-sizing: border-box; padding: 6px 8px; background: #000; color: #fff; border: 1px solid #444; }
                input[readonly] { color: #999; }
                label.choice { display: flex; gap: 8px; align-items: baseline; margin-bottom: 6px; }
                label.choice input { width: auto; }
                fieldset { border: 1px solid #333; margin: 0 0 14px; padding: 10px 14px; }
                legend { color: #999; font-size: 13px; }
                button { font: inherit; padding: 7px 16px; background: #222; color: orange; border: 1px solid orange; cursor: pointer; }
                button.secondary { color: #999; border-color: #444; margin-left: 8px; }
                code { color: orange; }
                .hint { color: #888; font-size: 13px; }
                .errors { color: #ff6b5b; padding-left: 18px; }
            </style></head>
            <body><main><h1>$title</h1>$body</main></body></html>
            HTML;
    }

    private function errorList(): string
    {
        if ($this->errors === []) {
            return '';
        }
        return '<ul class="errors"><li>' . implode('</li><li>', array_map([$this, 'h'], $this->errors)) . '</li></ul>';
    }

    private function tokenField(): string
    {
        $given = (string) ($_POST['setup'] ?? $_GET['setup'] ?? '');
        return $given === '' ? '' : '<input type="hidden" name="setup" value="' . $this->h($given) . '">';
    }

    private function readonly(bool $readonly): string
    {
        return $readonly ? 'readonly' : '';
    }

    private function h(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /** plain JSON only - NULL for a file that does not parse (JSONC comments) */
    private static function readJson(string $file): ?array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode(ltrim((string) file_get_contents($file), "\xEF\xBB\xBF"), true);
        return is_array($data) ? $data : null;
    }

    /** tabs, like the config files written by hand and by InstanceFiles */
    private static function writeJson(string $file, array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $json = (string) preg_replace_callback('~^(?: {4})+~m', fn($m) => str_repeat("\t", intdiv(strlen($m[0]), 4)), $json);
        return file_put_contents($file, $json . "\n") !== false;
    }
}
