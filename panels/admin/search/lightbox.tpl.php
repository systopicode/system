<?php
/** @var \Systopic\System\Panels\Root\Admin\Search\Panel $this */
?>
<div class="cmslightbox cms cmsFind" data-size="2">
	<section>
		<header>
			<input type="search" name="query" data-on_input="<?= htmlspecialchars(\http::$root . 'admin/search/search', ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) $this->state->query, ENT_QUOTES) ?>" placeholder="Search everything" aria-label="Search" autocomplete="off" autofocus>
			<ul class="barmenu">
				<li><button type="button" data-close-find aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
		<main>
			<?php include __DIR__ . '/results.tpl.php'; ?>
		</main>
	</section>
</div>
