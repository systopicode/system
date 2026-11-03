<?php
$again = \http::$root . 'admin/auth/?choose=1';
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
(function () {
    const screen = document.currentScript.previousElementSibling;
    const again = screen.querySelector('a.button');
    const timer = setTimeout(function () {
        // An AJAX navigation may already have replaced the signed-out card.
        if (screen.isConnected) window.location.href = <?= json_encode($again) ?>;
    }, 30000);
    again.addEventListener('click', function () { clearTimeout(timer); }, {once: true, capture: true});
})();
</script>
