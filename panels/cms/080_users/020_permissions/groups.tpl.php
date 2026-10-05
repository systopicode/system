<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Panel $this */

use Systopic\System\Panels\Root\Cms\Users\Panel as Users;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$cell = function ($group, string $right, string $drop, string $remove): string {
	$stickers = '';
	foreach ($this->admin->rolesWith($group, $right) as $role) {
		$stickers .= Users::roleSticker($role, $remove);
	}
	return "<div class='roles' data-drop_accept='role' data-on_drop='$drop'>$stickers</div>";
};
?>
<section class="widget">
	<header>
		<h1>Content Groups and assigned Role permissions</h1>
		<?= \menu::create('barmenu')->item('addGroup')->icon('fal fa-plus-circle') ?>
	</header>
	<main>
		<div class="row">
			<div>
				<table class="keycolumn">
					<tr>
						<th>id</th>
						<th>name</th>
						<th><i class="fas fa-eye space"></i>read access</th>
						<th><i class="fas fa-pen space"></i>write access</th>
					</tr>
					<?php foreach ($this->groups as $group) { ?>
						<tr data-group_id="<?= $group->id ?>"<?= $group->color ? " style='background:{$e($group->color)}'" : '' ?>>
							<td><?= $group->id ?></td>
							<td>
								<a class="ajax" href="edit/?group_id=<?= $group->id ?>"><div class="tag"><?= $e($group->name) ?><i class="fas fa-pen space"></i></div></a>
								<p><?= $e($group->description) ?></p>
							</td>
							<td><?= $cell($group, 'read', 'addReadRoleToGroup', 'removeReadRoleFromGroup') ?></td>
							<td><?= $cell($group, 'write', 'addWriteRoleToGroup', 'removeWriteRoleFromGroup') ?></td>
						</tr>
					<?php } ?>
				</table>
			</div>
		</div>
	</main>
</section>
<style>
	div.roles {
		min-height: 20px;
	}
</style>
