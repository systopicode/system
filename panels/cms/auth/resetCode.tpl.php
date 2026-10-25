<h1>Enter the code</h1>
<p class="authLead">Check your inbox, and the spam folder.</p>
<main>
	<div class="row">
		<?= message::flush() ?>
	</div>
	<div class="row">
		<form method=get action=reset  onsubmit='this.classList.add("loading")'>
			<div class="row">
				<div class='inputGroup'><i class="fa-regular fa-hashtag"></i>
					<input type=text name="key" placeholder="Reset Code" required autocapitalize="off"/>
					<span class='bar'></span>
				</div>
			</div>
			<div class="row">
				<br>
				<button class='submit'><i class="fa-regular fa-arrow-right"></i> Continue</button>
			</div>
		</form>
	</div>
</main>
