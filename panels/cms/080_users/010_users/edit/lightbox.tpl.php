<?php /** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */ ?>
<div class="cms cmslightbox userEdit" data-size="2">
	<section>
		<?php
		if ($this->user) {
			$this->withView('user');
		}
		?>
	</section>
</div>
