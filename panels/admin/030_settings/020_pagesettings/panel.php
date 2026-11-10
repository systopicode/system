<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Pagesettings;

use Systopic\System\Panels\Root\Admin\Settings\Panel as SettingsPanel;
use Systopic\System\Settings\ProjectFile;
use Systopic\System\Settings\ProjectForm;

/**
 * Global settings — one table per group. The file is config/global.settings.json.
 *
 * What a package defines (`x-constant` in its definition) lives in its
 * configuration, in config.json (`bin/config.php --import-settings` moves
 * it); here shows only what is left. A settings panel of another package may
 * name keys it shows itself (`public array $globalSettings`).
 */
class Panel extends SettingsPanel
{
    public $icon = 'cmsIcon cmsIcon-globe';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Legacy Settings';
        $this->inNavi = false;
    }

    function onLoad() {}

    public function refreshNavigation(): void
    {
        $path = ProjectFile::path('page');
        $this->inNavi = $path !== null && ProjectForm::hasVisibleGroups($path, $this->claimed());
    }

    function onLoadThisClass() {}

    /**
     * The keys shown elsewhere: in the configuration of a package (its
     * definition names them, `x-constant`) or by a sibling panel.
     *
     * @return list<string>
     */
    public function claimed(): array
    {
        $keys = [];
        foreach (\Systopic\System\Config\Reconcile::PACKAGES as $package) {
            foreach (\Systopic\System\Config\Config::constantsOf(\Systopic\System\Config\Schema::load($package), []) as [$name]) {
                $keys[] = $name;
            }
        }
        for ($node = $this->parent?->firstChild; $node !== null; $node = $node->next) {
            if ($node !== $this && is_array($node->globalSettings ?? null)) {
                array_push($keys, ...array_map('strval', $node->globalSettings));
            }
        }
        return array_values(array_unique($keys));
    }

    public function searchHits(\Systopic\System\Sys\Search $search): void
    {
        ProjectForm::searchHits($search, 'page', $this->getHref(\http::$root), [], $this->claimed(), $this->label);
    }
}
