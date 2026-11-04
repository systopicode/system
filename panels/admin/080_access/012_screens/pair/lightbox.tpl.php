<?php
/** @var \Systopic\System\Panels\Root\Admin\Access\Screens\Pair\Panel $this */
use Systopic\System\Auth\Session;

$screen = $this->screen;
if ($screen === null) {
	return;
}
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$name = trim((string) ($screen->fullname ?: $screen->name));
$actor = trim((string) Session::current()->name);
?>
<div class="cms cmslightbox userEdit" data-size="2">
	<section>
		<header>
			<h1>Pair <?= $e($name) ?></h1>
			<ul class="barmenu">
				<li><a class="ajax" href="<?= $e($this->parent->href) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<?= \message::flush() ?>
			<p class="cmsBoxLead">You, <?= $e($actor) ?>, are signing a device in as <strong><?= $e($name) ?></strong>.</p>
			<p class="tagsHint">This choice is written to the sign-in log.</p>
			<section class="cmsBox userAccess">
				<header><div class="cmsBoxTitles"><h2>Code from the screen</h2><p class="cmsBoxLead">The screen shows a code on its sign-in page. Enter it here.</p></div></header>
				<div class="cmsBoxBody">
					<label class="field"><span>Device code</span><input type="text" name="value" data-on_change="approveDeviceCode" data-user_id="<?= (int) $screen->id ?>" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="12"></label>
				</div>
			</section>
			<section class="cmsBox userAccess">
				<header><div class="cmsBoxTitles"><h2>Code for the screen</h2><p class="cmsBoxLead">Valid for 10 minutes. Enter it on the screen.</p></div></header>
				<div class="cmsBoxActions">
					<button type="button" class="button submit" data-feedback="none" data-on_click="createCode" data-user_id="<?= (int) $screen->id ?>"><i class="cmsIcon cmsIcon-qrcode"></i> Create code</button>
				</div>
			</section>
		</main>
	</section>
</div>
<?php
$issued = $this->issuedCode;
if ($issued !== null) {
	$issuedFor = 'screen';
	include dirname(__DIR__, 2) . '/010_users/edit/code.tpl.php';
}
