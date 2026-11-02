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
 * All of it is one table, each white card a `tbody.card`: so the columns
 * are as wide in "This panel" as in "Below" (discover.css, `cardTable`).
 *
 * @var \Systopic\System\Panels\Root\Cms\Controlmap\Discover\Panel $this
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

// one card: its title row, the column heads, the rows
$card = static function (string $title, string $rows, string $tools = ''): string {
	return "<tbody class='card'>"
		. "<tr class='cardHead'><th colspan='4'><header><h2>$title</h2>$tools</header></th></tr>"
		. "<tr class='cardColumns'><th>Panel</th><th>From</th><th>Access in code</th><th>Actions</th></tr>"
		. $rows
		. '</tbody>';
};
$gap = "<tbody class='cardGap'><tr><td colspan='4'></td></tr></tbody>";
$selected = $map->selected;
?>
<?php if ($this->showAll) { ?>
	<h1>All panels</h1>
	<p class="tagsHint">
		<?= count($all) ?> panels, <?= $actionCount ?> actions, <?= $entries ?> of them open from outside;
		<?= $granting ?> panels decide access in their own code.
		Read off the folders and the code of this installation on every view, nothing is stored.
	</p>
	<?php
	$rows = '';
	foreach ($all as $panel) {
		$rows .= $this->row($panel, $panel->depth);
	}
	$filter = '<input type="search" class="discoverFilter" placeholder="Filter panels and actions" autocomplete="off"'
		. ' oninput="const q = this.value.trim().toLowerCase(); this.closest(\'tbody\').querySelectorAll(\'tr:not(.cardHead, .cardColumns)\').forEach(r => r.hidden = q !== \'\' &amp;&amp; !r.textContent.toLowerCase().includes(q))">';
	?>
	<table class="discoverList cardTable"><?= $card('Panels', $rows, $filter) ?></table>
	<?php return;
} ?>

<h1><?= $e($selected === NULL ? $map->rootLabel : $selected->path . '/') ?></h1>
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
<table class="discoverList cardTable">
	<?php if ($selected !== NULL) {
		echo $card('This panel', $this->row($selected)) . $gap;
	} ?>
	<?= $card($selected === NULL ? 'Panels' : 'Below', $below) ?>
</table>
