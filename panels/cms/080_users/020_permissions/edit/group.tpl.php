<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */

$group = $this->group;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$field = static function (string $label, string $type, string $action, ?string $value) use ($e, $group): string {
	return '<label class="field"><span>' . $label . '</span><input type="' . $type . '" autocapitalize="off" data-group_id="' . (int) $group->id . '" name="value" data-on_blur="' . $action . '" value="' . $e($value) . '" autocomplete="off" spellcheck="off"></label>';
};
$color = ($group->color !== null && $group->color !== '') ? $group->color : '#8a8f93';
?>
<header>
	<h1><?= $e($group->name ?: 'Group') ?></h1>
	<ul class="barmenu">
		<li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li>
	</ul>
</header>
<main class="groupEdit">
	<?= \message::flush() ?>
	<div class="fieldGrid is-stack">
		<?= $field('Name', 'text', 'updateGroupName', (string) $group->name) ?>
		<?= $field('Description', 'text', 'updateGroupDescription', (string) $group->description) ?>
		<label class="field roleColor">
			<span>Color</span>
			<span class="colorChoice">
				<input type="color" data-group_id="<?= (int) $group->id ?>" name="value" data-on_change="updateGroupColor" value="<?= $e($color) ?>">
				<?php if ((string) $group->color !== '') { ?>
					<button type="button" class="button" data-on_click="resetGroupColor" data-group_id="<?= (int) $group->id ?>">Clear</button>
				<?php } ?>
			</span>
		</label>
	</div>
	<section class="groupRights">
		<p class="tagsHint">Click a role to allow or remove it.</p>
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
<footer>
	<button type="button" class="button" data-on_click="userConfirm" data-action="removeGroup" data-confirm="Delete this group? This cannot be undone." data-group_id="<?= (int) $group->id ?>"><i class="fa-regular fa-trash"></i>Remove group</button>
</footer>
