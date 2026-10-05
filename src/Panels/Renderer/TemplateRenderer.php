<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Renderer;

use Systopic\System\Panels\PanelNode;

/**
 * Renders template files in an isolated function scope.
 *
 * The legacy system included templates in the global scope so they could
 * access $module and $state as globals (set via $GLOBALS['module'] = ...).
 * TemplateRenderer instead passes the panel and its state as explicit local
 * variables, completely removing the need for $GLOBALS.
 *
 * For backward compatibility, the variable $module is also provided as an
 * alias for $panel so that existing templates that use $module->... continue
 * to work without changes during the migration.
 */
class TemplateRenderer
{
    /**
     * Includes $absolutePath inside an isolated static closure.
     *
     * Variables available inside the template:
     *  - $panel  — the active PanelNode
     *  - $module — alias for $panel (backward compat)
     *  - $state  — $panel->state shorthand
     *
     * Output is written directly to the current output buffer (which may be
     * captured by DomLayerTree / DomRenderer).
     */
    public function render(string $absolutePath, PanelNode $panel): void
    {
        $state  = $panel->state;
        $module = $panel; // backward compat alias

        (static function (
            string    $__path,
            PanelNode $panel,
            PanelNode $module,
            ?object   $state,
        ): void {
            include $__path;
        })($absolutePath, $panel, $module, $state);
    }

    /**
     * Returns true when the template file exists at the given absolute path.
     */
    public function exists(string $absolutePath): bool
    {
        return is_file($absolutePath);
    }

    /**
     * Builds an absolute template path from a folder path and a view name.
     *
     * @param string $folder   Absolute or relative path to the panel's folder.
     * @param string $viewName View name without extension (e.g. 'main', 'body').
     */
    public function resolvePath(string $folder, string $viewName): string
    {
        $folder = rtrim(str_replace('\\', '/', $folder), '/');
        return "{$folder}/{$viewName}.tpl.php";
    }
}
