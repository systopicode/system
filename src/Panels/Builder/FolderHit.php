<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Builder;

use Systopic\System\Panels\PanelRootFolder;

/**
 * One folder discovered by FolderResolver, attributed to its source root.
 *
 * The resolver builds a name → FolderHit map across all registered roots and
 * lets later roots overwrite earlier ones (last-wins). The PanelBuilder then
 * iterates the map to construct one panel per logical name, knowing which
 * root each came from so per-panel realPath, sourceRoot and re-render lookups
 * stay correct.
 */
final readonly class FolderHit
{
    public function __construct(
        /** Logical panel name, e.g. 'pages' (after stripping the '010_' prefix). */
        public string $name,

        /** Raw folder name on disk (winning root), e.g. '010_pages'. Used for realPath. */
        public string $folder,

        /** Absolute filesystem path to the winning folder, used for chdir(). */
        public string $absPath,

        /** Root that contributed the winning folder (last in registration order). */
        public PanelRootFolder $root,

        /**
         * Absolute paths for this logical name across ALL roots that have it,
         * in registration order. The last entry equals $absPath. PanelBuilder
         * includes module.php from every entry — so a class defined in sys
         * still applies when site only contributes an empty same-named folder
         * (e.g. site/modules/cms/ without its own module.php, while sys's
         * cms_module class lives in sys/modules/cms/module.php).
         *
         * @var string[]
         */
        public array $altPaths = [],
    ) {}
}
