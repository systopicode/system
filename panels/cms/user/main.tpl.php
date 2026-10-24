<?php
/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */

use Systopic\System\Auth\Portrait;

$me = $this->me;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$portrait = $me ? Portrait::src((int) $me->id, $me->email) : '';
$field = static fn(string $label, string $type, string $action, ?string $value, string $caps = 'on'): string
	=> "<label class='field'><span>$label</span><input type='$type' autocapitalize='$caps' name='value' data-on_blur='$action' value='{$e($value)}' autocomplete='off' spellcheck='off'></label>";
$preview = $this->fakeRoles;
?>
<section class="cms profile">
	<?= \message::flush() ?>
	<div class="profileHead">
		<div class="profilePortrait">
			<div class="portraitFrame">
				<form class="portraitDrop" method="post" enctype="multipart/form-data" target="_self" action="<?= $e($this->href . 'uploadPortrait') ?>">
					<div class="signet">
						<span><?= $e($this->initials) ?></span>
						<?php if ($portrait !== '') { ?>
							<img src="<?= $e($portrait) ?>" alt="">
						<?php } ?>
					</div>
					<input type="file" name="portrait" accept="image/jpeg,image/png,image/webp,image/gif" onchange="this.form.submit()">
				</form>
				<?php if ($me && Portrait::hasFile((int) $me->id)) { ?>
					<button type="button" class="portraitRemove" data-on_click="removePortrait" title="Remove photo"><i class="fa-regular fa-trash"></i></button>
				<?php } ?>
			</div>
			<span>Drop or click to replace</span>
		</div>
		<div>
			<h1><?= $e($me?->fullname ?: $me?->name ?: 'Profile') ?></h1>
			<?php if ($me?->email) { ?>
				<p><?= $e($me->email) ?></p>
			<?php } ?>
			<?php if ($me?->lastLogin) { ?>
				<p><i class="fa-regular fa-user-clock"></i>Signed in <?= $e($me->lastLogin->format('j M Y, H:i')) ?></p>
			<?php } ?>
			<ul class="profileRoles">
				<?php if (\Systopic\System\Auth\Session::isSuperuser(true)) { ?>
					<li class="is-super"><i class="fa-regular fa-user-shield"></i>Superuser</li>
				<?php } ?>
				<?php foreach (\Systopic\System\Auth\Session::current()->roles as $role) { ?>
					<li><?= $e($role->name) ?></li>
				<?php } ?>
				<?php if (!\Systopic\System\Auth\Session::isSuperuser(true) && \Systopic\System\Auth\Session::current()->roles === []) { ?>
					<li class="is-none">No role</li>
				<?php } ?>
			</ul>
		</div>
	</div>
	<?php if ($preview !== []) { ?>
		<p class="profilePreview"><i class="fa-regular fa-eye"></i>Viewing the CMS as <?= $e(implode(', ', $preview)) ?>. <a data-on_click="unsetFakeRoles">Stop</a></p>
	<?php } ?>
	<div class="profileGrid">
		<?php if ($me) { ?>
			<section class="widget">
				<header><h2><i class="fa-regular fa-id-card"></i>Account</h2></header>
				<main class="fieldGrid">
					<?= $field('Name', 'text', 'updateUserName', $me->name, 'off') ?>
					<?= $field('Full name', 'text', 'updateUserFullname', $me->fullname) ?>
					<?= $field('Email', 'email', 'updateUserEmail', $me->email, 'off') ?>
				</main>
			</section>
			<section class="widget">
				<header><h2><i class="fa-regular fa-lock"></i>Password</h2></header>
				<form method="post" action="changePassword">
					<main class="fieldGrid">
						<label class="field"><span>New password</span><input type="password" name="password" autocomplete="new-password" required></label>
						<label class="field"><span>Repeat new password</span><input type="password" name="password_repeat" autocomplete="new-password" required></label>
					</main>
					<footer><button class="button submit" type="submit"><i class="fa-regular fa-check"></i>Save password</button></footer>
				</form>
			</section>
		<?php } ?>
		<section class="widget">
			<header><h2><i class="fa-regular fa-key"></i>Passkeys</h2></header>
			<main>
				<p class="tagsHint">Sign in from a device without typing a password.</p>
				<p class="profileEmpty">No passkeys yet.</p>
			</main>
		</section>
		<section class="widget">
			<header><h2><i class="fa-regular fa-phone-laptop"></i>Devices</h2></header>
			<main>
				<p class="tagsHint">Browsers and phones that stay signed in.</p>
				<p class="profileEmpty">No devices yet.</p>
			</main>
		</section>
	</div>
</section>
