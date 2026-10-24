<?php

use Systopic\System\Http\Assets;

$e = static fn(mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES);
$mode = \http::$assetsMode;
$hint = match ($mode) {
	'source' => 'The web server reads the original folders. Copies in public/assets/ are not used.',
	'serve' => 'Each request reads the original through asset.php. Copies are not used.',
	'publish' => 'The browser reads copies in public/assets/. They are made on first use and replaced when the original is newer.',
	default => 'How scripts, styles and fonts reach the browser.',
};
?>
<h1>Assets</h1>
<p class="tagsHint">Scripts, styles and fonts from the packages and from this project.</p>
<div class="widgets">
	<section class="widget">
		<header><h2>Delivery</h2></header>
		<main>
			<p class="tagsHint"><?= $e($hint) ?></p>
			<div class="assetsStatus"><?= include __DIR__ . '/status.php' ?></div>
		</main>
		<footer>
			<a class="button submit put" href="publishAll">Publish all</a>
			<a class="button" data-on_click="userConfirm" data-action="clearAll" data-confirm="Remove all published copies? They are made again on first use.">Clear all</a>
		</footer>
	</section>
	<section class="widget">
		<header><h2>Sources</h2></header>
		<main>
			<p class="tagsHint">The folders the copies are made from.</p>
			<table>
				<tbody>
					<?php foreach (Assets::roots() as $name => $dir) { ?>
						<tr>
							<td class="muted"><?= $e($name) ?></td>
							<td><?= $e($dir) ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</main>
	</section>
</div>
