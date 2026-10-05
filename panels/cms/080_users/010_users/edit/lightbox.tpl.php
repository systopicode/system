<?php /** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */ ?>
<div class="cms cmslightbox" data-size="1">
	<section>
		<?php
		if ($this->user) {
			$this->withView('user');
		}
		if ($this->role) {
			$this->withView('role');
		}
		?>
	</section>
</div>
