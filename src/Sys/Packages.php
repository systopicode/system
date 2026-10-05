<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/**
 * What add-on packages contribute to the system — announced from their
 * Composer `autoload.files`, so it is known before the bootstrap runs.
 *
 * The system only reads these lists; it never names a package. A project
 * that does not install a package simply has no entry for it — that is how
 * systopic.net runs without systopic/system-legacy.
 */
final class Packages
{
    /** @var array<string, string> root name => lib/class base directory (native, with trailing /) */
    private static array $libs = [];

    /** @var list<array{dir: string, namespace: string}> */
    private static array $siteViews = [];

    /**
     * A directory with `lib/class/…` in the system's naming convention,
     * searched by the fsAutoloader after project and system. `$name` is the
     * root the client loads companion JS from (`settings.packageRoots`).
     */
    public static function addLib(string $name, string $dir): void
    {
        self::$libs[$name] = rtrim(str_replace('\\', '/', $dir), '/') . '/';
    }

    /** @return array<string, string> */
    public static function libs(): array
    {
        return self::$libs;
    }

    public static function libDir(string $name): ?string
    {
        return self::$libs[$name] ?? null;
    }

    /** Fallback page views (document, head …) — registered by loader.php before the project's. */
    public static function addSiteViews(string $dir, string $namespace): void
    {
        self::$siteViews[] = ['dir' => rtrim(str_replace('\\', '/', $dir), '/') . '/', 'namespace' => $namespace];
    }

    /** @return list<array{dir: string, namespace: string}> */
    public static function siteViews(): array
    {
        return self::$siteViews;
    }

    /** @var array<string, string> root name => namespace root of its panel classes */
    private static array $panels = [];

    /** @var list<Frontend> */
    private static array $frontends = [];

    /**
     * The package's `panels/` folder as a panel root — between the system's
     * and the project's, merged by name like those (a package can add
     * `cms/…` panels). `$name` must have been announced with `addLib()`.
     */
    public static function addPanels(string $name, string $namespace): void
    {
        self::$panels[$name] = $namespace;
    }

    /** @return list<array{name: string, dir: string, namespace: string}> */
    public static function panels(): array
    {
        $out = [];
        foreach (self::$panels as $name => $namespace) {
            if (isset(self::$libs[$name])) {
                $out[] = ['name' => $name, 'dir' => self::$libs[$name] . 'panels/', 'namespace' => $namespace];
            }
        }
        return $out;
    }

    public static function addFrontend(Frontend $frontend): void
    {
        self::$frontends[] = $frontend;
    }

    /** @return list<Frontend> */
    public static function frontends(): array
    {
        return self::$frontends;
    }

    /** The root name of the package a directory lies in — for the client's script and CSS URLs. */
    public static function rootOf(string $dir): ?string
    {
        $dir = strtolower(rtrim(str_replace('\\', '/', $dir), '/') . '/');
        $dir = preg_replace('~^/([a-z])/~', '$1:/', $dir);   // the fs spelling /C/… as C:/…
        foreach (self::$libs as $name => $root) {
            if (str_starts_with($dir, strtolower($root))) {
                return $name;
            }
        }
        return null;
    }

    /**
     * URL prefixes of the package roots, for the client's script loader. Only
     * where the package lies inside the document root (DEV, STAGING); RC and
     * LIVE need the publishing step that is still to come.
     *
     * @return array<string, string>
     */
    public static function urls(): array
    {
        $urls = [];
        foreach (self::$libs as $name => $dir) {
            $real = realpath($dir);
            $url = $real === false ? null : \http::urlPath($real);
            if ($url !== null) {
                $urls[$name] = $url;
            }
        }
        return $urls;
    }
}
