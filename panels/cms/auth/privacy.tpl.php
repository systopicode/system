<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$site = rtrim((string) http::$root, '/');
$privacy = $site . '/datenschutz/';
$imprint = $site . '/impressum/';
?>
<h1>Privacy</h1>
<main class="authText">
	<div class="authScroll">
	<p>This page describes what fluxo cms does when you sign in. It is the notice for that step. The operator’s privacy policy for the rest of the website is published separately and does not have to repeat it:</p>
	<p><a href="<?= htmlspecialchars($privacy, ENT_QUOTES) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($privacy, ENT_QUOTES) ?></a></p>
	<p>The controller is the operator named in the <a href="<?= htmlspecialchars($imprint, ENT_QUOTES) ?>" target="_blank" rel="noopener">imprint</a>.</p>
	<p>Signing in processes only what access needs:</p>
	<ul>
		<li>Your email address, and your password. The password is checked and not kept as you typed it. The account stores a hash.</li>
		<li>A session cookie, so the following requests belong to the signed-in account. It ends when the session ends.</li>
		<li>A device cookie in this browser, so a later visit can be recognised. It stays 180 days unless this installation is set otherwise. Signing out removes it.</li>
	</ul>
	<p>The purpose is access to fluxo cms for accounts that are allowed in, and keeping that access limited to them. The legal bases are Art. 6 (1) (b) GDPR, where the account is used under an agreement, and Art. 6 (1) (f) GDPR, because the operator has a legitimate interest in letting only authorised people change the website. You cannot sign in without the email address and the password. Nothing else is required.</p>
	<p>The data stay on the systems of this website. Other recipients are only those named in the operator’s privacy policy, for example the host.</p>
	<p>The hash is kept for as long as the account exists. The operator deletes the account. The cookies last as set out above.</p>
	<p>You can ask the operator for access, correction, deletion or restriction, and you can object to the processing based on legitimate interest. You can also complain to a supervisory authority. How to reach the operator is in the imprint. The authority is named in the operator’s privacy policy.</p>
	</div>
	<p class="authLinks"><a href="login" class="ajax"><i class="fa-regular fa-chevron-left"></i> Back to sign in</a></p>
</main>
