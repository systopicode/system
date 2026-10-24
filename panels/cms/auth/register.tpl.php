<h1>Create account</h1>
<p class="authLead">Use an email address your administrator has allowed.</p>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row">
		<form method=post action=createAccount  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'>
					<input required spellcheck="false" autocapitalize="off" placeholder="Email" type=email autocomplete="username"
						   name=email_create
						   value="<?= http::post('email_create') ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					 <br>
					<button class='submit'><i class="fa-regular fa-user-plus"></i> Create account</button>
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
