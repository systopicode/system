<?php
$authTitle = 'Enter the code';
$authLead = 'Check your inbox, and the spam folder.';
ob_start();
?>
<form class="appLoginForm" method=get action=reset  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'><i class="cmsIcon cmsIcon-hashtag"></i>
					<input type=text name="key" placeholder="Reset Code" required autocapitalize="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class='submit'><i class="cmsIcon cmsIcon-check"></i> Verify code</button>
			</div>
		</form>
<?php
$authForm = ob_get_clean();
include dirname(__DIR__, 3) . '/src/Auth/form.card.tpl.php';
