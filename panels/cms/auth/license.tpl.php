<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$path = rtrim((string) fs::$sysRoot, '/') . '/LICENSE';
$text = fs::isFile($path) ? (string) fs::file_get_contents($path) :'MIT License. Copyright (c) systopic GmbH.';
?>
<h1>License</h1>
<main class="authText">
	<p>fluxo cms is published under the MIT License.</p>
	<pre><?= htmlspecialchars($text, ENT_QUOTES) ?></pre>
	<p class="authLinks"><a href="login" class="ajax"><i class="fa-regular fa-chevron-left"></i> Back to sign in</a></p>
</main>
