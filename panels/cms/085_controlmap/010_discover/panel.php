<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Controlmap\Discover;

use Systopic\System\Controlmap\Discover;
use Systopic\System\Controlmap\DiscoveredPanel;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

/**
 * cms/controlmap/discover — every panel and its actions, as the code has
 * them. Read on each view; nothing is kept.
 */
class Panel extends CmsPanel {

	public $icon = 'fa-regular fa-magnifying-glass';

	/** @var list<DiscoveredPanel> */
	public array $panels {
		get => $this->found ??= Discover::ofRequest()->panels();
	}

	/** @var list<DiscoveredPanel>|null */
	private ?array $found = NULL;
}
