<?php /** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */
if (!$this->group) {
	return;
}
?>
<div class="cmslightbox cms" data-size="1">
	<section>
		<?php if ($this->group) {
			$this->withView('group');
		} ?>
	</section>
</div>
