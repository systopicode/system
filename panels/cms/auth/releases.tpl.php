<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES);
?>
<h1>Release notes</h1>
<main class="authText">
	<div class="authScroll">
	<?php foreach ($this->releases as $version => $release) { ?>
		<h2>GUI <?= $e((string) $version) ?> <small><?= $e($release['date']) ?></small></h2>
		<?php foreach (['new' => 'New', 'fixed' => 'Fixed'] as $key => $title) {
			if (empty($release[$key])) {
				continue;
			} ?>
			<h3><?= $title ?></h3>
			<ul>
				<?php foreach ($release[$key] as $note) { ?>
					<li><?= $e($note) ?></li>
				<?php } ?>
			</ul>
		<?php } ?>
	<?php } ?>
	</div>
</main>
