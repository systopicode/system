<h1>Password lost?</h1>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row">
		<form method=post action=sendResetCode  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'>
					<input required spellcheck=false autocapitalize=off placeholder=E-Mail type=email name=email_reset
						   value="<?= http::post('email_reset') ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					<br>
					<button class="submit">Reset password</button>
				</div>
			</div>
		</form>
	</div>
	<div class="row">
		<div>
		    <br>
			<p><a href="login" class="ajax">Already have an account? Go to Login</a></p>
		</div>
	</div>
</main>
