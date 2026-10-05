<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Layer\Slice;

use Systopic\System\Debug\BoxTree;
use Systopic\System\Dom\Debug\OpenerInfo;
use Systopic\System\Dom\Layer\DomLayerItem;
use Systopic\System\Dom\Layer\DomLayer;

/**
 * A captured chunk of HTML output between two DomLayer open/close events.
 *
 * The legacy domLayerSlice called ob_get_clean() on itself. Here the output
 * buffer is owned by DomLayerTree; the tree calls close() and injects the
 * captured string so slices stay stateless regarding OB management.
 */
class DomLayerSlice implements DomLayerItem
{
    private string $html     = '';
    private int    $byteSize = 0;

    /** Back-reference set by DomLayer::add(). */
    public ?DomLayer $layer = null;

    /**
     * User-code call site that caused this slice to be appended — populated by
     * DomLayerTree when DEBUGRENDER is on. See Dom\Debug\OpenerInfo.
     *
     * @var array{path:string,file:string,line:int,function:string,class:string}|null
     */
    public ?array $opener = null;

    public function isLayer(): bool
    {
        return false;
    }

    /**
     * Called by DomLayerTree when the OB is flushed.
     * Receives the captured HTML string and records its byte size.
     * Empty slices are discarded by the tree before calling close().
     */
    public function close(string $capturedHtml): void
    {
        $this->html     = $capturedHtml;
        $this->byteSize = strlen($capturedHtml);
    }

    public function isEmpty(): bool
    {
        return $this->byteSize === 0;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function __toString(): string
    {
        return $this->html;
    }

    // =========================================================================
    // Session serialisation — the html does not travel
    // =========================================================================

    /**
     * How much of the html the debug excerpt keeps. Exactly what debugInfo()
     * can display, so a restored slice shows the same thing a live one does.
     */
    private const DEBUG_EXCERPT = 300;

    /**
     * The reference tree goes into the session so the NEXT request can diff
     * against it — and a diff never reads the html. It compares identities
     * (DomRenderer::addReference) and looks layers up by address and view
     * name (DomLayerTree::getReference). Keeping a whole rendered page per
     * entry was a debugging leftover.
     *
     * What still travels with DEBUG on is the excerpt debugInfo() displays,
     * so the reference side of the layer comparison stays readable. byteSize
     * is kept either way — it is the ORIGINAL length, which is what the
     * debug view sizes itself by.
     */
    public function __serialize(): array
    {
        $excerpt = '';
        if (function_exists('DEBUG') && DEBUG()) {
            $excerpt = $this->byteSize > self::DEBUG_EXCERPT
                // start and end, the two pieces debugInfo() shows
                ? substr($this->html, 0, (int) (self::DEBUG_EXCERPT / 2))
                  . substr($this->html, -(int) (self::DEBUG_EXCERPT / 2))
                : $this->html;
        }

        return [
            'html'     => $excerpt,
            'byteSize' => $this->byteSize,
            'layer'    => $this->layer,
            'opener'   => $this->opener,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->html     = (string) ($data['html'] ?? '');
        $this->byteSize = (int) ($data['byteSize'] ?? 0);
        $this->layer    = $data['layer'] ?? null;
        $this->opener   = $data['opener'] ?? null;
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /**
     * Sized by byteSize, not by strlen($html): for a slice that came back
     * from the session the html IS the excerpt, and asking it how long it is
     * would take the short branch and print start+end glued together.
     */
    public function debugInfo(): array
    {
        $maxlen = self::DEBUG_EXCERPT;
        // Raw html: the debug tool writes info values as text, so there is
        // nothing to escape here any more - and the JSON copy reads as html.
        $info = ['opener' => OpenerInfo::caller($this->opener)];
        if ($this->byteSize > $maxlen) {
            $info['start'] = substr($this->html, 0, (int) ($maxlen / 2));
            $info['end']   = substr($this->html, -(int) ($maxlen / 2));
        } else {
            $info['html'] = $this->html;
        }
        return $info;
    }

    /** The slice as a box of the debug tree — labelled with its size. */
    public function debugNode(): array
    {
        return BoxTree::node((string) $this->byteSize, $this->debugInfo(), ['slice']);
    }
}
