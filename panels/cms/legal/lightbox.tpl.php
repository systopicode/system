<?php
/** The auth panel's texts, in a lightbox. @var \Systopic\System\Panels\Root\Cms\Legal\Panel $this */
$pages = ['releases' => 'Release notes', 'license' => 'License', 'imprint' => 'Imprint', 'privacy' => 'Privacy'];
$page = (string) \http::get('page');
if (!isset($pages[$page])) {
	return;
}
?>
<div class="cmslightbox cms legalText">
	<section>
		<header>
			<h1><?= $pages[$page] ?></h1>
			<ul class="barmenu">
				<li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<?php $this->render(dirname(__DIR__) . "/auth/$page.tpl.php"); ?>
		</main>
	</section>
</div>
