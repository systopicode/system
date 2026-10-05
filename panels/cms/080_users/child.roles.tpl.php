<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<section class="widget">
	<header>
		<h1>User Roles</h1>
		<?= \menu::create('barmenu')->item('addRole')->icon('fal fa-plus-circle') ?>
	</header>
	<main>
		<div class="row">
			<div>
				<table class="keycolumn">
					<tr><th>id</th><th>name</th><th>description</th></tr>
					<?php foreach ($this->roles as $role) { ?>
						<tr data-role_id="<?= $role->id ?>">
							<td><?= $role->id ?></td>
							<td>
								<a class="ajax" href="edit/?role_id=<?= $role->id ?>">
									<div class="tag active" data-drag_type="role"<?= $role->color ? " style='background:{$e($role->color)};border-color:{$e($role->color)}'" : '' ?>><?= $e($role->name) ?><i class="fas fa-pen space"></i></div>
								</a>
							</td>
							<td><?= $e($role->description) ?></td>
						</tr>
					<?php } ?>
				</table>
			</div>
		</div>
	</main>
</section>
