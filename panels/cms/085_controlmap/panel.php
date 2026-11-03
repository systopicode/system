<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Controlmap;

use Systopic\System\Controlmap\Discover;
use Systopic\System\Controlmap\DiscoveredPanel;
use Systopic\System\Controlmap\Map;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

/**
 * cms/controlmap — who may build which panel and run which of its actions.
 *
 * The pylon is the panel tree of the installation, as Discover reads it off
 * the folders and the code (`Controlmap\Discover`, nothing stored), under
 * one root. The node chosen there (`?panel=cms.pages`) is what the children
 * show: `discover/` its panels one level down. The maps and the configs the
 * running system reads come next (`.cursor/docs/controlmap.md`).
 */
class Panel extends CmsPanel {

	public $showSubnavi = FALSE;
	public $icon = 'cmsIcon cmsIcon-duotone cmsIcon-sitemap';
	public ?int $naviOrder = 45;
	public bool $buildChildPanels = true;

	/** @var list<DiscoveredPanel> the top-level panels, read once per request */
	public array $panels {
		get => $this->found ??= Discover::ofRequest()->panels();
	}

	/** @var list<DiscoveredPanel>|null */
	private ?array $found = NULL;

	/** The configs over these panels: who may, merged and inherited (`Controlmap\Map`). */
	public Map $map {
		get => $this->mapped ??= new Map($this->panels, rtrim(str_replace('\\', '/', (string) \fs::$projectRoot), '/') . '/config/controlmap.json');
	}

	private ?Map $mapped = NULL;

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

	/**
	 * The link that chooses a node, the path dotted (`?panel=cms.tools`, as
	 * in a panelPath; no panel folder has a dot). Neither form of the slash
	 * survives the client: it reads a query value without decoding it and
	 * encodes it again (sys.http.parseQuery, obj.getQueryString), so `%2F`
	 * arrived as `%252F`, and a bare `/` is taken for the path when the
	 * address is pushed. Proposal: .cursor/team/2026-11-02-query-encoding.md
	 */
	public static function nodeHref(string $path): string {
		return './?panel=' . rawurlencode(str_replace('/', '.', $path));
	}

	/** @var list<string> the paths unfolded in the tree; the root is always open */
	public array $openFolds {
		get => array_values(array_filter((array) ($this->state->openFolds ?? []), 'is_string'));
	}

	/** @return list<string> every path that has panels below, the ones a fold could open */
	public function foldablePaths(): array {
		$paths = [];
		foreach ($this->panels as $top) {
			foreach ($top->all() as $panel) {
				if ($panel->children !== []) {
					$paths[] = $panel->path;
				}
			}
		}
		return $paths;
	}

	public function toggleFold(string $path): void {
		$open = $this->openFolds;
		$this->state->openFolds = in_array($path, $open, TRUE)
			? array_values(array_diff($open, [$path]))
			: [...$open, $path];
	}

	/** Unfold the way to a node, without the node itself. */
	public function openPathTo(string $path): void {
		$open = $this->openFolds;
		$segments = explode('/', $path);
		for ($i = 1; $i < count($segments); $i++) {
			$open[] = implode('/', array_slice($segments, 0, $i));
		}
		$this->state->openFolds = array_values(array_unique($open));
	}

	/** What the root is called: the project folder. */
	public string $rootLabel {
		get => basename(rtrim(str_replace('\\', '/', (string) \fs::$projectRoot), '/')) ?: 'panels';
	}

	function buildGranted() {
		return \Systopic\System\Auth\Session::isSuperuser();
	}

	function onLoad() {
		// a node of the tree is a get to the open child (./?panel=cms.pages, see nodeHref())
		$chosen = \http::get('panel', NULL);
		if ($chosen !== NULL && \http::method() === 'GET') {
			$this->state->selected = trim(str_replace('.', '/', (string) $chosen), '/');
			// a link in the table may choose a node inside a closed fold
			$this->openPathTo($this->state->selected);
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
		$defaultState->openFolds = [];
		return $defaultState;
	}
}
