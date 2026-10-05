<main>
	<?= message::flush(); ?>
    <div class="layout">
		<div class="halfColumn">
			<h2>Databases</h2>
			<ul class="checkBoxList">
				<li>
					<label class="checkbox disabled"><span>Site & Media Structure</span><input  type="checkbox" disabled="disabled" value='1' name='database_sites' class="switch tinyswitch" /><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Newsletter</span><input  type="checkbox" disabled="disabled" value='1' name='database_sites' class="switch tinyswitch" /><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Shop</span><input  type="checkbox" disabled="disabled" value='1' name='database_sites' class="switch tinyswitch" /><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>User</span><input  type="checkbox" disabled="disabled" checked='checked' value='1' name='database_sites' class="switch tinyswitch" /><div><div></div></div></label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Settings</span><input  type="checkbox" disabled="disabled" value='1' name='database_sites' class="switch tinyswitch" /><div><div></div></div></label>
				</li>
			</ul>
		</div>
		<div class="halfColumn">
			<h2>Files</h2>
			<ul class="checkBoxList">
				<li>
					<label class="checkbox">
						<span>Media Files</span>
						<input data-on_change="updateBackupSettings" value='1' type="checkbox" <?= $this->parent->state->backupFiles ? "checked='checked'" : "" ?> name='backupFiles' class="switch tinyswitch" />
						<div><div></div></div>
					</label>
				</li>
				<li>
					<label class="checkbox disabled"><span>Templates</span><input  type="checkbox" disabled="disabled" value='1' name='database_sites' class="switch tinyswitch" /><div><div></div></div></label>
				</li>
			</ul>
		</div>
		<div class="fullColumn">
			<br clear="all">
			<h2>Notes</h2>
			<input type="text" data-on_input="updateBackupSettings" name="backupNotes" value="<?= $this->parent->state->backupNotes ?>"/>
		</div>
	</div>
</main>
<footer>
    <a data-on_click="createTaskList" class="button submit ajax"><span>Create Backup</span></a>
</footer>

