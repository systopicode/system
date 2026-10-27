<?php
/** @var \Systopic\System\Panels\Root\Cms\Search\Panel $this */
$cms = $this->parent;
$back = '../';
$path = is_array($cms->state->submodulePath ?? null) ? array_values($cms->state->submodulePath) : [];
if ($path !== [] && ($path[0] ?? '') === 'cms') {
	array_shift($path);
}
$node = $cms;
foreach ($path as $segment) {
	$next = $node->child((string) $segment);
	if ($next === null) {
		$node = null;
		break;
	}
	$node = $next;
}
if ($node !== null && $node !== $cms) {
	$back = $node->getHref(\http::$root);
} else {
	$name = (string) ($cms->state->submoduleName ?? '');
	if ($name !== '' && ($mod = $cms->child($name)) !== null && $mod->inNavi) {
		$back = $mod->getHref(\http::$root);
	}
}
?>
<div class="cmslightbox cms cmsFind" data-size="2">
	<section>
		<header>
			<input type="search" name="query" data-on_input="search" value="<?= htmlspecialchars((string) $this->state->query, ENT_QUOTES) ?>" placeholder="Search everything" aria-label="Search" autocomplete="off" autofocus>
			<ul class="barmenu">
				<li><a href="<?= htmlspecialchars($back, ENT_QUOTES) ?>" data-on_click="closeSearch" title="Close"><i class="fa-regular fa-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<?php $this->withView('results'); ?>
		</main>
	</section>
</div>
