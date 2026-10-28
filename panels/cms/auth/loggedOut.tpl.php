<?php
$again = \http::$root . 'cms/auth/?choose=1';
?>
<h1>Signed out</h1>
<p class="authLead">See you later.</p>
<main>
	<a class="button submit" href="<?= htmlspecialchars($again, ENT_QUOTES) ?>"><i class="fa-regular fa-right-to-bracket" aria-hidden="true"></i>Sign in again</a>
	<div class="authProgress is-slow" aria-hidden="true"><span></span></div>
	<p class="authWait">Opens in 30 seconds.</p>
</main>
<script>
	setTimeout(function () {
		window.location.href = <?= json_encode($again) ?>;
	}, 30000);
</script>
