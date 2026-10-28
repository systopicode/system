<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$revision = $this->revision();
$gui = (string) array_key_first($this->releases);
$site = rtrim((string) http::$root, '/') . '/';
?>
<footer class="authFooter">
	<span>© <?= date('Y') ?> systopic GmbH · <a href="?view=releases" class="ajax" title="Release notes">GUI <?= htmlspecialchars($gui, ENT_QUOTES) ?><?php if ($revision !== '') { ?> · <?= htmlspecialchars($revision, ENT_QUOTES) ?><?php } ?></a></span>
	<nav>
		<a href="?view=license" class="ajax"><i class="fa-regular fa-file-contract"></i> License</a>
		<a href="?view=imprint" class="ajax"><i class="fa-regular fa-building"></i> Imprint</a>
		<a href="?view=privacy" class="ajax"><i class="fa-regular fa-shield-halved"></i> Privacy</a>
		<a href="<?= htmlspecialchars($site, ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-up-right-from-square"></i> Website</a>
		<a href="https://fluxo-help.1f2c.de" target="_blank" rel="noopener"><i class="fa-regular fa-question-circle"></i> Help</a>
	</nav>
</footer>
