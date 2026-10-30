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

$head = '<thead><tr><th>Panel</th><th>From</th><th>Access in code</th><th>Actions</th></tr></thead>';
$selected = $map->selected;
?>
<?php if ($this->showAll) { ?>
	<h1>All panels</h1>
	<p class="tagsHint">
		<?= count($all) ?> panels, <?= $actionCount ?> actions, <?= $entries ?> of them open from outside;
		<?= $granting ?> panels decide access in their own code.
		Read off the folders and the code of this installation on every view, nothing is stored.
	</p>
	<section class="widget is-list discover">
		<header>
			<h2>Panels</h2>
			<input type="search" class="discoverFilter" placeholder="Filter panels and actions" autocomplete="off"
				   oninput="const q = this.value.trim().toLowerCase(); this.closest('section').querySelectorAll('tbody tr').forEach(r => r.hidden = q !== '' && !r.textContent.toLowerCase().includes(q))">
		</header>
		<div class="tableScroll">
			<table class="discoverList">
				<?= $head ?>
				<tbody>
					<?php foreach ($all as $panel) {
						echo $this->row($panel, $panel->depth);
					} ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php return;
} ?>

<h1><?= $e($selected?->path ?? $map->rootLabel) ?></h1>
<p class="tagsHint">
	<?php if ($selected === NULL) { ?>
		The top-level panels of this installation: <?= count($all) ?> panels, <?= $actionCount ?> actions in all.
	<?php } else { ?>
		The panel chosen in the tree and the <?= count($selected->children) ?> panels one level below it.
	<?php } ?>
	Read off the folders and the code on every view, nothing is stored.
</p>

<?php if ($selected !== NULL) { ?>
	<section class="widget is-list discover">
		<header><h2>This panel</h2></header>
		<div class="tableScroll">
			<table class="discoverList">
				<?= $head ?>
				<tbody><?= $this->row($selected) ?></tbody>
			</table>
		</div>
	</section>
<?php } ?>

<section class="widget is-list discover">
	<header><h2><?= $selected === NULL ? 'Panels' : 'Below' ?></h2></header>
	<?php if ($map->selectedChildren === []) { ?>
		<p class="tagsHint">No panels below.</p>
	<?php } else { ?>
		<div class="tableScroll">
			<table class="discoverList">
				<?= $head ?>
				<tbody>
					<?php foreach ($map->selectedChildren as $panel) {
						echo $this->row($panel);
					} ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
