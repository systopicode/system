<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Builder;

use Systopic\System\Debug\BoxTree;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Panels\PanelRootFolder;

/**
 * Builds the unified panel tree across all registered roots.
 *
 * One instance is held by PanelTree. Every call to buildChildren() asks the
 * FolderResolver for the merged folder list across all roots and constructs
 * one panel per logical name in a single pass — there is no separate
 * "primary" build followed by a secondary merge anymore.
 *
 * The class itself is stateless apart from the optional debug log and the
 * accessControl flag; results are returned as ChildBuildResult so callers
 * (PanelTree) can aggregate without side effects.
 */
class PanelBuilder
{
    /** @var array<int, array<string, mixed>> Structured build log for debug output. */
    private array $buildLog = [];

    public function __construct(
        private readonly PanelClassResolver $classResolver,
        private bool                        $accessControlEnabled = true,
    ) {}

    public function disableAccessControl(): void
    {
        $this->accessControlEnabled = false;
    }

    /** @return array<int, array<string, mixed>> */
    public function getBuildLog(): array
    {
        return $this->buildLog;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    /**
     * Builds the children of $parent by enumerating folders across all
     * $roots in registration order (last-wins on name collisions) and
     * constructing one panel per logical name.
     *
     * Walks $limb to select the correct child and recurses into it. The
     * caller is responsible for restoring CWD afterwards.
     *
     * @param PanelRootFolder[] $roots
     */
    public function buildChildren(
        PanelNode $parent,
        array     $limb,
        array     $roots,
    ): ChildBuildResult {
        if ($parent->children !== [] || count($parent->path) > 20) {
            if (count($parent->path) > 20) {
                error_log('PanelBuilder::buildChildren — max nesting exceeded at ' . $parent->name);
            }
            return new ChildBuildResult([], [], null);
        }

        $parent->childSelectedName = count($limb) > 0 ? reset($limb) : '';

        $hits = FolderResolver::discover($parent->realPath, $roots);

        if (defined('DEBUG') && DEBUG()) {
            $this->buildLog[] = [
                'event'          => 'scanChildren',
                'parent'         => $parent->name,
                'parentRealPath' => implode('/', $parent->realPath) ?: '(root)',
                'foldersFound'   => implode(', ', array_keys($hits)) ?: '(none)',
                'selectingChild' => $parent->childSelectedName ?: '(none)',
                'rootCount'      => count($roots),
            ];
        }

        $prevChild    = null;
        $resolvedPath = [];
        $resolvedReal = [];
        $selected     = null;
        // Use native getcwd() so chdir() round-trips on Windows; fs::cwd()
        // returns the forward-slash form (e.g. /C/laragon/...) which chdir
        // does not recognise.
        $savedCwd = \getcwd();

        foreach ($hits as $hit) {
            if (!@chdir(\fs::toNative($hit->absPath))) {
                continue;
            }

            $child  = $this->constructPanel($hit->name, $parent, $hit);
            $this->createReferences($child, $parent, $hit->folder);
            $child->absPath = $hit->absPath;

            $isSelected = ($hit->name === $parent->childSelectedName);

            if ($this->accessControlEnabled && !$child->buildGranted()) {
                if (defined('DEBUG') && DEBUG()) {
                    $this->buildLog[] = [
                        'event'  => 'accessDenied',
                        'name'   => $hit->name,
                        'folder' => $hit->folder,
                        'root'   => (string) $hit->root->dir,
                    ];
                }
                chdir($savedCwd);
                continue;
            }

            $this->linkSibling($child, $parent, $prevChild);
            $prevChild = $child;
            $parent->lastChild = $child;
            $child->onBuild();

            if ($isSelected) {
                $parent->childSelected = $child;
                $resolvedPath[]        = $child->name;
                $resolvedReal[]        = $child->folder;
                $selected              = $child;

                $subLimb = array_slice($limb, 1);
                if (count($subLimb) > 0) {
                    chdir($savedCwd);
                    $sub          = $this->buildChildren($child, $subLimb, $roots);
                    $resolvedPath = array_merge($resolvedPath, $sub->resolvedPath);
                    $resolvedReal = array_merge($resolvedReal, $sub->resolvedRealPath);
                    $selected     = $sub->selected ?? $selected;
                    continue;
                }
                // Selected leaf — still expand its children when the panel
                // requests it (loadChildPanels / legacy loadChildModules),
                // so navigation templates calling eachChild() find siblings
                // to iterate over even on URLs like /cms/.
                if ($child->buildChildPanels
                 || $child->loadChildPanels
                 || $this->legacyBuildAllChildren($child)) {
                    chdir($savedCwd);
                    $this->buildChildren($child, [], $roots);
                    continue;
                }
            } elseif ($child->buildChildPanels
                   || $child->loadChildPanels
                   || $this->legacyBuildAllChildren($child)) {
                chdir($savedCwd);
                $this->buildChildren($child, [], $roots);
                continue;
            }

            chdir($savedCwd);
        }

        if (\getcwd() !== $savedCwd) {
            chdir($savedCwd);
        }

        // Unmatched URL segment → parent becomes the effective selected panel
        if ($parent->childSelectedName !== ''
            && $parent->childSelected === null
            && $parent->parent !== null
        ) {
            $parent->action = \app::request()->action;
        }

        // Mark this scan complete so PanelNode::ensureChildrenBuilt() does
        // not re-scan when children are legitimately empty.
        $parent->childrenBuilt = true;

        return new ChildBuildResult($resolvedPath, $resolvedReal, $selected);
    }

    /**
     * Convenience wrapper for the "build every direct child without path
     * selection" case — used by PanelTree when re-rendering an unselected
     * sub-tree that needs all its children populated.
     *
     * @param PanelRootFolder[] $roots
     */
    public function buildAllChildren(PanelNode $parent, array $roots): void
    {
        $this->buildChildren($parent, [], $roots);
    }

    /**
     * Instantiates a single panel for the given $name from a FolderHit.
     *
     * Must be called with CWD set to the panel's folder ($hit->absPath).
     * Includes panel.php / module.php if present, then delegates class
     * resolution to PanelClassResolver and tags the panel with its
     * sourceRoot so re-render lookups know which root to consult.
     */
    public function constructPanel(
        string     $name,
        ?PanelNode $parent,
        FolderHit  $hit,
    ): PanelNode {
        // Include panel.php and module.php from every root that has this
        // folder, in registration order. The winning root's file is included
        // last so its definitions take precedence on first load.
        // include_once guarantees no duplicate-definition errors across
        // requests. Both file names are supported: panel.php is the modern
        // convention (→ class candidates with `_panel` suffix), module.php
        // is the legacy convention (→ `_module` suffix). A folder may ship
        // either or both; the resolver tries `_panel` first, then `_module`.
        $hasOwnFile = false;
        foreach ($hit->altPaths as $alt) {
            foreach (['panel.php', 'module.php'] as $file) {
                $abs = $alt . '/' . $file;
                if (\fs::isFile($abs)) {
                    include_once \fs::toNative($abs);
                    $hasOwnFile = true;
                }
            }
        }

        $resolved  = $this->classResolver->resolve($name, $parent, $hasOwnFile, $hit->root);
        $className = $resolved->className;

        /** @var PanelNode $panel */
        $panel = new $className($name, $parent);

        // When parent bequeaths its class but this folder has no own file,
        // the child should not further bequest.
        if ($parent !== null && $parent->bequests && !$hasOwnFile) {
            $panel->bequests = false;
        }

        $panel->hasOwnClass = $resolved->hasOwnClass;
        $panel->jsClass     = \fs::isFile('panel.js') ? 'panel.js' : (\fs::isFile('module.js') ? 'module.js' : null);
        $panel->sourceRoot  = $hit->root;

        if (defined('DEBUG') && DEBUG()) {
            $this->buildLog[] = [
                'event'       => 'construct',
                'name'        => $name,
                'parent'      => $parent?->name ?? '(root)',
                'cwd'         => \fs::cwd(),
                'className'   => $resolved->className,
                'hasOwnClass' => $resolved->hasOwnClass ? 'TRUE' : 'FALSE',
                'ownFile'     => $hasOwnFile ? 'TRUE' : 'FALSE',
                'root'        => (string) $hit->root->dir,
            ];
        }

        return $panel;
    }

    /**
     * Constructs the synthetic top-level "app" panel.
     *
     * The app panel may itself be a real, rendering panel: when one of the
     * registered roots ships a panel.php / module.php directly in its
     * panels/ base, that file is included and the resolver picks the
     * matching class (e.g. app_panel). When no class file exists the
     * panel falls back to the bare PanelNode but still gets an absPath
     * pointing at the (last-wins) registered root, so a global
     * panels/body.tpl.php can be discovered by findRenderRoot().
     *
     * @param PanelRootFolder[] $roots
     */
    public function constructAppRoot(array $roots): PanelNode
    {
        $hit = FolderResolver::discoverRoot($roots);

        $hasOwnFile = false;
        if ($hit !== null) {
            foreach ($hit->altPaths as $alt) {
                foreach (['panel.php', 'module.php'] as $file) {
                    $abs = $alt . '/' . $file;
                    if (\fs::isFile($abs)) {
                        include_once \fs::toNative($abs);
                        $hasOwnFile = true;
                    }
                }
            }
        }

        $resolved  = $this->classResolver->resolve('app', null, $hasOwnFile, $hit?->root ?? ($roots[0] ?? null));
        $className = $resolved->className;

        /** @var PanelNode $panel */
        $panel = new $className('app', null);
        $panel->path        = [];
        $panel->realPath    = [];
        $panel->hasOwnClass = $resolved->hasOwnClass;
        $panel->absPath     = $hit?->absPath ?? '';
        $panel->sourceRoot  = $hit?->root ?? ($roots[0] ?? null);

        return $panel;
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /** The build log as a box-tree root (Debug\BoxTree), one box per event. */
    public function debugBuildLog(): array
    {
        $events = [];
        foreach ($this->buildLog as $entry) {
            $eventType = (string) ($entry['event'] ?? 'unknown');
            $eventName = (string) ($entry['name'] ?? $entry['parent'] ?? '');
            $events[] = BoxTree::node("{$eventType}: {$eventName}", $entry, ['buildEvent', $eventType]);
        }
        return BoxTree::node('PanelBuilder', ['events' => count($this->buildLog)], ['buildLog'], $events);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Backward-compat check for legacy property names that older module.php
     * files may set instead of $buildChildPanels / $loadChildPanels.
     *
     * Both legacy flags trigger an "always build all children" path here:
     * in the legacy moduleBuilder, $loadChildModules implied that the
     * children would be present (built by default) so its hooks could run —
     * to preserve that contract under the new builder, where the default
     * no longer constructs every sibling, treat both as build triggers.
     */
    private function legacyBuildAllChildren(PanelNode $child): bool
    {
        return (property_exists($child, 'buildChildModules') && (bool) $child->buildChildModules)
            || (property_exists($child, 'loadChildModules')  && (bool) $child->loadChildModules);
    }

    private function createReferences(PanelNode $child, PanelNode $parent, string $folder): void
    {
        $child->level      = $parent->level + 1;
        $child->parent     = $parent;
        $child->root       = $parent->root;
        $child->folder     = $folder;
        $child->realPath   = $parent->realPath;
        $child->realPath[] = $folder;
    }

    private function linkSibling(PanelNode $child, PanelNode $parent, ?PanelNode $prev): void
    {
        $parent->children[]                    = $child;
        $parent->childrenByName[$child->name]  = $child;

        if ($prev === null) {
            $parent->firstChild = $child;
        } else {
            $child->prev = $prev;
            $prev->next  = $child;
        }
    }
}
