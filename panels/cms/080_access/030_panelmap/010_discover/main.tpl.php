<?php
/**
 * The node chosen in the tree and the panels one level below it — or, with
 * "Show all", every panel depth first (`Controlmap\Discover`).
 *
 * A chip per action; its title says where it was found. An entry action
 * (the panel's `$entryActions`) may be opened from outside by GET and is
 * marked; every other one runs only from this site's own pages
 * (ActionGuard).
 *
 * @var \Systopic\System\Panels\Root\Cms\Access\Panelmap\Discover\Panel $this
 */

use Systopic\System\Controlmap\DiscoveredPanel;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$map = $this->parent;

/** @var list<DiscoveredPanel> $all */
$all = [];
foreach ($map->panels as $top) {
	foreach ($top->all() as $panel) {
		$all[] = $panel;
	}
}
$actionCount = array_sum(array_map(static fn(DiscoveredPanel $p) => count($p->actions), $all));
$entries = 0;
foreach ($all as $panel) {
	foreach ($panel->actions as $action) {
		$entries += $action->entry ? 1 : 0;
	}
}
$granting = count(array_filter($all, static fn(DiscoveredPanel $p) => $p->grants !== []));

$card = static function (string $title, string $rows, bool $gap = false) use ($e): string {
	return '<tbody class="tableWidget">'
		. ($gap ? '<tr class="tableWidgetGap" aria-hidden="true"><td colspan="4"></td></tr>' : '')
		. '<tr class="tableWidgetTitle"><th scope="rowgroup" colspan="4"><h2>'
		. $e($title) . '</h2></th></tr>'
		. '<tr class="tableWidgetColumns"><th scope="col">Panel</th><th scope="col">From</th>'
		. '<th scope="col">Access in code</th><th scope="col">Actions</th></tr>' . $rows . '</tbody>';
};
$table = static function (string $groups): string {
	return '<div class="discoverLayout"><div class="tableScroll tableWidgets"><table class="discoverList tableWidgetGrid">'
		. $groups . '</table></div></div>';
};
$selected = $map->selected;
?>
<h1><?= $e($this->showAll ? 'All panels' : ($selected?->path !== NULL ? $selected->path . '/' : $map->rootLabel)) ?></h1>
<?php if ($this->showAll) { ?>
	<p class="tagsHint">
		<?= count($all) ?> panels, <?= $actionCount ?> actions, <?= $entries ?> of them open from outside;
		<?= $granting ?> panels decide access in their own code.
		Read off the folders and the code of this installation on every view, nothing is stored.
	</p>
	<?php
	// the field above the tree filters (matches()); what matched is marked in the row
	$rows = '';
	$shown = 0;
	foreach ($all as $panel) {
		if ($this->matches($panel)) {
			$rows .= $this->row($panel, $panel->depth);
			$shown++;
		}
	}
	if ($rows === '') {
		$rows = "<tr><td colspan='4' class='none'>No panel has „{$e($this->filter)}“.</td></tr>";
	}
	$title = $this->filter === '' ? 'Panels' : "Panels with „{$e($this->filter)}“: $shown";
	?>
	<?= $table($card($title, $rows)) ?>
	<?php return;
} ?>

<p class="tagsHint">
	<?php if ($selected === NULL) { ?>
		The top-level panels of this installation: <?= count($all) ?> panels, <?= $actionCount ?> actions in all.
	<?php } else { ?>
		The panel chosen in the tree and the <?= count($selected->children) ?> panels one level below it.
	<?php } ?>
	Read off the folders and the code on every view, nothing is stored.
</p>

<?php
$below = '';
foreach ($map->selectedChildren as $panel) {
	$below .= $this->row($panel);
}
if ($below === '') {
	$below = "<tr><td colspan='4' class='none'>No panels below.</td></tr>";
}
?>
<?= $table(($selected === NULL ? '' : $card('This panel', $this->row($selected)))
	. $card($selected === NULL ? 'Panels' : 'Below', $below, $selected !== NULL)) ?>
