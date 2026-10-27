<?php
/** @var \Systopic\System\Panels\Root\Cms\Backup\Restore\Panel $this */
$name = (string) \http::get('name');
$contents = $this->parent->contentsOfBackup($name);
$state = $this->parent->state;
$chosen = [\Systopic\System\Panels\Root\Cms\Backup\Panel::class, 'chosen'];
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$showList = false;
if (is_array($contents)) {
	$showList = !empty($contents['database']) || !empty($contents['media']) || !empty($contents['environment']);
	foreach ($this::SETTING_LABELS as $which => $label) {
		if (!empty($contents[$which . 'Settings'])) {
			$showList = true;
		}
	}
}
?>
<main class="backupConfirm">
	<?= message::flush(); ?>
	<?php if ($contents === null) { ?>
		<p>This backup was not found.</p>
	<?php } else { ?>
		<p>Restore this backup? The selected parts replace what is on this server.</p>
		<p class="backupConfirmName"><?= $e($name) ?></p>
		<hr>
		<form id="backupRestoreForm" method="put" action="createTaskList?name=<?= urlencode($name) ?>">
			<?php if ($showList) { ?>
				<ul class="checkBoxList">
					<?php if (!empty($contents['database'])) { ?>
						<li>
							<label class="checkbox">
								<span>Database</span>
								<input data-on_change="updateRestoreSelection" value="1" type="checkbox" <?= $chosen($state, 'restoreDatabase', true) ? "checked='checked'" : "" ?> name="restoreDatabase" class="switch tinyswitch">
								<div><div></div></div>
							</label>
						</li>
					<?php } ?>
					<?php if (!empty($contents['media'])) { ?>
						<li>
							<label class="checkbox">
								<span>Media files</span>
								<input data-on_change="updateRestoreSelection" value="1" type="checkbox" <?= $chosen($state, 'restoreMedia', false) ? "checked='checked'" : "" ?> name="restoreMedia" class="switch tinyswitch">
								<div><div></div></div>
							</label>
						</li>
					<?php } ?>
					<?php foreach ($this::SETTING_LABELS as $which => $label) {
						if (empty($contents[$which . 'Settings'])) {
							continue;
						}
						$key = $this::settingKey($which, true);
						?>
						<li>
							<label class="checkbox">
								<span><?= $e($label) ?></span>
								<input data-on_change="updateRestoreSelection" value="1" type="checkbox" <?= $chosen($state, $key, false) ? "checked='checked'" : "" ?> name="<?= $e($key) ?>" class="switch tinyswitch">
								<div><div></div></div>
							</label>
						</li>
					<?php } ?>
					<?php if (!empty($contents['environment'])) { ?>
						<li>
							<label class="checkbox">
								<span>Environment</span>
								<input data-on_change="updateRestoreSelection" value="1" type="checkbox" <?= $chosen($state, 'restoreEnvironment', false) ? "checked='checked'" : "" ?> name="restoreEnvironment" class="switch tinyswitch">
								<div><div></div></div>
							</label>
						</li>
					<?php } ?>
				</ul>
			<?php } ?>
		</form>
	<?php } ?>
</main>
<?php if ($contents !== null) { ?>
<footer>
	<button type="submit" name="action" value="createTaskList?name=<?= urlencode($name) ?>" form="backupRestoreForm" class="button submit"><i class="fa-regular fa-arrow-rotate-left"></i><span>Restore</span></button>
</footer>
<?php } ?>
