<?php
$authTitle = 'Activate account';
$authLead = 'Check your inbox, and the spam folder.';
ob_start();
?>
<form class="appLoginForm" method=get action=activate  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'><i class="cmsIcon cmsIcon-hashtag"></i>
					<input type=text name=key placeholder="Activation code" required spellcheck="false" autocapitalize="off"
						   autocomplete="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					<br>
					<button class='submit'><i class="cmsIcon cmsIcon-check"></i> Activate</button>
				</div>
			</div>
		</form>
<?php
$authForm = ob_get_clean();
include dirname(__DIR__, 3) . '/src/Auth/form.card.tpl.php';
