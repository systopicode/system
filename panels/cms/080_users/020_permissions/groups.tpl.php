<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$names = function ($group, string $right) use ($e): string {
	$list = [];
	foreach ($this->admin->rolesWith($group, $right) as $role) {
		$list[] = $e($role->name);
	}
	return $list === [] ? '' : implode(', ', $list);
};
?>
<section class="widget is-list groupList">
	<header>
		<h2>Content groups</h2>
		<input class="listSearch" type="search" placeholder="Search" data-on_input="filterGroups" autocomplete="off">
		<a class="button submit" data-on_click="addGroup"><i class="fa-regular fa-plus"></i><span>Add group</span></a>
	</header>
	<table>
		<thead>
			<tr>
				<th>Name</th>
				<th>Color</th>
				<th>Description</th>
				<th>Read</th>
				<th>Write</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($this->groups as $group) {
				$color = ($group->color !== null && $group->color !== '') ? $group->color : '#8a8f93';
			?>
				<tr data-group_id="<?= (int) $group->id ?>">
					<td><?= $e($group->name) ?></td>
					<td><span class="swatch" style="background:<?= $e($color) ?>"></span></td>
					<td><?= $e($group->description) ?></td>
					<td><?= $names($group, 'read') ?></td>
					<td><?= $names($group, 'write') ?></td>
					<td class="icons">
						<ul>
							<li><a class="ajax button icononly" title="Edit" href="edit/?group_id=<?= (int) $group->id ?>"><i class="fa-regular fa-pen"></i></a></li>
						</ul>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</section>
