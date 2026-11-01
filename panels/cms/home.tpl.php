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
$layout = $this->dashboardLayout();
['order' => $order, 'hidden' => $hidden] = $layout;
$order = $layout['layouts'][3] ?? $order;
$columns = $this->dashboardColumns(array_keys($widgets), $order);
$sorted = array_merge(...$columns);
$layouts = [];
foreach ($layout['layouts'] as $count => $savedOrder) {
    $layouts[$count] = \Systopic\System\Dashboard\Layout::order(\Systopic\System\Dashboard\Layout::columns(array_keys($widgets), $savedOrder, (int) $count));
}
$layouts[3] = \Systopic\System\Dashboard\Layout::order($columns);
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
			<p><?= $e($now->format(\Systopic\System\Sys\LocalTime::DATE)) ?></p>
		</div>
		<nav>
			<button type="button" class="button dashHeadMore" data-cms-more aria-label="More" aria-expanded="false"><i class="cmsIcon cmsIcon-ellipsis"></i></button>
			<div class="cmsPageToolsMenu dashHeadMenu">
				<button type="button" class="button" data-on_click="dashEdit"><i class="cmsIcon <?= $editing ? 'cmsIcon-check' : 'cmsIcon-grid-2' ?>"></i><span><?= $editing ? 'Done' : 'Customize' ?></span></button>
				<a class="button cmsExternalLink" href="<?= $e($site) ?>" target="_blank" rel="noopener"><span>View website</span><i class="cmsIcon cmsIcon-up-right-from-square" aria-hidden="true"></i></a>
			</div>
		</nav>
	</header>
	<?php include __DIR__ . '/home.notices.tpl.php'; ?>
	<?php if ($editing) {
		$off = array_values(array_intersect($sorted, $hidden));
		?>
		<div class="dashCustomize" data-drop_accept="widget" data-on_drop="hideDroppedWidget">
			<p>This layout is personal to your account. Hidden widgets remain available to add again.</p>
			<p class="dashHidden">
				<?php if ($off !== []) { ?><span>Add:</span><?php } ?>
				<?php foreach ($off as $key) { ?>
					<button type="button" class="button" data-drag_type="widget" data-on_click="showWidget" data-widget="<?= $e($key) ?>"><?= $title($widgets[$key]['file']) ?></button>
				<?php } ?>
			</p>
		</div>
	<?php } ?>
	<div class="widgets dashWidgets<?= $editing ? ' is-editing' : '' ?>" data-layouts="<?= $e(json_encode($layouts)) ?>">
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
							<button type="button" class="button transparent icononly dashHide" data-on_click="hideWidget" title="Hide"><i class="cmsIcon cmsIcon-xmark"></i></button>
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
		<span>© <?= date('Y') ?> systopic GmbH · <a class="ajax" href="<?= $e(\http::$root . 'cms/legal/') ?>?page=releases" title="Release notes">GUI <?= $e($gui) ?><?= $revision !== '' ? ' · ' . $e($revision) : '' ?></a></span>
		<nav>
			<a class="ajax" href="<?= $e(\http::$root . 'cms/legal/') ?>?page=license"><i class="cmsIcon cmsIcon-file-contract"></i> License</a>
			<a class="ajax" href="<?= $e(\http::$root . 'cms/legal/') ?>?page=imprint"><i class="cmsIcon cmsIcon-building"></i> Imprint</a>
			<a class="ajax" href="<?= $e(\http::$root . 'cms/legal/') ?>?page=privacy"><i class="cmsIcon cmsIcon-shield-halved"></i> Privacy</a>
			<a href="https://fluxo-help.1f2c.de" target="_blank" rel="noopener"><i class="cmsIcon cmsIcon-question-circle"></i> Help</a>
		</nav>
	</footer>
</div>
