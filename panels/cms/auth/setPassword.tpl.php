<?php
$keyUser = $this->keyUser;
$keyValid = $keyUser !== null && \Systopic\System\Tables\Users\Operator::keyValid($keyUser);
if (!$keyValid) {
    $authTitle = 'Request a new key';
    $authLead = 'Your activation key was not found or is expired, please get a new one';
    $authForm = '<a class="button submit ajax" href="' . htmlspecialchars(\http::$root . 'cms/auth/?view=resetPassword', ENT_QUOTES) . '"><i class="cmsIcon cmsIcon-paper-plane"></i> Resend key</a>';
    include dirname(__DIR__, 3) . '/src/Auth/form.card.tpl.php';
    return;
}
$authTitle = 'Choose a password';
$authLead = 'At least 15 characters. You can use spaces and a passphrase.';
ob_start();
?>
<form class="appLoginForm" method=post action=setPassword data-password-check data-password-strict="<?= STAGE === 'LIVE' ? 'true' : 'false' ?>" onsubmit='this.classList.add("loading")'>
			<input type=hidden name=key value="<?= htmlspecialchars((string) http::get('key', http::post('key')), ENT_QUOTES) ?>"/>
			<div class="row">
				<div class='inputGroup'><i class="cmsIcon cmsIcon-lock"></i>
					<input type=password name="password" placeholder='New Password' required spellcheck="false" autocapitalize="off"
						   autocomplete="new-password">
					<button type="button" class="authReveal" aria-label="Show password" onclick="var i=this.parentElement.querySelector('input');var show=i.type==='password';i.type=show?'text':'password';this.setAttribute('aria-label',show?'Hide password':'Show password');this.querySelector('i').className=show?'cmsIcon cmsIcon-eye-slash':'cmsIcon cmsIcon-eye';"><i class="cmsIcon cmsIcon-eye"></i></button>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div class='inputGroup'><i class="cmsIcon cmsIcon-lock"></i>
					<input type=password name="password_repeat" placeholder='Repeat New Password' required spellcheck="false" autocapitalize="off"
						   autocomplete="new-password">
					<button type="button" class="authReveal" aria-label="Show password" onclick="var i=this.parentElement.querySelector('input');var show=i.type==='password';i.type=show?'text':'password';this.setAttribute('aria-label',show?'Hide password':'Show password');this.querySelector('i').className=show?'cmsIcon cmsIcon-eye-slash':'cmsIcon cmsIcon-eye';"><i class="cmsIcon cmsIcon-eye"></i></button>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button type="submit" class="submit" disabled><i class="cmsIcon cmsIcon-check"></i> Save password and sign in</button>
				<!--	<button name=action value=setPasswordAndMakeSuperuser>Set Password and make Superuser</button> -->
			</div>
		</form>
<?php
$authForm = ob_get_clean();
include dirname(__DIR__, 3) . '/src/Auth/form.card.tpl.php';
