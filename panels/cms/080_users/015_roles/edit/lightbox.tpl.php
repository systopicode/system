<?php /** @var \Systopic\System\Panels\Root\Cms\Users\Roles\Edit\Panel $this */
if (!$this->role) {
	return;
}
?>
<div class="cmslightbox cms" data-size="1">
	<section>
		<?php if ($this->role) {
			$this->withView('role');
		} ?>
	</section>
</div>
