<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$text = \Systopic\System\Sys\Release::license();
?>
<h1>License</h1>
<main class="authText">
	<div class="authScroll">
	<p>fluxo cms and the systopic framework are published by systopic GmbH under the systopic Software License. Public access to the source code does not grant unrestricted use or redistribution. Third-party components retain their respective licenses.</p>
	<pre><?= htmlspecialchars($text, ENT_QUOTES) ?></pre>
    <h2>Third-party software</h2>
    <p>The following components keep their own copyright and licence terms. The systopic Software License does not replace those rights. Full notices are included below; source links identify the upstream projects.</p>
    <?php foreach (\Systopic\System\Sys\ThirdParty::entries() as $entry) { ?>
    <details><summary><?= htmlspecialchars($entry['name'], ENT_QUOTES) ?> · <?= htmlspecialchars($entry['license'], ENT_QUOTES) ?></summary>
    <p><?= htmlspecialchars($entry['author'], ENT_QUOTES) ?><br><?php if ($entry['source'] !== '') { ?><a href="<?= htmlspecialchars($entry['source'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><i class="cmsIcon cmsIcon-arrow-up-right-from-square"></i>Source project and notices</a><?php } ?></p>
    <?php foreach ($entry['texts'] as $notice) { ?><pre><?= htmlspecialchars($notice, ENT_QUOTES) ?></pre><?php } ?>
    </details>
    <?php } ?>
    <h2>Optional server tools</h2>
    <p>Server-side tools such as FFmpeg, FFprobe and ImageMagick are supplied by the host or the project operator. Their licences depend on the installed build and linked components. Distributing those binaries requires the corresponding notices and, where applicable, source-code provisions; this page does not grant additional rights to those tools. See <a href="https://ffmpeg.org/legal.html" target="_blank" rel="noopener noreferrer">FFmpeg licensing</a> and <a href="https://imagemagick.org/script/license.php" target="_blank" rel="noopener noreferrer">ImageMagick licensing</a>.</p>
	</div>
</main>
