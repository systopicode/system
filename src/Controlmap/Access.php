<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

use Systopic\Db\Scope;
use Systopic\System\Auth\Session;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\UserRights\UserRights;

/**
 * The controlmap at runtime: may this request build a panel, run one of
 * its actions, use a named right, open an action by a link from anywhere?
 * PanelBuilder and ActionGuard ask here, a panel's code for a right
 * (`Access::right($this, 'move')`).
 *
 * Who may is said in the configs (`Map`): the panel.config.json of the
 * folders of the panels this request builds and of their ancestors
 * (`PanelNode::$altPaths`), up to the root (`panels/panel.config.json`,
 * the default: `authenticated`), and config/controlmap.json. No Discover.
 * The code only says what happens when it says no
 * (`PanelNode::onAccessDenied()`).
 *
 * Panels that still decide in code — `buildGranted()` / `actionGranted()`
 * declared by a class of their own (legacy, pwk) — keep doing so. In this
 * order:
 *
 *   1. the panel's own config (any of its layers) for the key
 *   2. the panel's own code — its folder has its class, and the class
 *      declares the method itself
 *   3. the config it inherits from a panel above it (`Map::effective()`)
 *   4. code it inherits — a class other than PanelNode declares the method
 *   5. the root's config, the default
 *   6. nothing anywhere: `authenticated`
 *
 * A list of role keys passes the superuser (unless viewing as a role).
 * Pseudo roles: `everyone` every request, `authenticated` everybody
 * signed in, `anonymous` nobody signed in. The others pass as
 * `Session::hasOneRole()` (fake roles included); a key no role has is
 * skipped, not thrown. The recovery user (no database) passes `everyone`,
 * `authenticated` and the recovery roles he holds (`recovery`, `restore`),
 * nothing else.
 */
final class Access
{
    public const PSEUDO_ROLES = ['everyone', 'authenticated', 'anonymous'];

    private static ?Map $map = null;

    public static function build(PanelNode $node): bool
    {
        return self::decide($node, 'build', static fn(): bool => (bool) $node->buildGranted(), 'buildGranted');
    }

    public static function action(PanelNode $node, string $action): bool
    {
        return self::decide($node, 'action.' . $action, static fn(): bool => (bool) $node->actionGranted($action), 'actionGranted');
    }

    /**
     * A named right (`move`): the panel's or an ancestor's `right.<name>`;
     * none anywhere: whoever may run the panel's actions.
     */
    public static function right(PanelNode $node, string $right): bool
    {
        $grant = self::map()->effective(self::panel($node), 'right.' . $right);
        if ($grant !== null && str_starts_with($grant->key, 'right.')) {
            return self::holds($grant->roles);
        }
        return self::decide($node, 'actions', static fn(): bool => (bool) $node->actionGranted(null), 'actionGranted');
    }

    /**
     * May the action be opened by a link from anywhere — a mail, a bookmark,
     * another site (GET only, ActionGuard)? The panel's `linkable` list.
     */
    public static function linkable(PanelNode $node, string $action): bool
    {
        return in_array($action, self::map()->linkable(self::panel($node)), true);
    }

    /** Read the configs again: after a write, and between tests. */
    public static function reset(): void
    {
        self::$map = null;
    }

    /**
     * The order of the class docblock. `$code` asks the panel's
     * `$method`, where legacy code still decides.
     */
    private static function decide(PanelNode $node, string $key, \Closure $code, string $method): bool
    {
        $panel = self::panel($node);
        $grant = self::map()->effective($panel, $key);
        if ($grant !== null && $grant->path === $panel->path) {
            return self::holds($grant->roles);
        }
        $declaring = (new \ReflectionMethod($node, $method))->getDeclaringClass()->getName();
        if ($node->hasOwnClass && $declaring === $node::class) {
            return $code();
        }
        if ($grant !== null && $grant->path !== '') {
            return self::holds($grant->roles);
        }
        if ($declaring !== PanelNode::class) {
            return $code();
        }
        return self::holds($grant?->roles ?? ['authenticated']);
    }

    /**
     * Does the request hold one of the roles? The session is asked only
     * here — where no config says anything, it is not touched.
     *
     * @param list<string> $roles
     */
    private static function holds(array $roles): bool
    {
        if (Session::isRecovery()) {   // no database to ask
            foreach ($roles as $role) {
                if ($role === 'everyone' || $role === 'authenticated'
                    || (in_array($role, Session::RECOVERY_ROLES, true) && Session::hasRole($role))) {
                    return true;
                }
            }
            return false;
        }
        if (Session::isSuperuser()) {   // not while viewing as a role: then those count
            return true;
        }
        $known = null;
        $assigned = [];
        foreach ($roles as $role) {
            if ($role === 'everyone'
                || ($role === 'authenticated' && Session::known())
                || ($role === 'anonymous' && !Session::known())) {
                return true;
            }
            if (in_array($role, self::PSEUDO_ROLES, true) || in_array($role, Session::RECOVERY_ROLES, true)) {
                continue;
            }
            $known ??= UserRights::keyedRoles(Scope::default());
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

    /** The panel as the map knows it — registered with its ancestors, up to the root, on first use. */
    private static function panel(PanelNode $node): DiscoveredPanel
    {
        $path = implode('/', $node->path);
        $map = self::map();
        if (($known = $map->panel($path)) !== null) {
            return $known;
        }
        if ($node->parent !== null) {
            self::panel($node->parent);
        }
        $panel = new DiscoveredPanel(
            path: $path,
            name: $node->name,
            folder: basename((string) $node->absPath),
            origin: Discover::origin((string) $node->absPath),
            dir: (string) $node->absPath,
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
