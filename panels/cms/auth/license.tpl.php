<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$text = \Systopic\System\Sys\Release::license();
?>
<h1>License</h1>
<main class="authText">
	<div class="authScroll">
	<p>fluxo cms is published under the MIT License.</p>
	<pre><?= htmlspecialchars($text, ENT_QUOTES) ?></pre>
	</div>
</main>
