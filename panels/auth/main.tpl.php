<?php
/** @var \Systopic\System\Panels\Root\Auth\Panel $this */
$wordPath = \Systopic\System\Settings\ThemeAssets::path('wordLight');
$wordOwn = $wordPath !== null && str_starts_with(\fs::toNative($wordPath), \fs::toNative(rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/'));
$logoPath = \Systopic\System\Settings\ThemeAssets::path('logo');
$logoOwn = $logoPath !== null && str_starts_with(\fs::toNative($logoPath), \fs::toNative(rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/'));
$logoSvg = $logoOwn && str_ends_with(strtolower($logoPath), '.svg') ? (string) fs::file_get_contents($logoPath) : '';
$frontendLogo = \Systopic\System\Auth\Branding::href('img/logo.svg') ?? \Systopic\System\Auth\Branding::href('img/logo.png');
?>
<div class="authCard cms">
	<?php if ($wordOwn) { ?>
        <a class="logo override" href="<?= $this->href ?>"><img src="<?= htmlspecialchars(\Systopic\System\Settings\ThemeAssets::href('wordLight'), ENT_QUOTES) ?>" alt=""></a>
    <?php } elseif ($frontendLogo !== null) { ?>
        <a class="logo override" href="<?= $this->href ?>"><img src="<?= htmlspecialchars($frontendLogo, ENT_QUOTES) ?>" alt=""></a>
	<?php } elseif ($logoSvg !== '') { ?>
		<a class="logo override" href="<?= $this->href ?>"><?= $logoSvg ?></a>
	<?php } else { ?>
		<a class="logo<?= $logoOwn ? ' override' : '' ?>" href="<?= $this->href ?>">
			<img src="<?= htmlspecialchars(\Systopic\System\Settings\ThemeAssets::href($logoOwn ? 'logo' : 'wordLight'), ENT_QUOTES) ?>" alt="fluxo">
		</a>
	<?php } ?>
	<?php $this->withView($this->form ?: 'login'); ?>
	<?php if (!in_array((string) ($this->form ?? 'login'), ['login', 'signedIn', 'passkeySetup', 'loggedOut', 'resetPassword', 'resetCode', 'activationCode', 'setPassword', 'register'], true)) { ?>
		<p class="authFoot"><a class="button transparent authSwitch ajax" href="<?= htmlspecialchars((string) $this->href, ENT_QUOTES) ?>?method=choose"><i class="cmsIcon cmsIcon-arrow-left"></i> Use another method</a></p>
	<?php } ?>
</div>
<?php include('footer.tpl.php'); ?>
