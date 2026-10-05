<?php

declare(strict_types=1);

namespace Systopic\System\Config;

use RuntimeException;

/**
 * The `config/` folder of a project — successor of the SERVER_NAME switch
 * that every `public/index.php` used to carry.
 *
 * Three kinds of file:
 *
 *   config.json               nothing installation-specific: project
 *                             constants, and the sites (root node, meta) —
 *                             shared, synced
 *   <stage>[.<machine>].config.json
 *                             one installation — DEV.ojoffice, STAGING, RC,
 *                             LIVE. The file name IS the instance name, the
 *                             part before the first dot its stage. Holds
 *                             its rules (`matches`: machine and site, see
 *                             Instance), db, debug. Synced; site add/rename/
 *                             remove rewrite the rules (InstanceFiles).
 *   config.local.json         this machine only, never synced (`*.local.json`
 *                             is in the sync ignore list) — mainly the LIVE
 *                             credentials. Flat: it speaks for the instance
 *                             that was recognised.
 *
 * Instance names follow the `stage` field of `.sync` (DEV.ojoffice, STAGING,
 * LIVE), so the sync tool and the config speak of the same thing.
 *
 * Two resolutions happen at different times, because they need different
 * input:
 *
 *   boot()         picks the INSTANCE from $_SERVER and the project dir, and
 *                  can define the constants the rest of the system reads
 *                  (STAGE, DB_*, DEBUG …). Runs first thing in index.php.
 *   resolveSite()  picks the SITE from host + root path, once http::setRoot()
 *                  knows them (sys::init).
 */
final class Config
{
    private static ?self $current = null;

    /** @var array<string, mixed> merged content of all files */
    public readonly array $data;

    /** @var list<string> the files that were read, in merge order */
    public readonly array $files;

    public readonly Instance $instance;
    /**
     * config.json's `sites` until the database is connected, then the rows of
     * cms_sites (Pages\Sites\SiteRegistry::boot() → useSites()).
     */
    public private(set) Sites $sites;

    private ?Site $site = null;

    /** The host entry the current site was matched by ('*' for the wildcard). */
    private string $siteEntry = '*';

    /**
     * The part of the current root path the matched entry does not cover,
     * e.g. 'public/' for entry '192.168.22.50/projects/systopic.net/' and
     * root '/projects/systopic.net/public/'. baseUrl() appends it to another
     * site's entry so the two addresses stay parallel.
     */
    private string $siteRemainder = '';

    /** @var array<string, string> what linkSites() did, link => outcome */
    private array $siteLinks = [];

    private function __construct(array $data, array $files, Instance $instance)
    {
        $this->data     = $data;
        $this->files    = $files;
        $this->instance = $instance;
        // what a site is: config.json (until the db is there); where it answers: the instance's rules
        $this->sites    = new Sites($data['sites'] ?? [], $instance->matches());
    }

    // =========================================================================
    // Boot
    // =========================================================================

    /**
     * Reads the folder and picks the instance.
     *
     * @param string      $configDir  <project>/config
     * @param array       $server     $_SERVER (or the CLI environment)
     * @param string      $projectDir the project root, for `match.rootDir`
     * @param string|null $pinned     instance name forced from outside
     *                                (CLI `"instance"`), wins over
     *                                config.local.json
     */
    public static function boot(string $configDir, array $server, string $projectDir, ?string $pinned = null): self
    {
        [$data, $files] = self::load($configDir);
        if ($files === []) {
            throw new RuntimeException("config: no config.json in '$configDir'");
        }
        $local = self::readLocal($configDir);
        if ($local !== []) {
            $files[] = rtrim(str_replace('\\', '/', $configDir), '/') . '/config.local.json';
        }

        // an empty "instance" means "not pinned"
        $pinned ??= is_string($local['instance'] ?? null) && $local['instance'] !== '' ? $local['instance'] : null;
        $instance = Instance::select($data['instances'] ?? [], $server, $projectDir, $pinned);

        // config.local.json lies on exactly one machine, so it speaks for the
        // instance that was just selected — flat, without an instance name.
        $instanceData = self::merge($instance->data, array_intersect_key($local, array_flip(self::LOCAL_INSTANCE_KEYS)));
        if ($instanceData !== $instance->data) {
            [$matchedBy, $match] = [$instance->matchedBy, $instance->match];
            $instance  = new Instance($instance->name, $instance->stage, $instanceData);
            [$instance->matchedBy, $instance->match] = [$matchedBy, $match];
        }
        foreach (['constants', 'ini'] as $key) {
            if (is_array($local[$key] ?? null)) {
                $data[$key] = self::merge($data[$key] ?? [], $local[$key]);
            }
        }

        return self::$current = new self($data, $files, $instance);
    }

    /**
     * Keys of config.local.json that belong to the selected instance — the
     * same keys an <Instance>.config.json has.
     */
    private const LOCAL_INSTANCE_KEYS = ['db', 'debug', 'versionAppendix', 'versionDate', 'assets'];

    /** @return array<string, mixed> config.local.json, [] when there is none */
    private static function readLocal(string $configDir): array
    {
        $file = rtrim(str_replace('\\', '/', $configDir), '/') . '/config.local.json';
        return is_file($file) ? self::readJson($file) : [];
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * The merged data of the shared files — config.json and every
     * <Instance>.config.json — without choosing anything, so a test can look
     * at them alone. config.local.json is not part of it: it speaks for the
     * selected instance and is applied in boot().
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    public static function load(string $configDir): array
    {
        $configDir = rtrim(str_replace('\\', '/', $configDir), '/');
        $files = [];
        $data  = [];

        $base = $configDir . '/config.json';
        if (is_file($base)) {
            $data    = self::readJson($base);
            $files[] = $base;
        }

        // <stage>[.<machine>].config.json → instances.<name>. The file name is
        // the instance; nothing in config.json has to repeat it. Sorted, so
        // the match order does not depend on the file system.
        $named = glob($configDir . '/*.config.json') ?: [];
        sort($named, SORT_STRING);
        foreach ($named as $file) {
            $name = basename($file, '.config.json');
            if ($name === 'config' || $name === '') {
                continue; // config.local.json is handled below
            }
            $data['instances'][$name] = self::merge(
                $data['instances'][$name] ?? [],
                self::readJson($file),
            );
            $files[] = $file;
        }

        return [$data, $files];
    }

    /** @return array<string, mixed> */
    private static function readJson(string $file): array
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

        $this->define('STAGE', $i->stage);
        // Spelled out as define('NAME') so the IDE knows these constants;
        // through $this->define() they would show as undefined everywhere.
        defined('INSTANCE') || define('INSTANCE', $i->name);
        defined('SYS_PATH') || define('SYS_PATH', $i->get('sysPath', $this->data['sysPath'] ?? 'vendor/systopic/system/'));

        $db = $i->db();
        $this->define('DB_HOST', $db['host'] ?? 'localhost');
        $this->define('DB_USER', $db['user'] ?? 'root');
        $this->define('DB_PASS', $db['pass'] ?? '');
        $this->define('DB_NAME', $db['name'] ?? '');
        // PHP 8.4+: the PDO parser skips '# :param' comments in the .sql files
        defined('DB_BIND_ONLY_USED_PARAMS') || define('DB_BIND_ONLY_USED_PARAMS', (bool) ($db['bindOnlyUsedParams'] ?? (PHP_VERSION_ID >= 80400)));

        $debug = $i->debug();
        $this->define('DEBUG', $debug['debug'] ?? false);
        $this->define('DEBUGRENDER', $debug['render'] ?? false);
        $this->define('DEBUGTIME', $debug['time'] ?? false);

        $versionDate = $i->get('versionDate');
        if (is_string($versionDate) && $versionDate !== '') {
            defined('PUBLIC_VERSIONDATE') || define('PUBLIC_VERSIONDATE', $versionDate);
        }
        $this->define('PUBLIC_VERSIONAPPENDIX', $this->versionAppendix());

        // Free-form constants: top level first, instance overrides.
        foreach (($this->data['constants'] ?? []) + [] as $name => $value) {
            $this->define((string) $name, $value);
        }
        foreach ($i->get('constants', []) as $name => $value) {
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
        $appendix = $this->instance->get('versionAppendix');
        if ($appendix === 'time') {
            return '?' . time();
        }
        if (is_string($appendix)) {
            return $appendix;
        }
        $versionDate = $this->instance->get('versionDate');
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
        $display = $this->instance->debug()['displayErrors'] ?? ($this->instance->stage === 'DEV');
        ini_set('display_errors', $display ? '1' : '0');

        foreach (($this->data['ini'] ?? []) as $key => $value) {
            ini_set((string) $key, (string) $value);
        }
        foreach ($this->instance->get('ini', []) as $key => $value) {
            ini_set((string) $key, (string) $value);
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
     * DEV convenience, opt-in per instance (`"linkSites": true`): creates the
     * missing directory links so every site answers under its path entry
     * (/projects/beautiful-technology.de/ → this project). See SiteLinks.
     *
     * @param string $documentRoot $_SERVER['DOCUMENT_ROOT']
     * @param string $projectDir   the project root
     * @return array<string, string> link => outcome, [] when nothing was missing
     */
    public function linkSites(string $documentRoot, string $projectDir): array
    {
        if (empty($this->instance->get('linkSites'))) {
            return [];
        }
        return $this->siteLinks = SiteLinks::ensure($this->sites, $documentRoot, $projectDir);
    }

    /**
     * Picks the site for host + root path, e.g. '192.168.22.50' and
     * '/projects/beautiful-technology.de/public/'. See Sites::resolve() for
     * the matching; the remainder is kept for baseUrl().
     */
    /**
     * Replaces the sites — name => root (site node id, NULL = default site),
     * default, meta — with those from the database. Where they answer still
     * comes from the instance.
     *
     * @param array<string, array<string, mixed>> $data
     */
    public function useSites(array $data): void
    {
        $this->sites = new Sites($data, $this->instance->matches());
        $this->site  = null;
    }

    /**
     * The site of the request: the one the matched rule names (a rule
     * without `site` is the default site's), else — an instance without
     * rules, LIVE — the site whose name or alias the host is.
     */
    public function resolveSite(string $host, string $rootPath): Site
    {
        $match = $this->instance->match;
        if ($match !== null) {
            $name = $this->instance->matchedSite();
            $site = $name === null ? $this->sites->default() : $this->sites->byName($name);
            if ($site !== null) {
                $entry = Sites::entryOf($match) ?? '*';
                $hit = new SiteHit($site, $entry, Sites::remainder($entry, rtrim('/' . ltrim(strtolower($rootPath), '/'), '/') . '/'));
            }
        }
        $hit ??= $this->sites->resolve($host, $rootPath);
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
            'matchedBy'     => $this->instance->matchedBy,
            'site'          => $this->site?->name,
            'siteEntry'     => $this->siteEntry,
            'siteRemainder' => $this->siteRemainder,
            'sites'         => array_keys($this->sites->all()),
            'siteLinks'     => $this->siteLinks,
        ];
    }
}
