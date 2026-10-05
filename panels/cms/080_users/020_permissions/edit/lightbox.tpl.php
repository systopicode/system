<?php /** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */ ?>
<div class='cmslightbox cms' data-size="2">
	<section data-doc=box>
		<?php
		if ($this->group) {
			$this->withView('group');
		}
		if ($this->role) {
			$this->withView('role');
		}
		?>
	</section>
</div>
