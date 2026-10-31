<div class="cms cmslightbox" data-size="1">
    <section>
		<header>
			<h1>Backup Log</h1>
			<ul class="barmenu">
				<li>
					<a download target="_self" href="<?= http::$siteUrl . 'cms/backup/download?name=backup.log' ?>" title="Download log"><i class="cmsIcon cmsIcon-arrow-down-to-line"></i></a>
				</li>
				<?php if (\Systopic\System\Auth\Session::isSuperuser()) { ?>
				<li>
					<button type="button" data-cms-action data-on_click="userConfirm" data-action="clear" data-confirm="Clear this log?" title="Clear log"><i class="cmsIcon cmsIcon-broom"></i></button>
				</li>
				<?php } ?>
				<li>
					<a class="ajax" href="../" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a>
				</li>
			</ul>
		</header>
		<main>
			<section class="widget is-list"><?php include __DIR__ . '/text.inc.php'; ?></section>
		</main>
    </section>
</div>

