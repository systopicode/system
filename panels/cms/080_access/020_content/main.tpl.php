<?php /** @var \Systopic\System\Panels\Root\Cms\Access\Content\Panel $this */ ?>
<section class="cms">
	<h1>Content</h1>
	<p class="tagsHint">Permission groups define which roles can read or change an area.</p>
	<?php $this->withView('groups'); ?>
</section>
