<?php
/** @var \Systopic\System\Panels\Root\Admin\Access\Users\Panel $this */

use Systopic\System\Auth\LoginCodes;
use Systopic\System\Auth\Presence;
use Systopic\System\Panels\Root\Admin\Access\Panel as Users;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$screens = ($this->accountKind ?? '') === \Systopic\System\Tables\Users\Model::SCREEN;
?>
<h1><?= $screens ? 'Screens' : 'Persons' ?></h1>
<p class="tagsHint"><?= $screens ? 'Search by name or description. The pen opens the screen.' : 'Search by username, full name or email. The pen opens the person.' ?></p>
<?php if (!LoginCodes::available()) { ?>
<div class="cms message is-stay userKeyWarn"><div class="warning"><p>The sign-in key is missing. One-time codes no longer work, including codes already sent. Passwords, passkeys and trusted devices still do.</p></div></div>
<?php } ?>
<section class="widget is-list userList">
	<header>
		<h2><?= $screens ? 'List of screens' : 'List of persons' ?></h2>
		<input class="listSearch" type="search" aria-label="<?= $screens ? 'Search screens' : 'Search persons' ?>" placeholder="Search" data-on_input="filterUsers" autocomplete="off">
		<button type="button" data-cms-action class="button submit" data-on_click="addUser"><i class="cmsIcon cmsIcon-plus"></i><span><?= $screens ? 'Add screen' : 'Add person' ?></span></button>
	</header>
	<div class="tableScroll">
		<table>
			<thead>
				<tr>
					<th></th>
					<th><?= $screens ? 'Name' : 'Username' ?></th>
					<th><?= $screens ? 'Description' : 'Full name' ?></th>
					<?php if (!$screens) { ?><th>Email</th><?php } ?>
					<th>Last sign-in</th>
					<th>Roles</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->users as $user) { ?>
					<tr data-user_id="<?= $user->id ?>">
						<?php [$status, $statusTitle] = Presence::userMark((int) $user->id); ?>
						<td class="userStatusCell"><span class="userStatus <?= $status ?>" title="<?= $e($statusTitle) ?>" role="img" aria-label="<?= $e($statusTitle) ?>"></span></td>
						<td>
							<span class="userListIdentity"><img class="userListAvatar" alt="" loading="lazy" src="<?= $e(\Systopic\System\Auth\Portrait::src((int) $user->id, $user->email, $screens)) ?>"><span class="userListName"><?= $e($user->name) ?></span>
                            <?php if ($user->isBlocked): ?><i class="cmsIcon cmsIcon-user-lock userSuper userBlocked" title="Blocked" aria-label="Blocked"></i><?php endif; ?>
							<?php if ($user->isSuperuser) { ?>
								<i class="cmsIcon cmsIcon-user-shield userSuper" title="Superuser" role="img" aria-label="Superuser"></i>
							<?php } ?>
                            </span>
						</td>
						<td><?= $e($user->fullname) ?></td>
						<?php if (!$screens) { ?><td><?= $e($user->email) ?></td><?php } ?>
						<td><?= $user->lastLogin ? $e(\Systopic\System\Sys\LocalTime::format($user->lastLogin, \Systopic\System\Sys\LocalTime::DATETIME)) : 'Unknown' ?></td>
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
								<?php if (\Systopic\System\Auth\Session::isSuperuser(true) && !$user->isSuperuser) { ?>
									<li><button type="button" data-cms-action class="button icononly" title="Sign in as this user" data-on_click="../hijackUser"><i class="cmsIcon cmsIcon-user-shield"></i></button></li>
								<?php } ?>
								<li><a class="ajax button icononly" title="Edit <?= $screens ? 'screen' : 'person' ?>: <?= $e($user->name) ?>" aria-label="Edit <?= $screens ? 'screen' : 'person' ?>: <?= $e($user->name) ?>" href="<?= $e($this->href . 'edit/?user_id=' . (int) $user->id) ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li>
								<?php if ((int) $user->id !== (int) \Systopic\System\Auth\Session::id()) { ?>
									<li><button type="button" data-cms-action class="button icononly" title="Delete <?= $screens ? 'screen' : 'person' ?>: <?= $e($user->name) ?>" aria-label="Delete <?= $screens ? 'screen' : 'person' ?>: <?= $e($user->name) ?>" data-on_click="userConfirm" data-action="removeUser" data-confirm="<?= $screens ? 'Delete this screen? This cannot be undone.' : 'Delete this user? This cannot be undone.' ?>" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-trash"></i></button></li>
								<?php } ?>
							</ul>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</section>
