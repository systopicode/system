<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Access\Panelmap\Discover\Config;

use Systopic\System\Controlmap\DiscoveredPanel;
use Systopic\System\Controlmap\Map;
use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;

/**
 * admin/access/panelmap/discover/config — the lightbox of one panel: what each
 * layer of the configs says about it, and what that comes to
 * (`Controlmap\Map`). Read only for now.
 *
 * Opened by a chip in discover's table: `?path=cms.pages` (dotted, like the
 * tree's links) and `&focus=` the key clicked (`build`, `actions`,
 * `action.publish`, `layer.cms`), whose row or column is marked.
 *
 * @property \Systopic\System\Panels\Root\Admin\Access\Panelmap\Discover\Panel $parent
 */
class Panel extends CmsPanel {

	public bool $inNavi = false;

	public Map $map {
		get => $this->parent->parent->map;
	}

	/** The panel the lightbox is about; null for a path that is gone. */
	public ?DiscoveredPanel $subject {
		get => $this->map->panel((string) ($this->state->path ?? ''));
	}

	public string $focus {
		get => (string) ($this->state->focus ?? '');
	}

	/** The link that opens it, from discover's table. */
	public static function href(string $discoverHref, string $path, string $focus): string {
		return $discoverHref . 'config/?path=' . rawurlencode(str_replace('/', '.', $path)) . '&focus=' . rawurlencode($focus);
	}

	function getDefaultState() {
		return (object) ['path' => '', 'focus' => ''];
	}

	/** A chip is a get: keep what it asks for, and draw the lightbox anew (a ?x= alone would not). */
	public function onLoadSelected(): void {
		if (\http::method() !== 'GET' || \http::get('path', NULL) === NULL) {
			return;
		}
		$this->state->path = Map::canonicalPanelPath(trim(str_replace('.', '/', (string) \http::get('path')), '/'));
		$this->state->focus = (string) \http::get('focus', '');
		$this->updateView('lightbox');
	}
}
