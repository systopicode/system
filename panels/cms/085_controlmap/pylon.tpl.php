<?php
/**
 * The panel tree of the installation under one root — every node a get to
 * the open child (`./?panel=…`), which shows that node's level. No folding:
 * the whole tree is open.
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

$item = static function (string $path, string $label, string $icon, string $note, bool $plain, string $below) use ($e, $selected): string {
	$classes = trim(($path === $selected ? 'active' : '') . ($plain ? ' plain' : ''));
	return "<li class='$classes'><label><a class='fold leaf'></a>"
		. "<a class='label ajax' href='./?panel=" . rawurlencode($path) . "' title='{$e($path === '' ? 'All panels' : $path)}'>"
		. "<i class='type fa-regular $icon'></i>{$e($label)}"
		. ($note !== '' ? "<span class='count'>{$e($note)}</span>" : '')
		. "</a></label>$below</li>";
};
$branch = static function (array $panels) use (&$branch, $item): string {
	$html = '';
	/** @var DiscoveredPanel $panel */
	foreach ($panels as $panel) {
		$below = $panel->children === [] ? '' : '<ul>' . $branch($panel->children) . '</ul>';
		$icon = $panel->children === [] ? 'fa-file' : 'fa-folder';
		$count = count($panel->actions);
		$html .= $item($panel->path, $panel->name, $icon, $count > 0 ? (string) $count : '', $panel->isPlain, $below);
	}
	return $html;
};
?>
<div class="pylon cms controlmapTree"<?= $pylonWidth > 0 ? " style=\"--pylonWidth: {$pylonWidth}px;\"" : '' ?>>
	<header></header>
	<nav>
		<div class="tree">
			<ul class="tree">
				<?= $item('', $this->rootLabel, 'fa-sitemap', '', false, '<ul>' . $branch($this->panels) . '</ul>') ?>
			</ul>
		</div>
	</nav>
	<div data-drag_type='none' data-on_draginit='draginitPylonwidth'></div>
</div>
