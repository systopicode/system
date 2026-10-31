<?php
$again = \http::$root . 'cms/auth/?choose=1';
?>
<main class="authSignin">
    <div class="authPane">
        <div class="authHero authSignedOut">
            <h1>Signed out</h1>
            <p class="authLead">See you later.</p>
            <div class="authProgress is-slow" aria-hidden="true"><span></span></div>
            <p class="authWait">Opens in 30 seconds.</p>
            <a class="button submit" href="<?= htmlspecialchars($again, ENT_QUOTES) ?>"><i class="cmsIcon cmsIcon-right-to-bracket" aria-hidden="true"></i>Sign in again</a>
        </div>
    </div>
</main>
<script>
	setTimeout(function () {
		window.location.href = <?= json_encode($again) ?>;
	}, 30000);
</script>
