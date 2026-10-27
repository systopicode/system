<?php
/** @var \Systopic\System\Panels\Root\Cms\Backup\Create\Panel $this */
$state = $this->parent->state;
$chosen = [\Systopic\System\Panels\Root\Cms\Backup\Panel::class, 'chosen'];
$hasEnvironment = $this->environmentArchiveMap() !== [];
?>
<main class="backupCreate">
	<?= message::flush(); ?>
	<form id="backupCreateForm" method="put" action="createTaskList">
	<ul class="checkBoxList">
		<li>
			<label class="checkbox">
				<span>Database</span>
				<input type="checkbox" checked disabled class="switch tinyswitch">
				<div><div></div></div>
			</label>
		</li>
		<li>
			<label class="checkbox">
				<span>Media files</span>
				<input data-on_change="updateBackupSettings" value="1" type="checkbox" <?= $state->backupFiles ? "checked='checked'" : "" ?> name="backupFiles" class="switch tinyswitch">
				<div><div></div></div>
			</label>
		</li>
		<?php foreach ($this::SETTING_LABELS as $which => $label) {
			if (\Systopic\System\Settings\ProjectFile::path($which) === null) {
				continue;
			}
			$key = $this::settingKey($which, false);
			?>
			<li>
				<label class="checkbox">
					<span><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
					<input data-on_change="updateBackupSettings" value="1" type="checkbox" <?= $chosen($state, $key, true) ? "checked='checked'" : "" ?> name="<?= htmlspecialchars($key, ENT_QUOTES) ?>" class="switch tinyswitch">
					<div><div></div></div>
				</label>
			</li>
		<?php } ?>
		<?php if ($hasEnvironment) { ?>
			<li>
				<label class="checkbox">
					<span>Environment</span>
					<input data-on_change="updateBackupSettings" value="1" type="checkbox" <?= $chosen($state, 'backupEnvironment', true) ? "checked='checked'" : "" ?> name="backupEnvironment" class="switch tinyswitch">
					<div><div></div></div>
				</label>
			</li>
		<?php } ?>
	</ul>
	<div class="inputGroup">
		<input type="text" data-on_input="updateBackupSettings" name="backupNotes" value="<?= htmlspecialchars((string) $state->backupNotes, ENT_QUOTES, 'UTF-8') ?>">
		<span class="bar"></span>
		<label>Notes</label>
	</div>
	</form>
</main>
<footer>
	<button type="submit" name="action" value="createTaskList" form="backupCreateForm" class="button submit"><i class="fa-regular fa-box-archive"></i><span>Create backup</span></button>
</footer>
