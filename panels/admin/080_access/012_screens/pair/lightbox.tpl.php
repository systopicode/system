<?php
/** @var \Systopic\System\Panels\Root\Admin\Access\Screens\Pair\Panel $this */

$screen = $this->screen;
if ($screen === null) {
	return;
}
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$name = trim((string) $screen->name);
$scanning = $this->scanning;
?>
<div class="cms cmslightbox deviceBox" data-feedback="parameters">
	<section>
		<header>
			<h1><?= $scanning ? 'Scan device code from screen' : 'Create new auth code' ?></h1>
			<ul class="barmenu">
				<li><a class="ajax" href="<?= $e($this->parent->href) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li>
			</ul>
		</header>
		<main class="deviceBoxMain">
			<?= \message::flush() ?>
			<?php
			$infoScan = $scanning;
			$infoName = $name;
			include dirname(__DIR__, 3) . '/user/authInfo.tpl.php';
			if ($scanning) {
				$scanHost = 'approve';
				$scanAction = $this->href . 'approveDeviceCode';
				$scanLead = 'The screen shows a code on its sign-in page.';
				$scanFields = ['user_id' => (int) $screen->id];
				$scanAuto = true;
				include dirname(__DIR__, 3) . '/user/scanCode.tpl.php';
			} else { ?>
				<div class="cmsBoxActions">
					<button type="button" class="button submit" data-feedback="none" data-on_click="createCode" data-user_id="<?= (int) $screen->id ?>"><i class="cmsIcon cmsIcon-qrcode"></i> Create auth code</button>
				</div>
			<?php } ?>
		</main>
	</section>
</div>
<?php
$issued = $this->issuedCode;
if ($issued !== null) {
	$issuedFor = 'screen';
	include dirname(__DIR__, 2) . '/010_users/edit/code.tpl.php';
}
