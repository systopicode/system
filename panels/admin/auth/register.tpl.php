<?php
$authTitle = 'Create account';
$authLead = 'Use an email address your administrator has allowed.';
ob_start();
?>
<form class="appLoginForm" method=post action=createAccount  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'><i class="cmsIcon cmsIcon-envelope"></i>
					<input required spellcheck="false" autocapitalize="off" placeholder="Email" type=email autocomplete="username"
						   name=email_create
						   value="<?= htmlspecialchars((string) http::post('email_create'), ENT_QUOTES) ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					 <br>
					<button class='submit'><i class="cmsIcon cmsIcon-user-plus"></i> Create account</button>
				</div>
			</div>
		</form>
<?php
$authForm = ob_get_clean();
include dirname(__DIR__, 3) . '/src/Auth/form.card.tpl.php';
