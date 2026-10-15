<?php

declare(strict_types=1);

namespace Systopic\System\Dom;

use Systopic\System\Dom\Layer\DomLayer;
use Systopic\System\Dom\Tree\DomLayerTree;

/**
 * What a layer's output depends on — and what changed since the browser got it.
 *
 * The keys are opaque: the system neither makes nor reads them. A package adds
 * them while a layer renders (systopic/relay: one per table a result read,
 * hashed). Every layer whose reference depends on a changed key is rendered
 * again: its `keep` becomes an `update` (DomRenderer::addReference()), and the
 * diff renderer ships just those.
 *
 * What changed comes from three places, and they meet in ONE render:
 *
 *   - the browser: keys it heard of through the hub, on whatever request goes
 *     out next (header `X-Changed`, comma separated) — a click, a save, or a
 *     refresh of its own when nothing else is going out
 *   - a PUT body `changed` (the CLI harness)
 *   - this request: what its actions wrote, before the render (`wrote()`).
 *     So the window that saves gets everything its save touched in the answer
 *     to the save — not a second time when the hub's signal comes back.
 *
 * `updateView()` of a panel stays the explicit way; it ends up in the same
 * render. Without a package nothing is added, nothing is touched.
 */
final class Dependencies
{
    private static ?DomLayerTree $tree = null;

    /** @var array<string, true>|null what the request names as changed */
    private static ?array $named = null;

    /** @var array<string, true> what this request wrote */
    private static array $written = [];

    /** bootstrap.php: the tree whose open layer collects. */
    public static function attach(DomLayerTree $tree): void
    {
        self::$tree = $tree;
        self::$named = null;
        self::$written = [];
    }

    /** The layer being rendered now depends on these keys. Outside a render nothing happens. */
    public static function add(string ...$keys): void
    {
        $layer = self::$tree?->active();
        if ($layer === null) {
            return;
        }
        foreach ($keys as $key) {
            $layer->dependencies[$key] = true;
        }
    }

    /** This request changed what these keys stand for (a package, after its commit). */
    public static function wrote(string ...$keys): void
    {
        foreach ($keys as $key) {
            self::$written[$key] = true;
        }
    }

    /** @return list<string> everything that changed: named by the request, or written by it */
    public static function changed(): array
    {
        return array_map('strval', array_keys(self::named() + self::$written));
    }

    /** Does what the browser shows in this layer depend on something that changed? */
    public static function touched(DomLayer $reference): bool
    {
        if ($reference->dependencies === []) {
            return false;
        }
        return array_intersect_key($reference->dependencies, self::named() + self::$written) !== [];
    }

    /** Was something rendered in this request? A PUT that only ran an action renders nothing. */
    public static function rendered(): bool
    {
        return self::$tree?->root() !== null;
    }

    /**
     * Every key of the tree rendered in this request — what the browser has
     * to listen to.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $keys = [];
        $walk = static function (DomLayer $layer) use (&$walk, &$keys): void {
            $keys += $layer->dependencies;
            foreach ($layer->items as $item) {
                if ($item->isLayer()) {
                    $walk($item);
                }
            }
        };
        $root = self::$tree?->root();
        if ($root !== null) {
            $walk($root);
        }
        return array_map('strval', array_keys($keys));
    }

    /** @return array<string, true> the header of the browser, the body of a PUT */
    private static function named(): array
    {
        if (self::$named !== null) {
            return self::$named;
        }
        $keys = [];
        foreach (explode(',', (string) ($_SERVER['HTTP_X_CHANGED'] ?? '')) as $key) {
            $key = trim($key);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        $value = \http::put('changed');
        // a list from the browser's json; the CLI harness hands objects
        $value = is_object($value) ? get_object_vars($value) : $value;
        foreach (is_array($value) ? $value : [] as $key) {
            if (is_string($key) && $key !== '') {
                $keys[$key] = true;
            }
        }
        return self::$named = $keys;
    }
}
