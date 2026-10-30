<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$logoPath = \Systopic\System\Settings\ThemeAssets::path('logo');
$logoOwn = $logoPath !== null && str_starts_with(\fs::toNative($logoPath), \fs::toNative(rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/'));
$logoSvg = $logoOwn && str_ends_with(strtolower($logoPath), '.svg') ? (string) fs::file_get_contents($logoPath) : '';
?>
<div class="authCard cms">
	<?php if ($logoSvg !== '') { ?>
		<a class="logo override" href="<?= $this->href ?>"><?= $logoSvg ?></a>
	<?php } else { ?>
		<a class="logo<?= $logoOwn ? ' override' : '' ?>" href="<?= $this->href ?>">
			<img src="<?= htmlspecialchars(\Systopic\System\Settings\ThemeAssets::href($logoOwn ? 'logo' : 'wordLight'), ENT_QUOTES) ?>" alt="fluxo">
		</a>
	<?php } ?>
	<?php $this->withView($this->form ?: 'login'); ?>
	<?php if (!in_array((string) ($this->form ?? 'login'), ['login', 'signedIn', 'passkeySetup', 'loggedOut', 'identify'], true)) { ?>
		<p class="authFoot"><a class="authBack ajax" href="<?= htmlspecialchars((string) $this->href, ENT_QUOTES) ?>"><i class="cmsIcon cmsIcon-arrow-left"></i> Back to sign in</a></p>
	<?php } ?>
</div>
<?php include('footer.tpl.php'); ?>
