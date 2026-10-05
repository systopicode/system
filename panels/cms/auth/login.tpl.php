<h1>Login</h1>
<main>
    <div class="row">
		<div class="message"><?= message::flush() ?></div>
    </div>
    <div class="row">
		<form action=doLogin onsubmit='this.classList.add("loading")' >
			<div class="row">
				<div class='inputGroup'>
					<input required spellcheck="false" placeholder="E-Mail" autocapitalize="off" type=text
						   name=username
						   value="<?= http::put('username', '') ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div class='inputGroup'>
					<input required spellcheck="false" placeholder="Password" type=password name=password
						   autocapitalize="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class='submit'>Login</button>
			</div>
		</form>
    </div>
    <div class="row">
		<div>
			<br>
			<p><a href="resetPassword" class="ajax">Forgot password? Reset here</a></p>
			<p><a href="register" class="ajax">No Account? Register here</a></p>
			<?php //			<p><a href="root/" class="ajax">New Installation? Create Superuser</a></p> ?>
		</div>
    </div>
</main>