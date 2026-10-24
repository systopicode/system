<h1>Activate account</h1>
<p class="authLead">Check your inbox, and the spam folder.</p>
<main>
	<div class="row">
		<div class="message"><?= message::flush() ?></div>
	</div>
	<div class="row">
		<form method=get action=activate  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'>
					<input type=text name=key placeholder="Activation code" required spellcheck="false" autocapitalize="off"
						   autocomplete="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<div>
					<br>
					<button class='submit'><i class="fa-regular fa-check"></i> Activate</button>
				</div>
			</div>
		</form>
	</div>
</main>
