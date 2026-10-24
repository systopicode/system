<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Roles\Edit\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */

$role = $this->role;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$field = static function (string $label, string $type, string $action, ?string $value) use ($e, $role): string {
	return '<label class="field"><span>' . $label . '</span><input type="' . $type . '" autocapitalize="off" data-role_id="' . (int) $role->id . '" name="value" data-on_blur="' . $action . '" value="' . $e($value) . '" autocomplete="off" spellcheck="off"></label>';
};
?>
<header>
	<h1><?= $e($role->name) ?></h1>
	<ul class="barmenu">
		<li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li>
	</ul>
</header>
<main class="roleEdit">
	<?= \message::flush() ?>
	<div class="fieldGrid is-stack">
		<?= $field('Name', 'text', 'updateRoleName', $role->name) ?>
		<?= $field('Description', 'text', 'updateRoleDescription', (string) $role->description) ?>
		<label class="field roleColor">
			<span>Color</span>
			<span class="colorChoice">
				<input type="color" data-role_id="<?= (int) $role->id ?>" name="value" data-on_change="updateRoleColor" value="<?= $e($role->color !== null && $role->color !== '' ? $role->color : '#8a8f93') ?>">
				<span class="tag active is-plain" style="--tag-color:<?= $e($role->color ?: '#8a8f93') ?>;--tag-bg:<?= $e($role->color ?: '#8a8f93') ?>;background:<?= $e($role->color ?: '#8a8f93') ?>"><?= $e($role->name) ?></span>
			</span>
		</label>
	</div>
</main>
<footer>
	<button type="button" class="button" data-on_click="userConfirm" data-action="removeRole" data-confirm="Delete this role? This cannot be undone." data-role_id="<?= (int) $role->id ?>"><i class="fa-regular fa-trash"></i>Remove role</button>
</footer>
