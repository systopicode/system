<main class="backupCreate">
	<?= message::flush(); ?>
	<div class="backupCreateGrid">
		<div>
			<h2>Databases</h2>
			<ul class="checkBoxList">
				<li>
					<label class="checkbox disabled"><span>Site &amp; media structure</span><input type="checkbox" disabled="disabled" value="1" name="database_sites" class="switch tinyswitch"><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Newsletter</span><input type="checkbox" disabled="disabled" value="1" name="database_sites" class="switch tinyswitch"><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Shop</span><input type="checkbox" disabled="disabled" value="1" name="database_sites" class="switch tinyswitch"><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Users</span><input type="checkbox" disabled="disabled" checked="checked" value="1" name="database_sites" class="switch tinyswitch"><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Settings</span><input type="checkbox" disabled="disabled" value="1" name="database_sites" class="switch tinyswitch"><div><div></div></div></label>
				</li>
			</ul>
		</div>
		<div>
			<h2>Files</h2>
			<ul class="checkBoxList">
				<li>
					<label class="checkbox">
						<span>Media files</span>
						<input data-on_change="updateBackupSettings" value="1" type="checkbox" <?= $this->parent->state->backupFiles ? "checked='checked'" : "" ?> name="backupFiles" class="switch tinyswitch">
						<div><div></div></div>
					</label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Templates</span><input type="checkbox" disabled="disabled" value="1" name="database_sites" class="switch tinyswitch"><div><div></div></div></label>
				</li>
			</ul>
		</div>
	</div>
	<div class="inputGroup">
		<input type="text" data-on_input="updateBackupSettings" name="backupNotes" value="<?= htmlspecialchars((string) $this->parent->state->backupNotes, ENT_QUOTES, 'UTF-8') ?>">
		<span class="bar"></span>
		<label>Notes</label>
	</div>
</main>
<footer>
	<a data-on_click="createTaskList" class="button submit ajax"><i class="fa-regular fa-box-archive"></i><span>Create backup</span></a>
</footer>
