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
 * The search filters the tree and table. The rescan reads the folders
 * and code again. The desktop resize handle persists treeWidth.
 *
 * @var \Systopic\System\Panels\Root\Admin\Access\Panelmap\Panel $this
 */

use Systopic\System\Controlmap\DiscoveredPanel;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$discover = $this->childSelected instanceof \Systopic\System\Panels\Root\Admin\Access\Panelmap\Discover\Panel ? $this->childSelected : NULL;
$showAll = $discover?->showAll ?? FALSE;
$filtering = $showAll && $discover->filter !== '';
$selected = $showAll ? NULL : $this->selectedPath;
$pylonWidth = (int) ($this->state->treeWidth ?? 0);
$openFolds = array_flip($this->openFolds);
$pylonInSheet ??= false;
$fold = static function (?string $path, bool $open) use ($e): string {
	if ($path === NULL) {
		return "<span class='fold leaf'></span>";
	}
	$icon = $open ? 'cmsIcon-chevron-down' : 'cmsIcon-chevron-right';
	return "<button type=button aria-label='Toggle children' aria-expanded='" . ($open ? "true" : "false") . "' data-on_click=fold data-path='{$e($path)}' class='fold" . ($open ? ' open' : '') . "'><i class='cmsIcon $icon'></i></button>";
};
$item = static function (string $path, string $label, string $icon, string $note, bool $plain, string $fold, string $below, bool $hit = false) use ($e, $selected, $pylonInSheet): string {
	$href = \Systopic\System\Panels\Root\Admin\Access\Panelmap\Panel::nodeHref($path);
	if ($pylonInSheet) $href .= '&detail=1';
	$classes = trim(($path === $selected ? 'active' : '') . ($plain ? ' plain' : ''));
	return "<li class='$classes'>$fold"
		. "<a class='label ajax' href='{$e($href)}' title='{$e($path === '' ? 'All panels' : $path . '/')}'>"
		. "<i class='type cmsIcon $icon'></i><span class='name" . ($hit ? ' is-match' : '') . "'>{$e($label)}</span>"
		. ($note !== '' ? "<span class='count'>{$e($note)}</span>" : '')
		. "</a>$below</li>";
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
		$html .= $item($panel->path, $panel->name, 'cmsIcon-file', $count > 0 ? (string) $count : '', $panel->isPlain,
			$fold($hasChildren ? $panel->path : NULL, $open), $below, $filtering && $discover->matches($panel));
	}
	return $html;
};
?>
<div class="pylon cms <?= empty($pylonInSheet) ? 'pylonSecondary' : 'pylonSheetTree' ?> controlmapTree"<?= $pylonWidth > 0 ? " style=\"--pylonWidth: {$pylonWidth}px;\"" : '' ?>>
	<header></header>
	<div class="pylonTitle pylonTreeToolbar">
		<div class="searchInput">
			<input class="autosend" id="<?= empty($pylonInSheet) ? 'controlmapFilter' : 'controlmapSheetFilter' ?>" data-focus-id="<?= empty($pylonInSheet) ? 'controlmap-filter' : 'controlmap-sheet-filter' ?>" data-on_input="filterPanels"
			       name="filter" type="search" autocomplete="off" placeholder="Search panels"
			       aria-label="Filter panels, roles and actions" value="<?= $showAll ? $e($discover->filter) : '' ?>">
		</div>
		<ul class="treeNavi">
			<li><a class="ajax put" href="openAllFolds" title="Open all folders" aria-label="Open all folders"><i class="cmsIcon cmsIcon-folder-open" aria-hidden="true"></i></a></li>
			<li><a class="ajax put" href="closeAllFolds" title="Close all folders" aria-label="Close all folders"><i class="cmsIcon cmsIcon-folder-minus" aria-hidden="true"></i></a></li>
			<li><a class="ajax" href="<?= $e($showAll
				? './?all=1&filter=' . rawurlencode($discover->filter)
				: ($pylonInSheet ? './?tree=1' : $this::nodeHref($this->selectedPath))) ?>" data-cms-refresh-view title="Discover panels again" aria-label="Discover panels again"><i class="cmsIcon cmsIcon-arrows-rotate" aria-hidden="true"></i></a></li>
		</ul>
	</div>
	<nav>
		<ul class="controlmapNav">
				<?= $item('', $this->rootLabel, 'cmsIcon-sitemap', '', false, $fold(NULL, true), '<ul>' . $branch($this->panels) . '</ul>') ?>
		</ul>
	</nav>
	<div class='pylonResizeHandle' data-drag_type='none' data-on_draginit='draginitPylonwidth' data-width-action="<?= $e(\http::$root . 'admin/access/panelmap/discover/updatePylonWidth') ?>"></div>
</div>
