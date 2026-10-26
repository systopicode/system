<?php
/**
 * cms/ - the dashboard. The widgets come from the panels (`dashboard.tpl.php`,
 * `dashboard.<name>.tpl.php`, see Panel::dashboardWidgets()); each starts with
 * its `<header><h2>`, which also names it in the list of hidden widgets.
 *
 * @var \Systopic\System\Panels\Root\Cms\Panel $this
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$widgets = $this->dashboardWidgets();
['order' => $order, 'hidden' => $hidden] = $this->dashboardLayout();
$columns = $this->dashboardColumns(array_keys($widgets), $order);
$sorted = array_merge(...$columns);
$this->state->dashboard = array_merge(...array_map(static fn(array $column, int $i): array => $i === 0 ? $column : ['|', ...$column], $columns, array_keys($columns)));
$editing = !empty($this->state->dashEdit);
$title = static function (string $file): string {
	preg_match('~<h2[^>]*>(.*?)</h2>~s', (string) file_get_contents($file), $m);
	return trim(strip_tags(preg_replace('~<\?.*?\?>~s', '', $m[1] ?? ''), '<i>'));
};
$name = trim((string) \Systopic\System\Auth\Session::current()->fullname);
$first = $name === '' ? '' : ', ' . strtok($name, ' ');
$now = \Systopic\System\Sys\LocalTime::now();
$hour = (int) $now->format('G');
$greeting = $hour >= 5 && $hour < 12 ? 'Good morning' : ($hour >= 12 && $hour < 18 ? 'Good afternoon' : 'Good evening');
$site = rtrim((string) \http::$root, '/') . '/';
$gui = (string) array_key_first(\Systopic\System\Sys\Release::notes());
$revision = \Systopic\System\Sys\Release::revision();
?>
<div class="dashboard">
	<header class="dashHead">
		<div>
			<h1><?= $e($greeting . $first) ?></h1>
			<p><?= $e($now->format('l, j F Y')) ?></p>
		</div>
		<nav>
			<button type="button" class="button dashHeadMore" data-cms-more aria-label="More" aria-expanded="false"><i class="fa-regular fa-ellipsis"></i></button>
			<div class="cmsPageToolsMenu dashHeadMenu">
				<button type="button" class="button" data-on_click="dashEdit"><i class="fa-regular <?= $editing ? 'fa-check' : 'fa-grid-2' ?>"></i><span><?= $editing ? 'Done' : 'Customize' ?></span></button>
				<a class="button" href="<?= $e($site) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-up-right-from-square"></i><span>View website</span></a>
			</div>
		</nav>
	</header>
	<?php if ($editing) {
		$off = array_values(array_intersect($sorted, $hidden));
		?>
		<div class="dashCustomize">
			<p>Drag a widget by its title to move it, the cross hides it.</p>
			<?php if ($off !== []) { ?>
				<p class="dashHidden">
					<span>Add:</span>
					<?php foreach ($off as $key) { ?>
						<button type="button" class="button" data-on_click="showWidget" data-widget="<?= $e($key) ?>"><i class="fa-regular fa-plus"></i><?= $title($widgets[$key]['file']) ?></button>
					<?php } ?>
				</p>
			<?php } ?>
		</div>
	<?php } ?>
	<div class="widgets dashWidgets<?= $editing ? ' is-editing' : '' ?>">
		<?php foreach ($columns as $column => $keys) { ?>
			<div class="dashColumn">
				<?php foreach ($keys as $key) {
					if (in_array($key, $hidden, true)) {
						continue;
					}
					['panel' => $panel, 'file' => $file] = $widgets[$key];
					?>
					<section class="widget dashWidget" data-widget="<?= $e($key) ?>"<?= $editing ? ' data-drag_type="widget"' : '' ?>>
						<?php if ($editing) { ?>
							<div class="sort v insertBefore" data-drop_accept="widget" data-on_drop="insertWidgetBefore"></div>
							<div class="sort v insertAfter" data-drop_accept="widget" data-on_drop="insertWidgetAfter"></div>
							<button type="button" class="dashHide" data-on_click="hideWidget" title="Hide"><i class="fa-regular fa-xmark"></i></button>
						<?php } ?>
						<?php $panel->render($file); ?>
					</section>
				<?php } ?>
				<?php if ($editing) { ?>
					<div class="dashDrop" data-drop_accept="widget" data-on_drop="insertWidgetEnd" data-column="<?= $column ?>"></div>
				<?php } ?>
			</div>
		<?php } ?>
	</div>
	<footer class="authFooter dashFooter">
		<span>© <?= date('Y') ?> systopic GmbH · <a class="ajax" href="legal/?page=releases" title="Release notes">GUI <?= $e($gui) ?><?= $revision !== '' ? ' · ' . $e($revision) : '' ?></a></span>
		<nav>
			<a class="ajax" href="legal/?page=license"><i class="fa-regular fa-file-contract"></i> License</a>
			<a class="ajax" href="legal/?page=imprint"><i class="fa-regular fa-building"></i> Imprint</a>
			<a class="ajax" href="legal/?page=privacy"><i class="fa-regular fa-shield-halved"></i> Privacy</a>
			<a href="https://fluxo-help.1f2c.de" target="_blank" rel="noopener"><i class="fa-regular fa-question-circle"></i> Help</a>
		</nav>
	</footer>
</div>
