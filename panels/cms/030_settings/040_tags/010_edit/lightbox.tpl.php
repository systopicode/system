<?php
/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Edit\Panel $this */

$tag = $this->tag;
$count = count($this->nodeIds);
$this->loadJS();
?>
<div class="cmslightbox cms" data-doc=box>
	<section>
		<?php if ($tag) { ?>
			<header>
				<h1 data-doc=tagname><?= htmlspecialchars((string) $tag->name) ?></h1>
				<?=
				\menu::create('barmenu')
						->item('toggleMenu')->icon('far fa-ellipsis-v')
						->openSubmenu()
						->item('rename')->icon('far fa-edit')->label('rename')
						->closeSubmenu()
						->link('../')->icon('fa-light fa-xmark-large')
				?>
			</header>
			<main>
				<div class="fullColumn">
					<h3><?= $count ?: 'No' ?> Page<?= $count === 1 ? '' : 's' ?> assigned to <?= htmlspecialchars((string) $tag->name) ?></h3>
				</div>
			</main>
		<?php } ?>
	</section>
</div>
