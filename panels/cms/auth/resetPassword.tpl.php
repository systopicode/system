<h1>Reset password</h1>
<p class="authLead">We email you a code.</p>
<main>
	<div class="row">
		<?= message::flush() ?>
	</div>
	<div class="row">
		<form method=post action=sendResetCode  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'><i class="fa-regular fa-envelope"></i>
					<input required spellcheck=false autocapitalize=off placeholder="Email" type=email name=email_reset autocomplete="username"
						   value="<?= http::post('email_reset') ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					<br>
					<button class="submit"><i class="fa-regular fa-paper-plane"></i> Send code</button>
				</div>
			</div>
		</form>
	</div>
	<div class="row">
		<div>
		    <br>
			<p class="authLinks"><a href="login" class="ajax"><i class="fa-regular fa-chevron-left"></i> Back to sign in</a></p>
		</div>
	</div>
</main>
