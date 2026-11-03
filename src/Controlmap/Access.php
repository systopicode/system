<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

use Systopic\Db\Scope;
use Systopic\System\Auth\Session;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\UserRights\UserRights;

/**
 * The controlmap at runtime: may this request build a panel, run one of
 * its actions, use a named right? PanelBuilder and ActionGuard ask here,
 * a panel's code for a right (`Access::right($this, 'move')`).
 *
 * The configs (`Map`) for the panels this request builds, and their
 * ancestors — read off their folders (`PanelNode::$altPaths`) and
 * config/controlmap.json, no Discover. What decides, in this order:
 *
 *   1. the panel's own config (any of its layers) for the key
 *   2. the panel's own code — its folder has its class, and the class
 *      declares buildGranted() / actionGranted() itself (a class the
 *      parent bequeaths is the parent's code, like the parent's config)
 *   3. the config it inherits (the parent's, see `Map::effective()`)
 *   4. the code it inherits (PanelNode: anybody signed in)
 *
 * So a config never loosens what a panel decides for itself in code,
 * unless it is the panel's own config.
 *
 * A list of role keys passes the superuser (unless viewing as a role), `everyone` every
 * request, `authenticated` everybody signed in; the others as
 * `Session::hasOneRole()` (fake roles included). A key no role has is
 * skipped, not thrown. The recovery user has no database: the code
 * decides.
 */
final class Access
{
    private static ?Map $map = null;

    public static function build(PanelNode $node): bool
    {
        return self::decide($node, 'build', 'buildGranted') ?? (bool) $node->buildGranted();
    }

    public static function action(PanelNode $node, string $action): bool
    {
        return self::decide($node, 'action.' . $action, 'actionGranted') ?? (bool) $node->actionGranted($action);
    }

    /**
     * A named right (`move`): the panel's or an ancestor's `right.<name>`;
     * none anywhere: whoever may run the panel's actions.
     */
    public static function right(PanelNode $node, string $right): bool
    {
        $grant = self::map()->effective(self::panel($node), 'right.' . $right);
        if ($grant !== null && str_starts_with($grant->key, 'right.') && ($held = self::holds($grant->roles)) !== null) {
            return $held;
        }
        return self::decide($node, 'actions', 'actionGranted') ?? (bool) $node->actionGranted(null);
    }

    /** Read the configs again: after a write, and between tests. */
    public static function reset(): void
    {
        self::$map = null;
    }

    /** True/false where a config decides (see the class), null where the code does. */
    private static function decide(PanelNode $node, string $key, string $method): ?bool
    {
        $panel = self::panel($node);
        $grant = self::map()->effective($panel, $key);
        if ($grant !== null && $grant->path === $panel->path) {
            return self::holds($grant->roles);
        }
        if ($node->hasOwnClass && (new \ReflectionMethod($node, $method))->getDeclaringClass()->getName() === $node::class) {
            return null;
        }
        return $grant === null ? null : self::holds($grant->roles);
    }

    /**
     * Does the request hold one of the roles? Null for the recovery user,
     * who has no database to ask: the code decides. The session is asked
     * only here — where no config says anything, it is not touched.
     *
     * @param list<string> $roles
     */
    private static function holds(array $roles): ?bool
    {
        if (Session::isRecovery()) {
            return null;
        }
        if (Session::isSuperuser()) {   // not while viewing as a role: then those count
            return true;
        }
        $known = UserRights::keyedRoles(Scope::default());
        $assigned = [];
        foreach ($roles as $role) {
            if ($role === 'everyone' || ($role === 'authenticated' && Session::known())) {
                return true;
            }
            if (isset($known[$role]) && !isset(\Systopic\System\Tables\Roles\Operator::BUILTIN[$role])) {
                $assigned[] = $role;
            }
        }
        return $assigned !== [] && Session::hasOneRole(...$assigned);
    }

    private static function map(): Map
    {
        return self::$map ??= new Map([], Map::projectFile());
    }

    /** The panel as the map knows it — registered with its ancestors on first use. */
    private static function panel(PanelNode $node): DiscoveredPanel
    {
        $path = implode('/', $node->path);
        $map = self::map();
        if (($known = $map->panel($path)) !== null) {
            return $known;
        }
        if ($node->parent !== null && $node->parent->path !== []) {
            self::panel($node->parent);
        }
        $panel = new DiscoveredPanel(
            path: $path,
            name: $node->name,
            folder: basename($node->absPath),
            origin: Discover::origin($node->absPath),
            dir: $node->absPath,
            origins: [],
            dirs: Discover::dirs($node->altPaths),
            classFile: null,
            className: $node::class,
            extends: null,
            grants: [],
            actions: [],
            hasTemplates: false,
            children: [],
        );
        $map->register($panel);
        return $panel;
    }
}
