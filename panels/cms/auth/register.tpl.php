<h1>Create account</h1>
<p class="authLead">Use an email address your administrator has allowed.</p>
<main>
	<div class="row">
		<?= message::flush() ?>
	</div>
	<div class="row">
		<form method=post action=createAccount  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'><i class="fa-regular fa-envelope"></i>
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
</main>
