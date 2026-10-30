<?php
/**
 * Every panel of the installation, depth first, with what its class asks
 * for access and the actions its code names (`Controlmap\Discover`).
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

/** @var list<DiscoveredPanel> $all */
$all = [];
foreach ($this->panels as $top) {
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

$grantChips = static function (?array $terms) use ($e): string {
	if ($terms === null) {
		return '';
	}
	return implode('', array_map(static fn(string $t) => "<span class='term" . (in_array($t, ['custom', 'parent', 'superuser', 'signed in', 'everyone', 'nobody'], true) ? ' is-' . str_replace(' ', '', $t) : '') . "'>{$e($t)}</span>", $terms));
};
?>
<h1>Discover</h1>
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
			<thead>
				<tr><th>Panel</th><th>From</th><th>Access in code</th><th>Actions</th></tr>
			</thead>
			<tbody>
				<?php foreach ($all as $panel) {
					$classTitle = $panel->className === null
						? 'No class file: the parent bequeaths its class, or PanelNode'
						: $panel->className . ($panel->extends !== null ? ' extends ' . $panel->extends : '') . ' (' . $panel->classFile . ')';
					?>
					<tr class="<?= $panel->isPlain ? 'plain' : '' ?>" style="--depth: <?= $panel->depth ?>">
						<td class="panel" title="<?= $e($classTitle) ?>">
							<span class="name"><?= $e($panel->name) ?></span>
							<span class="path"><?= $e($panel->path) ?></span>
						</td>
						<td class="origin">
							<?php foreach ($panel->origins as $origin) { ?>
								<span class="origin<?= $origin === $panel->origin ? ' is-winning' : '' ?>"
									  title="<?= $origin === $panel->origin ? 'Class and templates come from here' : 'Contributes folders' ?>"><?= $e($origin) ?></span>
							<?php } ?>
						</td>
						<td class="grants">
							<?php if (isset($panel->grants['build'])) { ?>
								<div title="buildGranted(): who sees the panel"><i class="fa-regular fa-eye"></i><?= $grantChips($panel->grants['build']) ?></div>
							<?php }
							if (isset($panel->grants['action'])) { ?>
								<div title="actionGranted(): who runs its actions"><i class="fa-regular fa-bolt"></i><?= $grantChips($panel->grants['action']) ?></div>
							<?php }
							if ($panel->grants === [] && !$panel->isPlain) { ?>
								<span class="none" title="Not overridden: the parent class decides (PanelNode: anybody signed in)">inherited</span>
							<?php } ?>
						</td>
						<td class="actions">
							<?php foreach ($panel->actions as $action) { ?>
								<span class="action<?= $action->entry ? ' is-entry' : '' ?>"
									  title="<?= $e(implode(', ', $action->kinds) . ' — ' . implode(', ', $action->places) . ($action->entry ? ' — may be opened from outside (GET)' : '')) ?>"><?php if ($action->entry) { ?><i class="fa-regular fa-arrow-right-to-bracket"></i><?php } ?><?= $e($action->name) ?></span>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</section>
