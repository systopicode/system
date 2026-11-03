<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Roles\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
// the icon before the key: a lock for the built-in roles, given to every request and asked by no
// panel; else the controlmap, every panel whose code asks for it (superusers only, like the controlmap)
$superuser = \Systopic\System\Auth\Session::isSuperuser();
$controlmap = static function (\Systopic\System\Tables\Roles\Model $role) use ($e, $superuser): string {
	if ($role->isBuiltin) {
		return "<i class='cmsIcon cmsIcon-lock' title='Built in: every " . ($role->key === 'everyone' ? 'request' : 'signed-in user') . " holds it, the key is fixed'></i>";
	}
	if (!$superuser || $role->key === NULL) {
		return '';
	}
	$title = 'Where the code asks for ' . $role->key . ' (controlmap)'
		. ($role->isSystem ? ' — a role of the system, its key is fixed' : '');
	return "<a class='ajax' href='{$e(\http::$root . 'cms/controlmap/discover/?all=1&filter=' . rawurlencode((string) $role->key))}' title='{$e($title)}'>"
		. "<i class='cmsIcon cmsIcon-sitemap'></i></a>";
};
?>
<section class="widget is-list roleList">
	<header>
		<h2>List of roles</h2>
		<input class="listSearch" type="search" aria-label="Search roles" placeholder="Search" data-on_input="filterRoles" autocomplete="off">
		<button type="button" data-cms-action class="button submit" data-on_click="addRole"><i class="cmsIcon cmsIcon-plus"></i><span>Add role</span></button>
	</header>
	<div class="tableScroll">
		<table>
			<thead>
				<tr>
					<th>Name</th>
					<th>Key</th>
					<th>Color</th>
					<th>Description</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php $hasRoles = false; foreach ($this->roles as $role) { $hasRoles = true;
					$color = ($role->color !== null && $role->color !== '') ? $role->color : '#8a8f93';
				?>
					<tr data-role_id="<?= (int) $role->id ?>">
						<td>
							<span class="roleName" data-drag_type="role" data-role_id="<?= (int) $role->id ?>"><?= $e($role->name) ?></span>
						</td>
						<td class="roleKey"><?= $controlmap($role) ?><code><?= $e($role->key) ?></code></td>
						<td><span class="swatch" style="background:<?= $e($color) ?>"></span></td>
						<td><?= $e($role->description) ?></td>
						<td class="icons">
							<ul>
								<li><a class="ajax button icononly" title="Edit" href="edit/?role_id=<?= (int) $role->id ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li>
							</ul>
						</td>
					</tr>
				<?php } ?>
                <?php if (!$hasRoles) { ?><tr><td colspan="4"><p class="tagsHint">No roles configured yet.</p></td></tr><?php } ?>
			</tbody>
		</table>
	</div>
</section>
