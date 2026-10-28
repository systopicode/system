<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$site = rtrim((string) http::$root, '/');
$privacy = $site . '/datenschutz/';
$imprint = $site . '/impressum/';
?>
<h1>Privacy</h1>
<main class="authText">
	<div class="authScroll">
	<p>This notice states which personal data fluxo cms processes when you sign in and while the account is in use. The operator’s privacy policy for the rest of the website is published separately:</p>
	<p><a href="<?= htmlspecialchars($privacy, ENT_QUOTES) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($privacy, ENT_QUOTES) ?></a></p>
	<p>The controller is the operator named in the <a href="<?= htmlspecialchars($imprint, ENT_QUOTES) ?>" target="_blank" rel="noopener">imprint</a>. If the operator has appointed a data protection officer, the contact is in that privacy policy.</p>
	<h2>Data processed when you sign in</h2>
	<ul>
		<li>Your email address.</li>
		<li>Your password. It is checked and not kept as you typed it. The account stores a hash.</li>
		<li>A passkey, if you add one. The account keeps the public key, a name you choose, when it was last used and a counter. The private key and your fingerprint or face are not sent here.</li>
		<li>A one-time code, if you use one. It is stored as a hash and can be used once. An invitation or a reset is sent to the email address. A camera picture from scanning a code is not sent here and not stored.</li>
		<li>A session cookie, so the following requests belong to the signed-in account. It ends when the session ends.</li>
		<li>A device cookie in this browser, so a later visit is signed in again. The account stores a name taken from the browser (for example “iPad · Safari”), the browser’s user agent, when it was last used and its last IP address. It stays 180 days unless this installation is set otherwise. Signing out or removing the device ends it.</li>
	</ul>
	<h2>Login log and failed attempts</h2>
	<p>Every sign-in, failed sign-in and sign-out is written to a log with the time, the name or email address, the IP address and the way of signing in. A remote sign-in also records who confirmed it and which account the device becomes. Failed attempts are counted per IP address, to limit guessing. People who administer accounts can access the log.</p>
	<h2>Pictures from other services</h2>
	<p>Some pictures are loaded by your browser from other providers. Your browser then sends them your IP address and the usual request data.</p>
	<ul>
		<li>Portraits: an account without its own photo is requested from DiceBear (api.dicebear.com). A person is requested with a checksum of the email address and the colours of the CMS, not the address itself. A screen is requested with a checksum of the account and a robot picture. An uploaded photo stays on this website.</li>
		<li>Language flags: from flagcdn (flagcdn.com), with nothing more than the language code.</li>
		<li>Video previews: YouTube and Vimeo, only where a page contains such a video. Both are services in the United States. The operator’s privacy policy states the safeguard for that transfer.</li>
	</ul>
	<p>Gravatar is not used.</p>
	<h2>Purpose and legal basis</h2>
	<p>The purpose is access to fluxo cms for accounts that are allowed in, keeping that access limited to them, and being able to trace misuse. The legal bases are Art. 6 (1) (b) GDPR, where the account is used under an agreement, and Art. 6 (1) (f) GDPR. The legitimate interest is letting only authorised people change the website and keeping it secure. You cannot sign in without an account and a way of signing in. Nothing else is required.</p>
	<h2>Recipients and storage</h2>
	<p>Apart from the providers named above, the data stay on the systems of this website. Other recipients are those named in the operator’s privacy policy, for example the host.</p>
	<p>The account data are kept for as long as the account exists. The operator deletes the account. The cookies and devices last as set out above. Entries of the login log are kept for as long as they are needed to trace misuse. There is no fixed period.</p>
	<p>You can ask the operator for access, correction, deletion, restriction or a copy of the data you provided, and you can object to the processing based on legitimate interest. You can also complain to a supervisory authority. How to reach the operator is in the imprint. The authority is named in the operator’s privacy policy.</p>
	</div>
</main>
