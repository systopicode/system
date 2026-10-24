<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Roles\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<section class="widget is-list roleList">
	<header>
		<h2>List of roles</h2>
		<input class="listSearch" type="search" placeholder="Search" data-on_input="filterRoles" autocomplete="off">
		<a class="button submit" data-on_click="addRole"><i class="fa-regular fa-plus"></i><span>Add role</span></a>
	</header>
	<table>
		<thead>
			<tr>
				<th>Name</th>
				<th>Color</th>
				<th>Description</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($this->roles as $role) {
				$color = ($role->color !== null && $role->color !== '') ? $role->color : '#8a8f93';
			?>
				<tr data-role_id="<?= (int) $role->id ?>">
					<td>
						<span class="roleName" data-drag_type="role" data-role_id="<?= (int) $role->id ?>"><?= $e($role->name) ?></span>
					</td>
					<td><span class="swatch" style="background:<?= $e($color) ?>"></span></td>
					<td><?= $e($role->description) ?></td>
					<td class="icons">
						<ul>
							<li><a class="ajax button icononly" title="Edit" href="edit/?role_id=<?= (int) $role->id ?>"><i class="fa-regular fa-pen"></i></a></li>
						</ul>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</section>
