<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Roles\Edit\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */

$role = $this->role;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$field = static function (string $label, string $type, string $action, ?string $value) use ($e, $role): string {
	return '<label class="field"><span>' . $label . '</span><input type="' . $type . '" autocapitalize="off" data-role_id="' . (int) $role->id . '" name="value" data-on_blur="' . $action . '" value="' . $e($value) . '" autocomplete="off" spellcheck="off"></label>';
};
?>
<header class="tagEditTitle">
	<h1><?= $e($role->name) ?></h1>
	<button type="button" class="tagGroupRename" onclick="renameUserAdminTitle(this)" data-rename-url="<?= $e($this->href . 'updateRoleName?role_id=' . (int) $role->id) ?>" title="Rename" aria-label="Rename"><i class="fa-regular fa-pen"></i></button>
	<ul class="barmenu">
		<?php if ((int) $role->id !== 1) { ?><li><button type="button" class="button icononly" aria-label="Remove" title="Remove" data-on_click="userConfirm" data-action="removeRole" data-confirm="Delete this role? This cannot be undone." data-role_id="<?= (int) $role->id ?>"><i class="fa-regular fa-trash"></i></button></li><?php } ?>
		<li><a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a></li>
	</ul>
</header>
<main class="roleEdit">
	<?= \message::flush() ?>
	<div class="fieldGrid is-stack">
		<?= $field('Description', 'text', 'updateRoleDescription', (string) $role->description) ?>
		<label class="field roleColor">
			<span>Color</span>
			<span class="colorChoice">
				<input type="color" data-role_id="<?= (int) $role->id ?>" name="value" data-on_change="updateRoleColor" value="<?= $e($role->color !== null && $role->color !== '' ? $role->color : '#8a8f93') ?>">
				<span class="tag active is-plain" style="--tag-color:<?= $e($role->color ?: '#8a8f93') ?>;--tag-bg:<?= $e($role->color ?: '#8a8f93') ?>;background:<?= $e($role->color ?: '#8a8f93') ?>"><?= $e($role->name) ?></span>
			</span>
		</label>
	</div>
	<section class="groupRights">
		<div class="rightsHead">
			<h2>Permissions</h2>
			<p class="tagsHint">Click a permission to allow or remove it.</p>
		</div>
		<div>
			<h2>Read</h2>
			<div class="picks">
				<?php foreach ($this->admin->groups as $group) {
					$on = (bool) ($this->admin->permission($group, $role)?->read ?? false);
					$color = ($group->color !== null && $group->color !== '') ? $group->color : '#8a8f93';
					echo '<div class="tag' . ($on ? ' active' : '') . '" data-on_click="toggleGroupRole" data-right="read" data-role_id="' . (int) $role->id . '" data-group_id="' . (int) $group->id . '" style="--tag-color:' . $e($color) . ';--tag-bg:' . $e($color) . ';background:' . $e($color) . '">' . $e($group->name ?: 'Group') . '</div>';
				} ?>
			</div>
		</div>
		<div>
			<h2>Write</h2>
			<div class="picks">
				<?php foreach ($this->admin->groups as $group) {
					$on = (bool) ($this->admin->permission($group, $role)?->write ?? false);
					$color = ($group->color !== null && $group->color !== '') ? $group->color : '#8a8f93';
					echo '<div class="tag' . ($on ? ' active' : '') . '" data-on_click="toggleGroupRole" data-right="write" data-role_id="' . (int) $role->id . '" data-group_id="' . (int) $group->id . '" style="--tag-color:' . $e($color) . ';--tag-bg:' . $e($color) . ';background:' . $e($color) . '">' . $e($group->name ?: 'Group') . '</div>';
				} ?>
			</div>
		</div>
	</section>
</main>
