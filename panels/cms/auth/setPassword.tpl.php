<h1>Set password</h1>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row">
		<div>
			<h3>Type your very secret password twice here. </h3>
			<p>It must be at least 5 characters long contain an uppercase letter, a number and one special
				char.</p>
		</div>
	</div>
	<div class="row">
		<form method=post action=setPassword  onsubmit='this.classList.add("loading")'>
			<input type=hidden name=key value="<?= http::get('key', http::post('key')) ?>"/>
			<div class="row">
				<div class='inputGroup'>
					<input type=password name="password" placeholder='New Password' required spellcheck="false" autocapitalize="off"
						   autocomplete="new-password">
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div class='inputGroup'>
					<input type=password name="password_repeat" placeholder='Repeat New Password' required spellcheck="false" autocapitalize="off"
						   autocomplete="new-password">
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class="submit">Set Password</button>
				<!--	<button name=action value=setPasswordAndMakeSuperuser>Set Password and make Superuser</button> -->
			</div>
		</form>
	</div>
</main>
