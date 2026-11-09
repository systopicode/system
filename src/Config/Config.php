<?php

declare(strict_types=1);

namespace Systopic\System\Config;

use RuntimeException;

/**
 * The `config/` folder of a project — successor of the SERVER_NAME switch
 * that every `public/index.php` used to carry.
 *
 * Two files:
 *
 *   config.json        the project: what is the same on every installation
 *                      (values marked `x-project` in the definitions), and
 *                      the sites before the database knows them. Git, sync.
 *   config.local.json  this installation: its name (`packages.system.instance`,
 *                      DEV.ojhome, STAGING, LIVE), how its sites are reached
 *                      (`packages.cms.siteAddress`), its databases, secrets. Lies in the
 *                      project folder of one installation and travels nowhere
 *                      (`*.local.json` is in the sync ignore list), so it
 *                      needs no rule to be found.
 *
 * Whatever neither file says comes from the definitions of the packages
 * (Schema). Two resolutions happen at different times:
 *
 *   boot()         reads both files and names the installation; can define
 *                  the constants the rest of the system reads (STAGE, DB_*,
 *                  DEBUG …). Runs first thing in index.php.
 *   resolveSite()  picks the SITE from host + root path, once http::setRoot()
 *                  knows them (sys::init).
 */
final class Config
{
    private static ?self $current = null;

    /** never from a config: they would get around the debug switch and the project's log */
    private const RESERVED_INI = ['error_log', 'display_errors', 'error_reporting'];

    /** the file of the installation */
    public const LOCAL = 'config.local.json';

    /** @var array<string, mixed> config.json, its free constants merged with those of config.local.json */
    public readonly array $data;

    /** @var list<string> the files that were read, in merge order */
    public readonly array $files;

    public readonly Instance $instance;

    /**
     * config.json's `sites` until the database is connected, then the rows of
     * cms_sites (Pages\Sites\SiteRegistry::boot() → useSites()).
     */
    public private(set) Sites $sites;

    /** @var array<string, array<string, mixed>> what $sites is made of */
    private array $siteData;

    /** host and root path of the request, once resolveSite() had them */
    private string $host = '';
    private string $rootPath = '';

    private ?Site $site = null;

    /** The host entry the current site was matched by ('*' for the wildcard). */
    private string $siteEntry = '*';

    /**
     * The part of the current root path the matched entry does not cover,
     * e.g. 'public/' for entry '/projects/systopic.net/' and root
     * '/projects/systopic.net/public/'. baseUrl() appends it to another
     * site's entry so the two addresses stay parallel.
     */
    private string $siteRemainder = '';

    /** @var array<string, array<string, mixed>> what the files say, package => tree */
    private array $packages;

    /** @var array<string, array<string, mixed>> the same with the defaults, as far as asked for */
    private array $packageTrees = [];

    private function __construct(array $data, array $files, Instance $instance, array $packages)
    {
        $this->packages = $packages;
        $this->data     = $data;
        $this->files    = $files;
        $this->instance = $instance;
        $this->siteData = is_array($data['sites'] ?? null) ? $data['sites'] : [];
        $this->sites    = $this->makeSites();
    }

    // =========================================================================
    // Boot
    // =========================================================================

    /**
     * Reads the folder and names the installation.
     *
     * @param string      $configDir  <project>/config
     * @param array       $server     $_SERVER (or the CLI environment), for the setup page
     * @param string      $projectDir the project root, for the setup page
     * @param string|null $pinned     an installation name forced from outside
     *                                (CLI `"instance"`, a test): the stage it
     *                                runs as, over config.local.json
     */
    public static function boot(string $configDir, array $server, string $projectDir, ?string $pinned = null): self
    {
        $configDir = rtrim(str_replace('\\', '/', $configDir), '/');
        $projectFile = $configDir . '/config.json';
        if (!is_file($projectFile)) {
            throw new NotConfigured("config: no config.json in '$configDir'", NotConfigured::NO_CONFIG, MachineRules::requestFacts($server, $projectDir));
        }
        $data = self::readJson($projectFile);
        $files = [$projectFile];
        $localFile = $configDir . '/' . self::LOCAL;
        $local = is_file($localFile) ? self::readJson($localFile) : [];
        if ($local !== []) {
            $files[] = $localFile;
        }

        $packages = self::merge(Layout::packages($data), Layout::packages($local));
        $name = $pinned ?? (is_string($packages['system']['instance'] ?? null) ? trim($packages['system']['instance']) : '');
        if ($name === '') {
            throw new NotConfigured(
                "config: no config.local.json in '$configDir' that names this installation (packages.system.instance)",
                NotConfigured::NO_INSTANCE,
                MachineRules::requestFacts($server, $projectDir),
            );
        }
        // the free constants: config.json, config.local.json over it
        if (is_array($local['constants'] ?? null)) {
            $data['constants'] = self::merge($data['constants'] ?? [], $local['constants']);
        }
        return self::$current = new self($data, $files, new Instance($name), $packages);
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * The settings of a package: defaults, config.json, config.local.json -
     * see Layout and Schema.
     *
     * @return array<string, mixed>
     */
    public function package(string $name): array
    {
        return $this->packageTrees[$name] ??= Schema::withDefaults($name, $this->packages[$name] ?? [], $this->instance->stage);
    }

    /**
     * A database connection by its name. `main` is the project's own.
     *
     * @return array<string, mixed> driver, host, port, name, user, pass, charset
     */
    public function connection(string $name = 'main'): array
    {
        $connection = $this->package('db')['connections'][$name] ?? null;
        return is_array($connection) ? $connection : [];
    }

    /** @return array<string, mixed> */
    public static function readJson(string $file): array
    {
        $raw = (string) file_get_contents($file);
        // a BOM from a Windows editor would make json_decode fail
        $raw = ltrim($raw, "\xEF\xBB\xBF");
        // JSONC: // and /* */ comments are allowed, so a config can explain itself
        $raw = self::stripComments($raw);
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("config: '$file' is not valid JSON: " . $e->getMessage(), 0, $e);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException("config: '$file' must hold a JSON object");
        }
        return $decoded;
    }

    /**
     * Removes // line and /* block *\/ comments outside of strings. A URL in a
     * value ("https://…") is a string and stays whole.
     */
    public static function stripComments(string $json): string
    {
        $out = '';
        $len = strlen($json);
        $inString = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $json[$i];
            if ($inString) {
                $out .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $out .= $json[++$i];
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '"') {
                $inString = true;
                $out .= $c;
                continue;
            }
            if ($c === '/' && ($json[$i + 1] ?? '') === '/') {
                while ($i < $len && $json[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }
            if ($c === '/' && ($json[$i + 1] ?? '') === '*') {
                $close = strpos($json, '*/', $i + 2);
                $i = $close === false ? $len : $close + 1;
                continue;
            }
            $out .= $c;
        }
        return $out;
    }

    /**
     * Deep merge: objects (string keys) recurse, everything else — including
     * lists — is replaced. A list in config.local.json therefore REPLACES the
     * list from config.json, it does not append to it; that is what makes an
     * override an override.
     */
    public static function merge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])
                && !array_is_list($value) && !array_is_list($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    // =========================================================================
    // Constants and ini — what the SERVER_NAME switch used to define
    // =========================================================================

    /**
     * Defines the constants the system reads, from the instance. Every define
     * is guarded, so something defined earlier (a CLI harness, a test) wins.
     */
    public function defineConstants(): void
    {
        $i = $this->instance;
        $system = $this->package('system');

        $this->define('STAGE', $i->stage);
        // Spelled out as define('NAME') so the IDE knows these constants;
        // through $this->define() they would show as undefined everywhere.
        defined('INSTANCE') || define('INSTANCE', $i->name);
        defined('SYS_PATH') || define('SYS_PATH', $system['sysPath'] ?? 'vendor/systopic/system/');

        $db = $this->connection();
        $this->define('DB_HOST', $db['host'] ?? 'localhost');
        $this->define('DB_USER', $db['user'] ?? 'root');
        $this->define('DB_PASS', $db['pass'] ?? '');
        $this->define('DB_NAME', $db['name'] ?? '');
        // PHP 8.4+: the PDO parser skips '# :param' comments in the .sql files
        defined('DB_BIND_ONLY_USED_PARAMS') || define('DB_BIND_ONLY_USED_PARAMS', (bool) ($this->package('db')['bindOnlyUsedParams'] ?? (PHP_VERSION_ID >= 80400)));

        $debug = $system['debug'] ?? [];
        $this->define('DEBUG', $debug['debug'] ?? false);
        $this->define('DEBUGRENDER', $debug['render'] ?? false);
        $this->define('DEBUGTIME', $debug['time'] ?? false);

        $versionDate = $system['versionDate'] ?? null;
        if (is_string($versionDate) && $versionDate !== '') {
            defined('PUBLIC_VERSIONDATE') || define('PUBLIC_VERSIONDATE', $versionDate);
        }
        $this->define('PUBLIC_VERSIONAPPENDIX', $this->versionAppendix());

        // the free ones of the project, and those of the system over them: both
        // are merged already, what is later in the files has won
        foreach (array_merge($this->data['constants'] ?? [], $system['constants'] ?? []) as $name => $value) {
            $this->define((string) $name, $value);
        }

        $this->applyIni();
    }

    /**
     * '?' . time() while developing, '?<versionDate>' when released, a literal
     * otherwise. The legacy switch spelled the same three cases by hand.
     */
    private function versionAppendix(): string
    {
        $appendix = $this->package('system')['versionAppendix'] ?? null;
        if ($appendix === 'time') {
            return '?' . time();
        }
        if (is_string($appendix)) {
            return $appendix;
        }
        $versionDate = $this->package('system')['versionDate'] ?? null;
        if (is_string($versionDate) && $versionDate !== '') {
            return '?' . $versionDate;
        }
        return '';
    }

    private function applyIni(): void
    {
        // error_reporting is -1 everywhere; whether errors are SHOWN is the
        // instance's business.
        ini_set('error_reporting', '-1');
        $system = $this->package('system');
        $display = $system['debug']['displayErrors'] ?? ($this->instance->stage === 'DEV');
        ini_set('display_errors', $display ? '1' : '0');

        // what the files say: the defaults were set before there was a config
        self::setIni($this->packages['system']['ini'] ?? []);
    }

    /**
     * The defaults of the definitions. Before Config::boot(): the setup page
     * runs when there is no config yet.
     */
    public static function applyIniDefaults(): void
    {
        self::setIni(Schema::withDefaults('system', [], '')['ini'] ?? []);
    }

    /** @param array<string, mixed> $values */
    private static function setIni(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_scalar($value) && !in_array((string) $key, self::RESERVED_INI, true)) {
                ini_set((string) $key, (string) $value);
            }
        }
    }

    private function define(string $name, mixed $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    // =========================================================================
    // Site
    // =========================================================================

    /**
     * Replaces the sites — name => root (site node id, NULL = default site),
     * default, meta, aliases — with those from the database. Where they
     * answer still comes from the installation.
     *
     * @param array<string, array<string, mixed>> $data
     */
    public function useSites(array $data): void
    {
        $this->siteData = $data;
        $this->sites = $this->makeSites();
        $this->site  = null;
    }

    /** the patterns of `packages.cms.siteAddress`, [] on LIVE */
    public function siteAddresses(): array
    {
        $address = $this->package('cms')['siteAddress'] ?? [];
        return array_values(array_filter(array_map('strval', (array) $address), static fn(string $a): bool => trim($a) !== ''));
    }

    private function makeSites(): Sites
    {
        return new Sites($this->siteData, $this->siteAddresses(), $this->host, $this->rootPath);
    }

    /**
     * Picks the site for host + root path, e.g. '192.168.22.50' and
     * '/projects/beautiful-technology.de/public/'. The addresses of the
     * sites are made for this request (Sites), the longest one that fits
     * wins; the remainder is kept for baseUrl().
     */
    public function resolveSite(string $host, string $rootPath): Site
    {
        $this->host = $host;
        $this->rootPath = $rootPath;
        $this->sites = $this->makeSites();
        $hit = $this->sites->resolve($host, $rootPath);
        $this->site          = $hit->site;
        $this->siteEntry     = $hit->entry;
        $this->siteRemainder = $hit->remainder;
        return $hit->site;
    }

    /** The current site — null before resolveSite() ran. */
    public function site(): ?Site
    {
        return $this->site;
    }

    /**
     * The absolute address root of a site on THIS instance, with trailing
     * slash — where `http::$root` would point if the request had come in for
     * that site.
     *
     *   same site                 → protocol://host + http root   (as today)
     *   entry 'beautiful-technology.de'   (LIVE)
     *                             → https://beautiful-technology.de/ + remainder
     *   entry '/projects/beautiful-technology.de/'   (DEV, path below any host)
     *                             → https://<current host>/projects/beautiful-technology.de/public/
     *
     * The remainder is what the current entry did not cover of the current
     * root ('public/' on a DEV path root, '' on a host root), so parallel
     * checkouts stay parallel. A site without an entry here (or with only
     * '*') cannot be addressed elsewhere and gets the current root.
     *
     * When the CURRENT site was matched by '*' nothing is known about how
     * much of the root is "site path": a host target then keeps the whole
     * current root (host swap), a path target gets no remainder. Give every
     * site of an instance an entry and this case never shows.
     */
    public function baseUrl(Site|string $target, string $protocol, string $host, string $rootPath): string
    {
        $site = $target instanceof Site ? $target : $this->sites->byName($target);
        $current = $protocol . '://' . $host . $rootPath;
        if ($site === null || $site === $this->site) {
            return $current;
        }
        return $this->entryUrl($site, $protocol, $host, $rootPath) ?? $current;
    }

    /**
     * The canonical address root of the CURRENT site — its first entry, not
     * the host the request came in on. Where a request on an alias domain is
     * sent (`SiteRegistry::redirectAlias()`). Null when the site has no
     * addressable entry here.
     */
    public function canonicalUrl(): ?string
    {
        return $this->site === null ? null : $this->entryUrl(
            $this->site,
            (string) (\http::$protocol ?: 'https'),
            (string) \http::$host,
            (string) \http::$root,
        );
    }

    /** The entry of the current site the request matched ('*' when none did). */
    public function siteEntry(): string
    {
        return $this->siteEntry;
    }

    /** baseUrl() of one site's first entry; null when it has none ('*'). */
    private function entryUrl(Site $site, string $protocol, string $host, string $rootPath): ?string
    {
        $entry = $site->firstEntry();
        if ($entry === null || $entry === '*') {
            return null;
        }
        // a path entry lives on the host the request came in on
        $base = str_starts_with($entry, '/') ? $host . $entry : $entry;
        if ($this->siteEntry === '*') {
            return str_contains($base, '/')
                ? $protocol . '://' . rtrim($base, '/') . '/'
                : $protocol . '://' . $base . $rootPath;
        }
        return $protocol . '://' . rtrim($base, '/') . '/' . $this->siteRemainder;
    }

    /**
     * baseUrl() with the current request's protocol, host and root — the form
     * the rendering side uses (links, href, redirects).
     */
    public function baseUrlFor(Site|string $target): string
    {
        return $this->baseUrl(
            $target,
            (string) (\http::$protocol ?: 'https'),
            (string) \http::$host,
            (string) \http::$root,
        );
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /** @return array<string, mixed> */
    public function debugInfo(): array
    {
        return [
            'files'         => $this->files,
            'instance'      => $this->instance->name,
            'stage'         => $this->instance->stage,
            'siteAddress'   => $this->siteAddresses(),
            'site'          => $this->site?->name,
            'siteEntry'     => $this->siteEntry,
            'siteRemainder' => $this->siteRemainder,
            'sites'         => array_keys($this->sites->all()),
        ];
    }
}
