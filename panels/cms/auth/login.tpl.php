<h1>Sign in</h1>
<p>&nbsp;</p>
<main>
    <div class="row authMessages">
		<?= message::flush([], true) ?>
    </div>
    <?php if (\Systopic\System\Auth\Passkeys::available()) {
        \http::sessionStartOnce();
        $passkeyEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
    ?>
    <div class="passkeys passkeyLogin" data-passkey-endpoint="<?= $passkeyEscape(\http::$root . 'cms/auth/') ?>" data-passkey-origin="<?= $passkeyEscape(\Systopic\System\Auth\Passkeys::config()['origin']) ?>" data-passkey-token="<?= $passkeyEscape(\Systopic\System\Auth\PasskeyChallenge::token()) ?>">
        <button type="button" class="button submit passkeyLoginButton" data-on_click="passkeyAction" data-passkey-mode="login"><?php include dirname(__DIR__, 3) . '/src/Auth/fingerprint.tpl.php'; ?>Sign in with a passkey</button>
        <div class="passkeyAuthFeedback" hidden>
            <div class="passkeyArtwork"><?php include dirname(__DIR__, 3) . '/src/Auth/passkey-icons.tpl.php'; ?></div>
        </div>
        <button type="button" class="button passkeyLoginCancel" data-on_click="passkeyAction" data-passkey-mode="cancel">Cancel</button>
    </div>
    <?php } ?>
    <p class="authPasswordAlternative"><?= \Systopic\System\Auth\Passkeys::available() ? 'Or sign in with email and password' : 'Sign in with email and password' ?></p>
    <div class="row">
		<form action=doLogin onsubmit='this.classList.add("loading")' >
			<div class="row">
				<div class='inputGroup'><i class="fa-regular fa-envelope"></i>
					<input required spellcheck="false" placeholder="Email" autocapitalize="off" type="email" autocomplete="username webauthn"
						   name=username
						   value="<?= htmlspecialchars((string) http::put('username', ''), ENT_QUOTES) ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div class='inputGroup'><i class="fa-regular fa-lock"></i>
					<input required spellcheck="false" placeholder="Password" type=password name=password autocomplete="current-password"
						   autocapitalize="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class='button authPasswordButton'><i class="fa-regular fa-arrow-right"></i> Sign in</button>
			</div>
		</form>
    </div>

    <div class="row">
		<div>
			<br>
			<p class="authLinks">
				<a href="resetPassword" class="ajax"><i class="fa-regular fa-key"></i> Forgot password</a>
				<a href="register" class="ajax"><i class="fa-regular fa-user-plus"></i> Create an account</a>
			</p>
			<?php //			<p><a href="root/" class="ajax">New Installation? Create Superuser</a></p> ?>
		</div>
    </div>
</main>