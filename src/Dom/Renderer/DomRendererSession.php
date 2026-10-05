<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Renderer;

use Systopic\System\Dom\Layer\DomLayer;
use Systopic\System\Dom\Tree\DomLayerTree;

/**
 * Keeps a rendered layer tree across requests, so the next one can diff
 * against it. Shared by the panel and the page stack.
 *
 * FILED UNDER THE URL IT RENDERED. This used to be one slot per session,
 * which is not per window — two browser windows on the same session (the cms
 * in one, the public page in the other) overwrote each other's reference on
 * every request, and the diff was then computed against a tree the client
 * was not showing.
 *
 * A diff request therefore names the state it is showing, in its url:
 *
 *   /cms/pages/?id=20~/cms/pages/?id=18
 *   └ reference ────┘ └ target ───────┘
 *
 * http::splitDiffUrl() takes that apart; the left half arrives here as
 * http::$referenceUrl and is the key to look up. Two windows on different
 * urls — including different query strings — no longer collide, because the
 * url is what tells them apart.
 *
 * USED ONCE. An entry is removed as it is read: a reference describes one
 * state of one window, and the render that consumes it immediately files its
 * own result as the next one. Left-over entries are the states of windows
 * that never navigated again (a closed tab, a page that was only loaded).
 * MAX_ENTRIES caps those.
 *
 * No html is stored — see DomLayerSlice::__serialize(). A reference is
 * identities and structure, which is all the diff compares.
 */
class DomRendererSession
{
    /** Session key holding every stored reference, url => entry. */
    private const KEY_STORE = 'domReferences';

    /**
     * Lid on the left-overs. Each entry is a few KB without its html, so
     * this is not about memory pressure — it is about the session not
     * growing without an upper bound over a long browsing day.
     */
    private const MAX_ENTRIES = 20;

    /** The consumed entry, cached for the rest of the request. */
    private ?array $entry = null;

    private bool $entryRead = false;

    // =========================================================================
    // Store
    // =========================================================================

    /**
     * Files this request's layer tree under the url it rendered — which is
     * the url the client will name as its reference on the next hop.
     */
    public function storeReference(DomLayerTree $tree): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION[self::KEY_STORE][$this->currentKey()] = [
            'byAddress' => $tree->getReferencesByPanelAddress(),
            'root'      => $tree->root(),
            'time'      => time(),
        ];

        $this->trim();
    }

    // =========================================================================
    // Load
    // =========================================================================

    /**
     * Root layer of the referenced render, or null when there is none — no
     * diff url, an unknown reference, or one that was already used. Null is
     * not an error: every layer then compares against nothing and comes out
     * as 'new', which is a full render. That is the intended fallback
     * whenever server and client disagree about what is on screen.
     */
    public function loadReferenceRoot(): ?DomLayer
    {
        $root = $this->reference()['root'] ?? null;
        return $root instanceof DomLayer ? $root : null;
    }

    /**
     * The address → view name → layer map of the referenced render. This is
     * what the diff actually looks layers up in; the root above only seeds
     * the comparison at the top.
     */
    public function loadReferencesByAddress(): array
    {
        $byAddress = $this->reference()['byAddress'] ?? null;
        return is_array($byAddress) ? $byAddress : [];
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Reads the referenced entry once per request and takes it out of the
     * session as it does.
     *
     * The session is not necessarily started yet: bootstrap builds the
     * renderer before app.php calls sessionStartOnce(), and DomRenderer
     * re-reads in beginDiff() for exactly that reason. So an inactive
     * session yields null WITHOUT marking the entry as read — otherwise that
     * early miss would be cached and the real read never happen.
     */
    private function reference(): ?array
    {
        if ($this->entryRead) {
            return $this->entry;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $this->entryRead = true;

        $key = $this->referenceKey();
        if ($key === null) {
            return null;
        }

        $entry = $_SESSION[self::KEY_STORE][$key] ?? null;
        if (!is_array($entry)) {
            return null;
        }
        // Only a navigation retires the entry. It named the state the window
        // was showing, and that window has moved on - nobody asks for it
        // again. A PUT stays where it is: taking its reference away here
        // leaves the NEXT request without anything to diff against, and that
        // next request is typically the one closing a lightbox. Reading is
        // not using, either: the read happens at bootstrap, long before it is
        // known whether this request renders at all.
        if (self::navigates()) {
            unset($_SESSION[self::KEY_STORE][$key]);
        }

        return $this->entry = $entry;
    }

    /** Key of the state the client says it is showing, or null. */
    private function referenceKey(): ?string
    {
        $url = \http::$referenceUrl;
        return $url === null ? null : $this->key($url);
    }

    /** A request that puts a different url on screen - GET and POST, not PUT. */
    private static function navigates(): bool
    {
        return \http::method() !== 'PUT';
    }

    /**
     * Key of the state this request leaves on screen.
     *
     * A navigation (GET/POST) puts the target url on screen, so that is where
     * the render belongs. A PUT does not navigate: the window keeps showing
     * what it showed, and filing the render under the ACTION url would hide
     * it from everyone - no client ever names an action url as its reference.
     */
    private function currentKey(): string
    {
        if (!self::navigates() && \http::$referenceUrl !== null) {
            return $this->key(\http::$referenceUrl);
        }
        return $this->key((string) \http::$requestUrl);
    }

    /**
     * Both halves of a diff url and the request url itself have to reduce to
     * the same string, so the key is normalised: host dropped, project root
     * dropped, query kept. Keeps it readable in a session dump, too —
     * '/cms/pages/?id=18' rather than a hash.
     */
    private function key(string $url): string
    {
        $path  = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        $root = (string) \http::$root;
        if ($root !== '' && $root !== '/' && str_starts_with($path, $root)) {
            $path = '/' . substr($path, strlen($root));
        }

        return ($path === '' ? '/' : $path) . ($query === '' ? '' : '?' . $query);
    }

    /** Drops the oldest entries once there are more than MAX_ENTRIES. */
    private function trim(): void
    {
        $store = $_SESSION[self::KEY_STORE] ?? [];
        if (count($store) <= self::MAX_ENTRIES) {
            return;
        }
        uasort($store, static fn($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
        $_SESSION[self::KEY_STORE] = array_slice($store, 0, self::MAX_ENTRIES, true);
    }
}
