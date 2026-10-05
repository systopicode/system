<h1>New here?</h1>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row">
		<form method=post action=createAccount  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'>
					<input required spellcheck="false" autocapitalize="off" placeholder="E-Mail" type=email
						   name=email_create
						   value="<?= http::post('email_create') ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					 <br>
					<button class='submit'>Create account</button>
				</div>
			</div>
		</form>
	</div>
	<div class="row">
		<div>
			 <br>
			<p><a href=login class="ajax">Already have an account? Go to Login</a></p>
		</div>
	</div>
</main>
