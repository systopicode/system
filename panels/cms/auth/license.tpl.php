<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$text = \Systopic\System\Sys\Release::license();
?>
<h1>License</h1>
<main class="authText">
	<div class="authScroll">
	<p>fluxo cms and the systopic framework are published by systopic GmbH under the systopic Software License. Public access to the source code does not grant unrestricted use or redistribution. Third-party components retain their respective licenses.</p>
	<pre><?= htmlspecialchars($text, ENT_QUOTES) ?></pre>
	</div>
</main>
