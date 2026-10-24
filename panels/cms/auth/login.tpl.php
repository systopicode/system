<h1>Sign in</h1>
<p class="authLead">Sign in to fluxo cms.</p>
<main>
    <div class="row">
		<div class="message"><?= message::flush() ?></div>
    </div>
    <div class="row">
		<form action=doLogin onsubmit='this.classList.add("loading")' >
			<div class="row">
				<div class='inputGroup'>
					<input required spellcheck="false" placeholder="Email" autocapitalize="off" type="email" autocomplete="username"
						   name=username
						   value="<?= http::put('username', '') ?>"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div class='inputGroup'>
					<input required spellcheck="false" placeholder="Password" type=password name=password autocomplete="current-password"
						   autocapitalize="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class='submit'><i class="fa-regular fa-arrow-right"></i> Sign in</button>
			</div>
		</form>
    </div>
    <div class="row">
		<div>
			<br>
			<p class="authLinks">
				<a href="resetPassword" class="ajax"><i class="fa-regular fa-key"></i> Forgot password</a>
				<a href="register" class="ajax"><i class="fa-regular fa-user-plus"></i> Create an account</a>
			</p>
			<?php //			<p><a href="root/" class="ajax">New Installation? Create Superuser</a></p> ?>
		</div>
    </div>
</main>