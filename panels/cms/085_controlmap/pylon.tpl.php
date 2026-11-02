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
 * As wide as the tree, until the edge is pulled: then the pulled width
 * (`--pylonWidth`, kept as `treeWidth` by discover/actions.php: the
 * `pylonWidth` of the state has a default and would never let it fit).
 * A GUI proposal for every pylon: .cursor/team/2026-10-30-pylon-breite.md
 *
 * @var \Systopic\System\Panels\Root\Cms\Controlmap\Panel $this
 */

use Systopic\System\Controlmap\DiscoveredPanel;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$selected = $this->selectedPath;
$pylonWidth = (int) ($this->state->treeWidth ?? 0);
$openFolds = array_flip($this->openFolds);

$fold = static function (?string $path, bool $open) use ($e): string {
	if ($path === NULL) {
		return "<a class='fold leaf'></a>";
	}
	$icon = $open ? 'fa-chevron-down' : 'fa-chevron-right';
	return "<a data-on_click=fold data-path='{$e($path)}' class='fold" . ($open ? ' open' : '') . "'><i class='fa-regular $icon'></i></a>";
};
$item = static function (string $path, string $label, string $icon, string $note, bool $plain, string $fold, string $below) use ($e, $selected): string {
	$classes = trim(($path === $selected ? 'active' : '') . ($plain ? ' plain' : ''));
	return "<li class='$classes'><label>$fold"
		. "<a class='label ajax' href='{$e(\Systopic\System\Panels\Root\Cms\Controlmap\Panel::nodeHref($path))}' title='{$e($path === '' ? 'All panels' : $path . '/')}'>"
		. "<i class='type fa-regular $icon'></i>{$e($label)}"
		. ($note !== '' ? "<span class='count'>{$e($note)}</span>" : '')
		. "</a></label>$below</li>";
};
$branch = static function (array $panels) use (&$branch, $item, $fold, $openFolds): string {
	$html = '';
	/** @var DiscoveredPanel $panel */
	foreach ($panels as $panel) {
		$hasChildren = $panel->children !== [];
		$open = $hasChildren && isset($openFolds[$panel->path]);
		$below = $open ? '<ul>' . $branch($panel->children) . '</ul>' : '';
		$count = count($panel->actions);
		$html .= $item($panel->path, $panel->name, 'fa-browser', $count > 0 ? (string) $count : '', $panel->isPlain,
			$fold($hasChildren ? $panel->path : NULL, $open), $below);
	}
	return $html;
};
?>
<div class="pylon cms controlmapTree"<?= $pylonWidth > 0 ? " style=\"--pylonWidth: {$pylonWidth}px;\"" : '' ?>>
	<header>
		<ul class="treeNavi">
			<li><a class="ajax put" href="openAllFolds" title="Open all folders"><i class="fa-regular fa-folder-open"></i></a></li>
			<li><a class="ajax put" href="closeAllFolds" title="Close all folders"><i class="fa-regular fa-folder-minus"></i></a></li>
		</ul>
	</header>
	<nav>
		<div class="tree">
			<ul class="tree">
				<?= $item('', $this->rootLabel, 'fa-sitemap', '', false, $fold(NULL, true), '<ul>' . $branch($this->panels) . '</ul>') ?>
			</ul>
		</div>
	</nav>
	<div data-drag_type='none' data-on_draginit='draginitPylonwidth'></div>
</div>
