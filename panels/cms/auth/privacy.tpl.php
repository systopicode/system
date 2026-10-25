<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$site = rtrim((string) http::$root, '/');
$privacy = $site . '/datenschutz/';
$imprint = $site . '/impressum/';
?>
<h1>Privacy</h1>
<main class="authText">
	<div class="authScroll">
	<p>This page describes what fluxo cms does when you sign in and while you work in it. The operator’s privacy policy for the rest of the website is published separately and does not have to repeat it:</p>
	<p><a href="<?= htmlspecialchars($privacy, ENT_QUOTES) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($privacy, ENT_QUOTES) ?></a></p>
	<p>The controller is the operator named in the <a href="<?= htmlspecialchars($imprint, ENT_QUOTES) ?>" target="_blank" rel="noopener">imprint</a>.</p>
	<h2>Signing in</h2>
	<ul>
		<li>Your email address, and your password. The password is checked and not kept as you typed it. The account stores a hash.</li>
		<li>If you use them: a passkey, of which the account keeps only the public part, or a one-time code by email or QR code, which is stored as a hash and can be used once.</li>
		<li>A session cookie, so the following requests belong to the signed-in account. It ends when the session ends.</li>
		<li>A device cookie in this browser, so a later visit is signed in again. The account lists the device with a name taken from the browser (for example “iPad · Safari”), the browser’s user agent, when it was last used and its last IP address. It stays 180 days unless this installation is set otherwise. Signing out or removing the device in your account ends it.</li>
	</ul>
	<h2>Login log and failed attempts</h2>
	<p>Every sign-in, failed sign-in and sign-out is written to a log with the time, the name or email address typed, the IP address and the way of signing in. Failed attempts are also counted per IP address, so that guessing gets blocked. Administrators who manage users can see the log and the latest sign-ins on the dashboard.</p>
	<h2>Pictures from other services</h2>
	<p>Some pictures in fluxo cms are loaded by your browser from other providers. Your browser then sends them your IP address and the usual request data, as with any picture on the web.</p>
	<ul>
		<li>Portraits: an account without its own photo shows a drawn face from DiceBear (api.dicebear.com). The address it is requested with contains only a checksum (md5) of the email address and the colours of the CMS, not the address itself. An uploaded photo stays on this website.</li>
		<li>Language flags: from flagcdn (flagcdn.com), with nothing more than the language code.</li>
		<li>Video previews: YouTube videos are embedded through youtube-nocookie.com, Vimeo videos through player.vimeo.com, only where a page contains such a video.</li>
	</ul>
	<p>Gravatar is not used.</p>
	<h2>Purpose and legal basis</h2>
	<p>The purpose is access to fluxo cms for accounts that are allowed in, keeping that access limited to them, and being able to trace misuse. The legal bases are Art. 6 (1) (b) GDPR, where the account is used under an agreement, and Art. 6 (1) (f) GDPR, because the operator has a legitimate interest in letting only authorised people change the website and in keeping it secure. You cannot sign in without the email address and a way of signing in. Nothing else is required.</p>
	<h2>Recipients and storage</h2>
	<p>Apart from the pictures above, the data stay on the systems of this website. Other recipients are only those named in the operator’s privacy policy, for example the host.</p>
	<p>The account data are kept for as long as the account exists. The operator deletes the account. The cookies and devices last as set out above. Entries of the login log are deleted after 90 days.</p>
	<p>You can ask the operator for access, correction, deletion or restriction, and you can object to the processing based on legitimate interest. You can also complain to a supervisory authority. How to reach the operator is in the imprint. The authority is named in the operator’s privacy policy.</p>
	</div>
	<p class="authLinks"><a href="login" class="ajax"><i class="fa-regular fa-chevron-left"></i> Back to sign in</a></p>
</main>
