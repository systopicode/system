<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */

use Systopic\System\Panels\Root\Cms\Users\Panel as Users;

$user = $this->user;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn(?DateTimeImmutable $d): string => $d?->format('d.m.Y H:i:s') ?? '';
$field = static function (string $label, string $value, ?string $action = NULL, string $type = 'text') use ($e): string {
	$input = $action === NULL
			? "<input type='$type' readonly value='{$e($value)}' />"
			: "<input type='$type' autocapitalize='off' name='value' data-on_blur='$action' value='{$e($value)}' required spellcheck='off' />";
	return "<div class='inputGroup'>$input<span class='bar'></span><label>$label</label></div>";
};
?>
<header>
	<h1>Edit User</h1>
	<ul class="barmenu">
		<li>
			<a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
		</li>
	</ul>
</header>
<main>
	<div class="layout">
		<div class='row'>
			<?= \message::flush() ?>
		</div>
		<div class="row layout">
			<div class="halfColumn">
				<?= $field('Username', (string) $user->name, 'updateUserName') ?>
				<?= $field('Fullname', (string) $user->fullname, 'updateUserFullname') ?>
				<?= $field('E-Mail', (string) $user->email, 'updateUserEmail', 'email') ?>
				<?= $field('Last Login', $when($user->lastLogin)) ?>
				<?= $field('First Login', $when($user->firstLogin)) ?>
				<?php if (\Systopic\System\Auth\Session::isSuperuser()) { ?>
					<div class="inputGroup">
						<?php if ($user->isSuperuser) { ?>
							<button type="button" class="submit" data-on_click="../revokeSuperuser" data-user_id="<?= $user->id ?>" <?= (int) $user->id === (int) \Systopic\System\Auth\Session::id() ? 'disabled' : '' ?>>
								<i class="fas fa-spider"></i> Revoke Superuser
							</button>
						<?php } else { ?>
							<button type="button" class="submit" data-on_click="../makeSuperuser" data-user_id="<?= $user->id ?>">
								<i class="fas fa-spider"></i> Make Superuser
							</button>
						<?php } ?>
					</div>
				<?php } ?>
			</div>
			<div class="halfColumn">
				<?= $field('User Created at', $when($user->createDate)) ?>
				<?= $field('User Created by', Users::userLabel($this->createdBy)) ?>
				<?= $field('Activation Key', (string) $user->activationKey) ?>
				<?= $field('Activation Key Expire', $when($user->activationKeyExpire)) ?>
				<br><br>
				<button class='submit' data-on_click='resendActivationEmail'><i class="far fa-envelope"></i>
					<?= $user->firstLogin ? 'Send new activation E-Mail' : 'Send activation E-Mail' ?>
				</button>
			</div>
		</div>
	</div>
</main>
