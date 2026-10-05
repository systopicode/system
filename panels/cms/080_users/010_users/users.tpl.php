<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Panel $this */

use Systopic\System\Panels\Root\Cms\Users\Panel as Users;
use Systopic\System\Tables\Users\Operator as UserOperator;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$superuser = \Systopic\System\Auth\Session::isSuperuser();
$arrow = $this->state->sortDesc ? 'down' : 'up';
?>
<div class="widget">
	<header>
		<h1>List of users</h1>
		<?= \menu::create('barmenu')->item('addUser')->icon('fal fa-plus-circle') ?>
	</header>
	<main>
		<div class="row">
			<div>
				<table class="keycolumn">
					<tr>
						<?php foreach (['name', 'email', 'last_login'] as $key) { ?>
							<th><b data-on_click="sortUsers" data-sortby="<?= $key ?>"><?= $key ?><?= $key === $this->state->sortBy ? " <i class=\"fas fa-sort-$arrow\"></i>" : '' ?></b></th>
						<?php } ?>
						<th><b>roles</b></th>
						<th></th>
					</tr>
					<?php foreach ($this->users as $user) { ?>
						<tr data-user_id="<?= $user->id ?>">
							<td><?= $e($user->name) ?></td>
							<td><?= $e($user->email) ?></td>
							<td><?= $user->lastLogin?->format('d.m.Y H:i:s') ?? 'unbekannt' ?></td>
							<td>
								<div class="roles" data-drop_accept="role" data-on_drop="addRoleToUser">
									<?php
									// A superuser has every role; the ones really assigned can be taken away.
									foreach ($user->isSuperuser ? $this->roles : $this->admin->rolesOf($user) as $role) {
										echo Users::roleSticker($role, $this->admin->assignment($user, $role) ? 'removeRoleFromUser' : NULL);
									}
									?>
								</div>
							</td>
							<td>
								<span class="nowrap">
									<?php if ($superuser && $user->isSuperuser) { ?>
										<i class="fas fa-spider space" data-on_click="revokeSuperuser"></i>
									<?php } elseif ($superuser) { ?>
										<i class="fas fa-spider disabled space" data-on_click="makeSuperuser"></i>
										<i class="fas fa-ghost space" data-on_click="../hijackUser"></i>
									<?php } elseif ($user->isSuperuser) { ?>
										<i class="fas fa-spider space"></i>
									<?php } ?>
									<a class="ajax" href="edit/?user_id=<?= $user->id ?>"><i class="fas fa-pen space"></i></a>
								</span>
							</td>
						</tr>
					<?php } ?>
				</table>
			</div>
		</div>
	</main>
</div>
<style>
	div.roles {
		min-height: 20px;
	}
</style>
