<?php

declare(strict_types=1);

namespace Systopic\System\Panels;

/**
 * Describes one root folder from which panels are built.
 *
 * Each root contributes its top-level folders to the panel tree. There are no
 * URL-prefix mounts: a root is just a `panels/` directory and the panels it
 * exposes are exactly the folders inside it (and their descendants). The
 * actual mount point — for example /cms/ — emerges automatically from a
 * `cms/` folder existing in the root.
 *
 * Multiple roots can be registered with PanelTree (see loader.php). The
 * FolderResolver merges them in registration order with a last-wins policy,
 * so a later-registered root can override panels from an earlier one.
 *
 * Class-name resolution for a panel folder is driven by the file it ships
 * (`panel.php` → `_panel` suffix, `module.php` → `_module` suffix); see
 * PanelClassResolver. Roots no longer carry a configurable suffix.
 *
 * === Module-based namespace scheme ===
 *
 * When $namespaceRoot is set, panel classes inside this root are addressed as:
 *
 *   <namespaceRoot>\Root\<PathInPascalCase>\Panel
 *
 * Examples (namespaceRoot = 'Systopic\System\Panels'):
 *   panels/cms/panel.php              → Systopic\System\Panels\Root\Cms\Panel
 *   panels/cms/040_backup/panel.php   → Systopic\System\Panels\Root\Cms\Backup\Panel
 *
 * Set namespaceRoot in loader.php via PROJECT_NAMESPACE . '\Panels'
 * (project root) or 'Systopic\System\Panels' (system root).
 * Leave empty ('') to disable the new scheme and use legacy underscore names.
 */
readonly class PanelRootFolder
{
    public function __construct(
        /** Filesystem directory object pointing to the panels root folder. */
        public \fsDir $dir,

        /**
         * Root namespace for panel classes in this module, e.g. 'Systopic\System\Panels'
         * or 'Spaceticker\Panels'. Leave empty to use only the legacy underscore scheme.
         */
        public string $namespaceRoot = '',
    ) {}
}
