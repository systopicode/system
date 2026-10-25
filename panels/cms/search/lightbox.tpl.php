<?php
/** @var \Systopic\System\Panels\Root\Cms\Search\Panel $this */
?>
<div class="cmslightbox cms cmsFind" data-size="2">
	<section>
		<header>
			<input type="search" name="query" data-on_input="search" value="<?= htmlspecialchars((string) $this->state->query, ENT_QUOTES) ?>" placeholder="Search everything" aria-label="Search" autocomplete="off" autofocus>
			<ul class="barmenu">
				<li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<?php $this->withView('results'); ?>
		</main>
	</section>
</div>
