<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$logoFile = fs::$siteRoot . 'overrides/img/logo.svg';
$logo = fs::isFile($logoFile) ? fs::file_get_contents($logoFile) : '';
?>
<div class="authCard cms">
	<?php if ($logo !== null && $logo !== '') { ?>
		<a class="logo override" href="<?= $this->href ?>"><?= $logo ?></a>
	<?php } else { ?>
		<a class="logo" href="<?= $this->href ?>">
			<img src="<?= http::$sysRoot ?>public/img/system/logo-horizontal-light.png" alt="fluxo">
		</a>
	<?php } ?>
	<?php $this->withView($this->form ?: 'login'); ?>
</div>
<?php include('footer.tpl.php'); ?>
