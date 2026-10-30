<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Controlmap;

use Systopic\System\Controlmap\Discover;
use Systopic\System\Controlmap\DiscoveredPanel;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

/**
 * cms/controlmap — who may build which panel and run which of its actions.
 *
 * The pylon is the panel tree of the installation, as Discover reads it off
 * the folders and the code (`Controlmap\Discover`, nothing stored), under
 * one root. The node chosen there (`?panel=cms/pages`) is what the children
 * show: `discover/` its panels one level down. The maps and the configs the
 * running system reads come next (`.cursor/docs/controlmap.md`).
 */
class Panel extends CmsPanel {

	public $showSubnavi = FALSE;
	public $icon = 'fa-duotone fa-regular fa-sitemap';
	public ?int $naviOrder = 45;
	public bool $buildChildPanels = true;

	/** @var list<DiscoveredPanel> the top-level panels, read once per request */
	public array $panels {
		get => $this->found ??= Discover::ofRequest()->panels();
	}

	/** @var list<DiscoveredPanel>|null */
	private ?array $found = NULL;

	/** The logical path chosen in the tree; '' is the root. */
	public string $selectedPath {
		get => (string) ($this->state->selected ?? '');
	}

	/** The panel chosen in the tree; null for the root (or a path that is gone). */
	public ?DiscoveredPanel $selected {
		get {
			foreach ($this->panels as $top) {
				foreach ($top->all() as $panel) {
					if ($panel->path === $this->selectedPath) {
						return $panel;
					}
				}
			}
			return NULL;
		}
	}

	/** @var list<DiscoveredPanel> the level below the chosen node */
	public array $selectedChildren {
		get => $this->selected?->children ?? $this->panels;
	}

	/** What the root is called: the project folder. */
	public string $rootLabel {
		get => basename(rtrim(str_replace('\\', '/', (string) \fs::$projectRoot), '/')) ?: 'panels';
	}

	function buildGranted() {
		return \Systopic\System\Auth\Session::isSuperuser();
	}

	function onLoad() {
		// a node of the tree is a get to the open child (./?panel=cms/pages)
		$chosen = \http::get('panel', NULL);
		if ($chosen !== NULL && \http::method() === 'GET') {
			$this->state->selected = trim((string) $chosen, '/');
		}
		// only while this panel is on screen (see cms/users)
		if ($this->inPath()) {
			$this->updateView('pylon');
		}
		$this->rememberOrOpenChild();
	}

	function getDefaultState() {
		$defaultState = parent::getDefaultState();
		$defaultState->openChild = NULL;
		$defaultState->selected = '';
		return $defaultState;
	}
}
