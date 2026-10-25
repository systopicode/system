<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Panel $this */

use Systopic\System\Panels\Root\Cms\Users\Panel as Users;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$superuser = \Systopic\System\Auth\Session::isSuperuser();
?>
<h1>Users</h1>
<p class="tagsHint">Search by username, full name or email. The pen opens the user.</p>
<section class="widget is-list userList">
	<header>
		<h2>List of users</h2>
		<input class="listSearch" type="search" placeholder="Search" data-on_input="filterUsers" autocomplete="off">
		<a class="button submit" data-on_click="addUser"><i class="fa-regular fa-plus"></i><span>Add user</span></a>
	</header>
	<div class="tableScroll">
		<table>
			<thead>
				<tr>
					<th></th>
					<th>Username</th>
					<th>Full name</th>
					<th>Email</th>
					<th>Last sign-in</th>
					<th>Roles</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->users as $user) { ?>
					<tr data-user_id="<?= $user->id ?>">
						<td class="userStatusCell"><span class="userStatus is-unknown" title="Unknown"></span></td>
						<td>
							<?= $e($user->name) ?>
							<?php if ($user->isSuperuser) { ?>
								<i class="fa-regular fa-user-shield userSuper" title="Superuser"></i>
							<?php } ?>
						</td>
						<td><?= $e($user->fullname) ?></td>
						<td><?= $e($user->email) ?></td>
						<td><?= $user->lastLogin ? $e(\Systopic\System\Sys\LocalTime::format($user->lastLogin, 'j M Y, H:i')) : 'Unknown' ?></td>
						<td>
							<div class="roles" data-drop_accept="role" data-on_drop="addRoleToUser">
								<?php
								foreach ($user->isSuperuser ? $this->roles : $this->admin->rolesOf($user) as $role) {
									echo Users::roleSticker($role);
								}
								?>
							</div>
						</td>
						<td class="icons">
							<ul>
								<?php if ($superuser && !$user->isSuperuser) { ?>
									<li><a class="button icononly" title="Sign in as this user" data-on_click="../hijackUser"><i class="fa-regular fa-ghost"></i></a></li>
								<?php } ?>
								<li><a class="ajax button icononly" title="Edit" href="edit/?user_id=<?= $user->id ?>"><i class="fa-regular fa-pen"></i></a></li>
								<?php if ((int) $user->id !== (int) \Systopic\System\Auth\Session::id()) { ?>
									<li><a class="button icononly" title="Delete" data-on_click="userConfirm" data-action="removeUser" data-confirm="Delete this user? This cannot be undone." data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-trash"></i></a></li>
								<?php } ?>
							</ul>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</section>
