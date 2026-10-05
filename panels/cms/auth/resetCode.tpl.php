<h1>Reset password</h1>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row">
		<div>
			<p>If you did not receive an email please also check your spam folder.</p>
		</div>
	</div>
	<div class="row">
		<form method=get action=reset  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'>
					<input type=text name="key" placeholder="Reset Code" required autocapitalize="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class='submit'>reset password</button>
			</div>
		</form>
	</div>
</main>
