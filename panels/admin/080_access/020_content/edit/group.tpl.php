<?php
/** @var \Systopic\System\Panels\Root\Admin\Access\Content\Edit\Panel $this */

$group = $this->group;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$field = static function (string $label, string $type, string $action, ?string $value) use ($e, $group): string {
	return '<label class="field"><span>' . $label . '</span><input type="' . $type . '" autocapitalize="off" data-group_id="' . (int) $group->id . '" name="value" data-on_blur="' . $action . '" value="' . $e($value) . '" autocomplete="off" spellcheck="off"></label>';
};
$color = ($group->color !== null && $group->color !== '') ? $group->color : '#8a8f93';
?>
<header class="tagEditTitle">
	<h1><?= $e($group->name ?: 'Group') ?></h1>
	<button type="button" class="tagGroupRename" onclick="renameUserAdminTitle(this)" data-rename-url="<?= $e($this->href . 'updateGroupName?group_id=' . (int) $group->id) ?>" title="Rename" aria-label="Rename"><i class="cmsIcon cmsIcon-pen"></i></button>
	<ul class="barmenu">
		<?php if ((int) $group->id !== 1) { ?><li><button type="button" class="button icononly" aria-label="Remove" title="Remove" data-on_click="userConfirm" data-action="removeGroup" data-confirm="Delete this group? This cannot be undone." data-group_id="<?= (int) $group->id ?>"><i class="cmsIcon cmsIcon-trash"></i></button></li><?php } ?>
		<li><a class="ajax" href="../" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li>
	</ul>
</header>
<main class="groupEdit">
	<?= \message::flush() ?>
	<div class="fieldGrid is-stack">
		<?= $field('Description', 'text', 'updateGroupDescription', (string) $group->description) ?>
		<label class="field roleColor">
			<span>Color</span>
			<span class="colorChoice">
				<input type="color" data-group_id="<?= (int) $group->id ?>" name="value" data-on_change="updateGroupColor" value="<?= $e($color) ?>">
			</span>
		</label>
	</div>
	<section class="groupRights">
		<div class="rightsHead">
			<h2>Roles</h2>
			<p class="tagsHint">Read allows viewing this group; Write allows making changes. Assigned accounts receive the permissions of their roles.</p>
		</div>
		<div>
			<h2>Read</h2>
			<div class="picks">
				<?php foreach ($this->admin->roles as $role) {
					$on = (bool) ($this->admin->permission($group, $role)?->read ?? false);
					$roleColor = ($role->color !== null && $role->color !== '') ? $role->color : '#8a8f93';
					echo '<div class="tag' . ($on ? ' active' : '') . '" data-on_click="toggleGroupRole" data-right="read" data-role_id="' . (int) $role->id . '" data-group_id="' . (int) $group->id . '" style="--tag-color:' . $e($roleColor) . ';--tag-bg:' . $e($roleColor) . ';background:' . $e($roleColor) . '">' . $e($role->name) . '</div>';
				} ?>
			</div>
		</div>
		<div>
			<h2>Write</h2>
			<div class="picks">
				<?php foreach ($this->admin->roles as $role) {
					$on = (bool) ($this->admin->permission($group, $role)?->write ?? false);
					$roleColor = ($role->color !== null && $role->color !== '') ? $role->color : '#8a8f93';
					echo '<div class="tag' . ($on ? ' active' : '') . '" data-on_click="toggleGroupRole" data-right="write" data-role_id="' . (int) $role->id . '" data-group_id="' . (int) $group->id . '" style="--tag-color:' . $e($roleColor) . ';--tag-bg:' . $e($roleColor) . ';background:' . $e($roleColor) . '">' . $e($role->name) . '</div>';
				} ?>
			</div>
		</div>
	</section>
</main>
