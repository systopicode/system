<?php
/**
 * A code that was just made, shown once.
 *
 * @var array{code: string, expires: int} $issued
 * @var string $issuedFor person | screen
 */
	$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
	$url = \Systopic\System\Auth\CodeMail::link((string) $issued['code']);
	$svg = class_exists('qr') ? \qr::svgMarkup(\qr::payload('url', ['url' => $url]), 4, 'M', 'qr-code') : '';
	$screenCode = $issuedFor === 'screen';
	$heading = $screenCode ? 'Auth code' : 'Sign-in code';
	$lead = $screenCode
		? 'Valid for 10 minutes. On the new device, enter this code or open the link, then choose Sign in. That device becomes this screen.'
		: 'Valid for 10 minutes. On the new device, enter this code or open the link, then choose Sign in. The account becomes active when the code is used.';
?>
<div class="cms cmslightbox qrZoom cmsNestedDialog" data-mobile-dialog="static" data-size="2" data-code-until="<?= (int) ($issued['expires'] ?? 0) ?>" data-code-value="<?= $e($issued['code']) ?>" hidden>
	<section class="is-expanded">
		<header>
			<h1><?= $e($heading) ?></h1>
			<ul class="barmenu">
				<li><button type="button" data-lightbox-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
		<main>
			<div data-code-live>
				<p class="cmsBoxLead"><?= $e($lead) ?></p>
				<p class="tagsHint">Anyone who can see this can sign in as this <?= $screenCode ? 'screen' : 'person' ?>. Close it when you are done.</p>
                <?php $copyCode = $issued['code']; include dirname(__DIR__, 5) . '/src/Auth/codeCopy.tpl.php'; ?>
				<?php if ($svg !== '') { ?>
					<div class="qrZoomStage">
						<div class="fileFrame">
							<div class="frameView"><?= $svg ?></div>
						</div>
					</div>
				<?php } ?>
			</div>
			<p class="cmsBoxLead" data-code-expired hidden>This code has expired. Close this and create a new one.</p>
            <div class="cmsBoxActions">
                <button type="button" class="button submit" data-on_click="createCode" data-user_id="<?= (int) (isset($screen) ? $screen->id : $this->user->id) ?>"><i class="cmsIcon cmsIcon-qrcode"></i>Create another code</button>
            </div>
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
