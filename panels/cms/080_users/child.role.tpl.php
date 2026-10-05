<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */

$role = $this->role;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$field = static fn(string $label, string $type, string $action, ?string $value): string
		=> "<div class='inputGroup'><input type='$type' autocapitalize='off' data-role_id='{$role->id}' name='value' data-on_blur='$action' value='{$e($value)}' required spellcheck='off' /><span class='bar'></span><label>$label</label></div>";
?>
<header>
	<h1>Edit User Role</h1>
	<?= \menu::create('barmenu')->link('../')->icon('far fa-times') ?>
</header>
<main>
	<div class="layout">
		<div class='row'>
			<?= \message::flush() ?>
		</div>
		<div class="row layout">
			<div class="halfColumn">
				<?= $field('Name', 'text', 'updateRoleName', $role->name) ?>
				<?= $field('Description', 'text', 'updateRoleDescription', (string) $role->description) ?>
				<?= $field('Color', 'color', 'updateRoleColor', $role->color) ?>
			</div>
		</div>
	</div>
</main>
