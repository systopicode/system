<h1>Activate your account</h1>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row"><p>If you did not receive an email please also check your spam folder.</p></div>
	<div class="row">
		<form method=get action=activate  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'>
					<label><b>Please enter the activation code</b></label>
					<input type=text name=key required spellcheck="false" autocapitalize="off"
						   autocomplete="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					<br>
					<button class='submit'>activate account</button>
				</div>
			</div>
		</form>
	</div>
</main>
