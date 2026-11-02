<?php
/**
 * The panel tree of the installation under one root — every node a get to
 * the open child (`./?panel=…`), which shows that node's level.
 *
 * Folds: a node with panels below has a chevron (`fold`); the open ones are
 * kept as paths in `openFolds` of the state, the root is always open. Open
 * all / close all sit in the header. The actions are discover's
 * (discover/actions.php), like the width.
 *
 * The header also has discover's two switches: the magnifier shows every
 * panel (`?all=1`, discover's "show all") — then no node is chosen, and a
 * field above the tree filters the tree and the table alike
 * (`filterPanels`, `Discover\Panel::matches()`): the tree shows what
 * matches and the way to it, unfolded. The sync icon reads the folders
 * and the code again.
 *
 * As wide as the tree, until the edge is pulled: then the pulled width
 * (`--pylonWidth`, kept as `treeWidth` by discover/actions.php: the
 * `pylonWidth` of the state has a default and would never let it fit).
 * A GUI proposal for every pylon: .cursor/team/2026-10-30-pylon-breite.md
 *
 * @var \Systopic\System\Panels\Root\Cms\Controlmap\Panel $this
 */

use Systopic\System\Controlmap\DiscoveredPanel;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$discover = $this->childSelected instanceof \Systopic\System\Panels\Root\Cms\Controlmap\Discover\Panel ? $this->childSelected : NULL;
$showAll = $discover?->showAll ?? FALSE;
$filtering = $showAll && $discover->filter !== '';
$selected = $showAll ? NULL : $this->selectedPath;
$pylonWidth = (int) ($this->state->treeWidth ?? 0);
$openFolds = array_flip($this->openFolds);
$again = $showAll
	? './?all=1&filter=' . rawurlencode($discover->filter)
	: \Systopic\System\Panels\Root\Cms\Controlmap\Panel::nodeHref($this->selectedPath);

$fold = static function (?string $path, bool $open) use ($e): string {
	if ($path === NULL) {
		return "<a class='fold leaf'></a>";
	}
	$icon = $open ? 'fa-chevron-down' : 'fa-chevron-right';
	return "<a data-on_click=fold data-path='{$e($path)}' class='fold" . ($open ? ' open' : '') . "'><i class='fa-regular $icon'></i></a>";
};
$item = static function (string $path, string $label, string $icon, string $note, bool $plain, string $fold, string $below, bool $hit = false) use ($e, $selected): string {
	$classes = trim(($path === $selected ? 'active' : '') . ($plain ? ' plain' : ''));
	return "<li class='$classes'><label>$fold"
		. "<a class='label ajax' href='{$e(\Systopic\System\Panels\Root\Cms\Controlmap\Panel::nodeHref($path))}' title='{$e($path === '' ? 'All panels' : $path . '/')}'>"
		. "<i class='type fa-regular $icon'></i><span class='name" . ($hit ? ' is-match' : '') . "'>{$e($label)}</span>"
		. ($note !== '' ? "<span class='count'>{$e($note)}</span>" : '')
		. "</a></label>$below</li>";
};
$branch = static function (array $panels) use (&$branch, $item, $fold, $openFolds, $filtering, $discover): string {
	$html = '';
	/** @var DiscoveredPanel $panel */
	foreach ($panels as $panel) {
		// filtering: only what matches and the way to it, unfolded
		if ($filtering && !$discover->leadsToMatch($panel)) {
			continue;
		}
		$hasChildren = $panel->children !== [];
		$open = $hasChildren && ($filtering
			? array_filter($panel->children, $discover->leadsToMatch(...)) !== []
			: isset($openFolds[$panel->path]));
		$below = $open ? '<ul>' . $branch($panel->children) . '</ul>' : '';
		$count = count($panel->actions);
		$html .= $item($panel->path, $panel->name, 'fa-browser', $count > 0 ? (string) $count : '', $panel->isPlain,
			$fold($hasChildren ? $panel->path : NULL, $open), $below, $filtering && $discover->matches($panel));
	}
	return $html;
};
?>
<div class="pylon cms controlmapTree"<?= $pylonWidth > 0 ? " style=\"--pylonWidth: {$pylonWidth}px;\"" : '' ?>>
	<header>
		<ul class="treeNavi">
			<li><a class="ajax<?= $showAll ? ' active' : '' ?>" href="<?= $showAll ? './?all=0' : './?all=1' ?>" title="<?= $showAll ? 'Back to the panel chosen in the tree' : 'Search every panel' ?>"><i class="fa-regular fa-magnifying-glass"></i></a></li>
		</ul>
		<ul class="treeNavi">
			<li><a class="ajax put" href="openAllFolds" title="Open all folders"><i class="fa-regular fa-folder-open"></i></a></li>
			<li><a class="ajax put" href="closeAllFolds" title="Close all folders"><i class="fa-regular fa-folder-minus"></i></a></li>
			<li><a class="ajax" href="<?= $e($again) ?>" title="Discover: read the folders and the code again"><i class="fa-regular fa-sync-alt"></i></a></li>
		</ul>
	</header>
	<?php if ($showAll) { ?>
		<?php // not inside <nav>: after a render the renderer blurs whatever has the focus in `main nav`
		// (domRenderer.js) — rebuildAround has kept the field, that would take its focus.
		// Proposal: .cursor/team/2026-11-02-nav-blur.md ?>
		<div class="pylonTitle">
			<div class="searchInput">
				<input class="autosend" id="controlmapFilter" data-on_input="filterPanels" name="filter" type="search" autocomplete="off"
				       placeholder="Filter panels, roles, actions" aria-label="Filter panels" value="<?= $e($discover->filter) ?>">
			</div>
		</div>
	<?php } ?>
	<nav>
		<div class="tree">
			<ul class="tree">
				<?= $item('', $this->rootLabel, 'fa-sitemap', '', false, $fold(NULL, true), '<ul>' . $branch($this->panels) . '</ul>') ?>
			</ul>
		</div>
	</nav>
	<div data-drag_type='none' data-on_draginit='draginitPylonwidth'></div>
</div>
