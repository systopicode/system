<?php
/** @var \Systopic\System\Panels\Root\Admin\Access\Roles\Panel|\Systopic\System\Panels\Root\Admin\Access\Content\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
// the icons before the key, as many as apply:
//   lock        a role of the system - built in, or created by it - its key is fixed
//   panel map   the key is named in the controlmap, a config or a panel's code (superusers
//               only, like the panel map: reading it off the code takes a moment)
//   content     the role may read or write content groups (access/content)
$roleKeys = \Systopic\System\Auth\Session::isSuperuser()
	? \Systopic\System\Controlmap\Map::ofRequest()->roleKeys()
	: [];
$icons = function (\Systopic\System\Tables\Roles\Model $role) use ($e, $roleKeys): string {
	$html = '';
	if ($role->isBuiltin) {
		$html .= "<i class='cmsIcon cmsIcon-lock' title='Built in: every " . ($role->key === 'everyone' ? 'request' : 'signed-in user') . " holds it, the key is fixed'></i>";
	} elseif ($role->isSystem) {
		$html .= "<i class='cmsIcon cmsIcon-lock' title='A role of the system: it creates it, the key is fixed'></i>";
	}
	if ($role->key !== NULL && isset($roleKeys[$role->key])) {
		$html .= "<a class='ajax' href='{$e(\http::$root . 'admin/access/panelmap/discover/?all=1&filter=' . rawurlencode($role->key))}' title='{$e('Named in the panel map: ' . $role->key)}'>"
			. "<i class='cmsIcon cmsIcon-sitemap'></i></a>";
	}
	$groups = $this->admin->groupsOf($role);
	if ($groups !== []) {
		$title = 'In content groups: ' . implode(', ', array_map(
			static fn(array $g): string => $g['group']->name . ' (' . implode(', ', array_keys(array_filter(['read' => $g['read'], 'write' => $g['write']]))) . ')',
			$groups,
		));
		$html .= "<a class='ajax' href='{$e(\http::$root . 'admin/access/content/')}' title='{$e($title)}'><i class='cmsIcon cmsIcon-folder'></i></a>";
	}
	return $html;
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
						<td class="roleKey"><?= $icons($role) ?><code><?= $e($role->key) ?></code></td>
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
