<?php
$authTitle = 'Reset password';
$authLead = 'We email you a code.';
ob_start();
?>
<form class="appLoginForm" method=post action=sendResetCode  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'><i class="cmsIcon cmsIcon-envelope"></i>
					<input required spellcheck=false autocapitalize=off placeholder="Email" type=email name=email_reset autocomplete="username"
						   value="<?= htmlspecialchars((string) (http::post('email_reset', '')), ENT_QUOTES) ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					<br>
					<button class="submit"><i class="cmsIcon cmsIcon-paper-plane"></i> Send code</button>
				</div>
			</div>
	<p class="authLinks"><a class="ajax" href="?method=password">Back to sign in</a></p>
		</form>
<?php
$authForm = ob_get_clean();
include dirname(__DIR__, 3) . '/src/Auth/form.card.tpl.php';
