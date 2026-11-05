<?php
/** @var \Systopic\System\Panels\Root\Admin\Access\Users\Panel $this */

use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\Presence;
use Systopic\System\Panels\Root\Admin\Access\Panel as Users;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$screens = ($this->accountKind ?? '') === \Systopic\System\Tables\Users\Model::SCREEN;
?>
<h1><?= $screens ? 'Screens' : 'Users' ?></h1>
<p class="tagsHint"><?= $screens ? 'Search by name or description. The pen opens the screen.' : 'Search by username, full name or email. The pen opens the user.' ?></p>
<?php if (!SignInCodes::available()) { ?>
<div class="cms message is-stay userKeyWarn"><div class="warning"><p>The sign-in key could not be written. One-time codes do not work until the config of this instance can be changed.</p></div></div>
<?php } ?>
<section class="widget is-list userList">
	<header>
		<h2><?= $screens ? 'List of screens' : 'List of users' ?></h2>
		<input class="listSearch" type="search" aria-label="<?= $screens ? 'Search screens' : 'Search users' ?>" placeholder="Search" data-on_input="filterUsers" autocomplete="off">
		<button type="button" data-cms-action class="button submit" data-on_click="addUser"><i class="cmsIcon cmsIcon-plus"></i><span><?= $screens ? 'Add screen' : 'Add user' ?></span></button>
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
						<td><?= $user->lastSignIn ? $e(\Systopic\System\Sys\LocalTime::format($user->lastSignIn, \Systopic\System\Sys\LocalTime::DATETIME)) : 'Unknown' ?></td>
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
									<li><button type="button" data-cms-action class="button icononly" title="Sign in as this user" data-on_click="../hijackUser"><i class="cmsIcon cmsIcon-ghost-3"></i></button></li>
								<?php } ?>
								<?php if (\Systopic\System\Auth\SignInGrant::account($this->scope, \Systopic\System\Queries\UserLookup\UserLookup::byId($this->scope, (int) \Systopic\System\Auth\Session::id()), $this, (int) $user->id)) { ?>
									<li><a class="ajax button icononly" title="Scan sign-in code: <?= $e($user->name) ?>" aria-label="Scan sign-in code: <?= $e($user->name) ?>" href="<?= $e($this->href . 'pair/?user_id=' . (int) $user->id . '&way=scan') ?>"><i class="cmsIcon cmsIcon-object-scan"></i></a></li>
									<li><a class="ajax button icononly" title="Create new auth code: <?= $e($user->name) ?>" aria-label="Create new auth code: <?= $e($user->name) ?>" href="<?= $e($this->href . 'pair/?user_id=' . (int) $user->id) ?>"><i class="cmsIcon cmsIcon-qrcode"></i></a></li>
								<?php } ?>
								<?php if (\Systopic\System\Auth\Session::hasRole('cmsAdmin')) { ?>
									<li><a class="ajax button icononly" title="Edit <?= $screens ? 'screen' : 'user' ?>: <?= $e($user->name) ?>" aria-label="Edit <?= $screens ? 'screen' : 'user' ?>: <?= $e($user->name) ?>" href="<?= $e($this->href . 'edit/?user_id=' . (int) $user->id) ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li>
								<?php } ?>
							</ul>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</section>
