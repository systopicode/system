<?php
/** @var \Systopic\System\Panels\Root\Admin\Backup\Restore\Panel $this */
$name = (string) \http::get('name');
$contents = $this->parent->contentsOfBackup($name);
$state = $this->parent->state;
$chosen = [\Systopic\System\Panels\Root\Admin\Backup\Panel::class, 'chosen'];
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$superuser = \Systopic\System\Auth\Session::isSuperuser();
$preflight = $contents === null ? null : \Systopic\System\Backup\Preflight::inspect(\fs::toNative($this->parent->backupdirpath), $name, \fs::toNative($this->parent->tmpdirpath));
$backupDate = '';
foreach ($this->parent->backupfilestable ?? [] as $backupRecord) {
    if (($backupRecord['filenamebase'] ?? '') === $name) {
        $backupDate = (new \DateTimeImmutable($backupRecord['date']))->format(\Systopic\System\Sys\LocalTime::DATETIME_SECONDS);
        break;
    }
}
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
        <?php if ($preflight['errors'] || $preflight['warnings']): ?>
        <div class="cms message is-stay restoreWarnings">
        <?php foreach ($preflight['errors'] as $problem): ?><div class="error"><p class="message"><?= $e($problem) ?></p></div><?php endforeach; ?>
        <?php foreach ($preflight['warnings'] as $note): ?><div class="warning"><p class="message"><?= $e($note) ?></p></div><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="cms message is-stay restoreCheck"><div class="<?= !empty($preflight['errors']) ? 'error' : 'info' ?>"><p class="message"><strong><?= !empty($preflight['errors']) ? 'Archive checks failed.' : 'Archive structure checked.' ?></strong> A restore has not been tested. Structure checks do not verify SQL execution or every media file.</p></div></div>
        <details class="restoreArchive"><summary>Backup<?= $backupDate !== '' ? ' · ' . $e($backupDate) : ' file' ?></summary><code><?= $e($name) ?></code></details>
        <h2 class="restoreSelectionTitle">What to restore</h2>
        <p class="tagsHint">Choose which parts of this installation to replace.</p>
		<form id="backupRestoreForm" data-feedback="parameters" method="put" action="createTaskList?name=<?= urlencode($name) ?>">
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
						if (!$superuser || empty($contents[$which . 'Settings'])) {
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
					<?php if ($superuser && !empty($contents['environment'])) { ?>
						<li>
							<label class="checkbox">
								<span>Environment</span>
								<input data-on_change="updateRestoreSelection" value="1" type="checkbox" <?= $chosen($state, 'restoreEnvironment', false) ? "checked='checked'" : "" ?> name="restoreEnvironment" class="switch tinyswitch">
								<div><div></div></div>
							</label>
						</li>
					<?php } ?>
				</ul>
				<?php if ($superuser && (!empty($contents['environment']) || !empty($contents['pageSettings']) || !empty($contents['mediaSettings']))) { ?>
					<p class="tagsHint">Settings and environment are not selected by default. Including them can replace sign-in, mail and theme configuration.</p>
				<?php } ?>
			<?php } ?>
        </form>
        <div class="cms message is-stay restoreReplaceWarning"><div class="warning"><p class="message">Selected parts will overwrite current data. Changes made after this backup can be lost. Create a current backup before restoring.</p></div></div>
	<?php } ?>
</main>
<?php if ($contents !== null) { ?>
<footer>
	<button type="submit" name="action" value="createTaskList?name=<?= urlencode($name) ?>" form="backupRestoreForm" <?= !empty($preflight['errors']) ? 'disabled' : '' ?> class="button submit"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i><span>Restore</span></button>
</footer>
<?php } ?>
