<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$imprint = rtrim((string) http::$root, '/') . '/impressum/';
?>
<h1>Imprint</h1>
<main class="authText">
	<div class="authScroll">
	<p>The provider of this website, and of this login, is named in the imprint required by § 5 DDG:</p>
	<p><a href="<?= htmlspecialchars($imprint, ENT_QUOTES) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($imprint, ENT_QUOTES) ?></a></p>
	<p>That page carries the name, the address, a way to make contact and, where the law requires them, the register, the registration number, the VAT id and the person responsible for the content. Those details are not repeated here.</p>
	<p>fluxo cms is the software used to edit the website. It is published by systopic GmbH. The operator named in the imprint is responsible for this installation.</p>
	</div>
</main>
