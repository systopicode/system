<?php
/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */

$me = $this->me;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$gravatar = $this->get_gravatar($me?->email, 300);
$field = static fn(string $label, string $type, string $action, ?string $value, string $caps = 'on'): string
		=> "<div class='inputGroup'><input type='$type' autocapitalize='$caps' name='value' data-on_blur='$action' value='{$e($value)}' required spellcheck='off' /><span class='bar'></span><label>$label</label></div>";
?>
<section class="cms">
	<div class='signet'>
		<span><?= $e($this->initials) ?></span>
		<?php if ($gravatar) { ?>
			<img class='gravatr' src='<?= $gravatar ?>'>
		<?php } ?>
	</div>
	<a href='https://en.gravatar.com/site/login' target='_blank' class='button submit'>You can change your profile picture on Gravatar</a>
	<br><br>
	<div class='row'>
		<?= \message::flush() ?>
	</div>
	<div class="layout">
		<?php if ($me) { ?>
			<div class="halfColumn widget" style='max-width: 1000px;' >
				<header>
					<h1>Basics</h1>
				</header>
				<main>
					<?= $field('Username', 'text', 'updateUserName', $me->name, 'off') ?>
					<?= $field('Fullname', 'text', 'updateUserFullname', $me->fullname) ?>
					<?= $field('E-Mail', 'email', 'updateUserEmail', $me->email) ?>
				</main>
			</div>
			<div class="halfColumn widget" style='max-width: 1000px;' >
				<form method=post action='changePassword'>
					<header>
						<h1>Change password</h1>
					</header>
					<main>
						<div class="row inputGroup">
							<input type=password name="password" required spellcheck="false" autocapitalize="off" autocomplete="new-password"/>
							<span class="bar"></span>
							<label>New Password</label>
						</div>
						<div class="row inputGroup">
							<input type=password name="password_repeat" required spellcheck="false" autocapitalize="off" autocomplete="new-password"/>
							<span class="bar"></span>
							<label>Repeat Password</label>
						</div>
					</main>
					<footer>
						<button class='submit'>Set Password</button>
					</footer>
				</form>
			</div>
		<?php } ?>
		<div class="halfColumn widget" style='max-width: 1000px;' >
			<header>
				<h1>Adopt Role</h1> <?= \menu::create('barmenu')->item('unsetFakeRoles')->icon('fas fa-undo')->if(count($this->fakeRoles)) ?>
			</header>
			<main>
				<div class="row">
					<div>
						<table class="keycolumn">
							<?php foreach ($this->adoptableRoles as $role) { ?>
								<tr data-role_name="<?= $e($role->name) ?>" data-selected="<?= in_array($role->name, $this->fakeRoles, TRUE) ? 'TRUE' : 'FALSE' ?>" data-on_click="toggleFakeRole">
									<td><div class="tag active"><?= $e($role->name) ?></div></td>
								</tr>
							<?php } ?>
						</table>
					</div>
				</div>
			</main>
		</div>
	</div>
</section>
