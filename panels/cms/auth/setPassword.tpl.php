<h1>Choose a password</h1>
<p class="authLead">At least 5 characters, with an uppercase letter, a number and a symbol.</p>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row">
		<form method=post action=setPassword  onsubmit='this.classList.add("loading")'>
			<input type=hidden name=key value="<?= http::get('key', http::post('key')) ?>"/>
			<div class="row">
				<div class='inputGroup'><i class="fa-regular fa-lock"></i>
					<input type=password name="password" placeholder='New Password' required spellcheck="false" autocapitalize="off"
						   autocomplete="new-password">
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div class='inputGroup'><i class="fa-regular fa-lock"></i>
					<input type=password name="password_repeat" placeholder='Repeat New Password' required spellcheck="false" autocapitalize="off"
						   autocomplete="new-password">
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class="submit"><i class="fa-regular fa-check"></i> Save password</button>
				<!--	<button name=action value=setPasswordAndMakeSuperuser>Set Password and make Superuser</button> -->
			</div>
		</form>
	</div>
</main>
