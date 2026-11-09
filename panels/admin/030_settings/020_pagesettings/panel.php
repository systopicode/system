<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Pagesettings;

use Systopic\System\Panels\Root\Admin\Settings\Panel as SettingsPanel;

/**
 * Global settings — one table per group. The file is config/global.settings.json.
 *
 * The file is shared: a settings panel of another package names the keys it
 * shows (`public array $globalSettings`, Website of the cms), they are not
 * shown here. Without that package they stay here.
 */
class Panel extends SettingsPanel
{
    public $icon = 'cmsIcon cmsIcon-globe';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Global Settings';
    }

    function onLoad() {}

    function onLoadThisClass() {}

    /**
     * The keys a sibling panel shows.
     *
     * @return list<string>
     */
    public function claimed(): array
    {
        $keys = [];
        for ($node = $this->parent?->firstChild; $node !== null; $node = $node->next) {
            if ($node !== $this && is_array($node->globalSettings ?? null)) {
                array_push($keys, ...array_map('strval', $node->globalSettings));
            }
        }
        return array_values(array_unique($keys));
    }

    public function searchHits(\Systopic\System\Sys\Search $search): void
    {
        \Systopic\System\Settings\ProjectForm::searchHits($search, 'page', $this->getHref(\http::$root), [], $this->claimed());
    }
}
