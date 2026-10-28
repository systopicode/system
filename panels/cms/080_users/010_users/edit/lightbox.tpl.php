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
<?php
$issued = $this->issuedCode;
if ($issued !== null) {
	$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
	$url = \Systopic\System\Auth\CodeMail::link((string) $issued['code']) . '&auto=1';
	$svg = class_exists('qr') ? \qr::svgMarkup(\qr::payload('url', ['url' => $url]), 4, 'M', 'qr-code') : '';
	$heading = 'Sign-in code';
	$lead = 'Valid for 10 minutes. On the new device, scan this on the sign-in page or open the link.';
?>
<div class="cms cmslightbox qrZoom" data-size="2" data-code-until="<?= (int) ($issued['expires'] ?? 0) ?>" data-code-value="<?= $e($issued['code']) ?>" hidden>
	<section>
		<header>
			<h1><?= $e($heading) ?></h1>
			<ul class="barmenu">
				<li><button type="button" data-lightbox-close aria-label="Close"><i class="fa-regular fa-xmark"></i></button></li>
			</ul>
		</header>
		<main>
			<div data-code-live>
				<p class="cmsBoxLead"><?= $e($lead) ?></p>
				<p class="tagsHint">Anyone who can see this can sign in as this user. Close it when you are done.</p>
				<div class="qrCodeBar">
					<strong><?= $e($issued['code']) ?></strong>
					<button type="button" class="button" data-copy="<?= $e($issued['code']) ?>"><i class="fa-regular fa-copy"></i> Copy code</button>
				</div>
				<?php if ($svg !== '') { ?>
					<div class="qrZoomStage">
						<div class="fileFrame">
							<div class="frameView"><?= $svg ?></div>
						</div>
					</div>
				<?php } ?>
			</div>
			<p class="cmsBoxLead" data-code-expired hidden>This code has expired. Close this and create a new one.</p>
		</main>
	</section>
</div>
<script>
(function () {
	var box = document.querySelector('.cmslightbox.qrZoom');
	if (!box || box.dataset.ready === '1') {
		return;
	}
	box.dataset.ready = '1';
	box.hidden = false;
	if (document.documentElement.dataset.qrZoom === '1') {
		return;
	}
	document.documentElement.dataset.qrZoom = '1';
	document.addEventListener('click', function (event) {
		var host = document.querySelector('.cmslightbox.qrZoom');
		if (!host) {
			return;
		}
		if (event.target.closest('[data-lightbox-close]')) {
			var own = event.target.closest('.cmslightbox.qrZoom');
			if (!own) {
				return;
			}
			event.preventDefault();
			own.hidden = true;
			var section = own.querySelector(':scope > section');
			if (section) {
				section.style.transform = '';
				section.style.transition = '';
			}
			return;
		}
		if (event.target === host) {
			host.hidden = true;
		}
	});
	document.addEventListener('keydown', function (event) {
		var host = document.querySelector('.cmslightbox.qrZoom');
		if (!host || host.hidden || event.key !== 'Escape') {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		host.hidden = true;
	}, true);
})();
</script>
<?php } ?>
