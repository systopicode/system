<head lang="en">
	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ TITLE -+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ -->
	<?php
	$modulePathString = implode('/', \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getPath() ?? []);
	$titleModulePath = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getPath() ?? [];
	unset($titleModulePath[0]);

	if (defined('CMS_PAGE_TITLE_OVERRIDE')) {
		if (defined('CMS_PAGE_TITLE_SHOW_PATH') && CMS_PAGE_TITLE_SHOW_PATH) {
			if (isset($titleModulePath[2])) {
				$path = $modules[$titleModulePath[1]]['submodules'][$titleModulePath[2]];
			} else {
				$path = isset($titleModulePath[1]) ? $modules[$titleModulePath[1]] : '';
			}
			$CMS_TITLE = str_replace('-<wbr>', '', isset($path['label']) ? $path['label'] : '') . '&nbsp;|&nbsp;' . CMS_PAGE_TITLE_OVERRIDE;
		} else {
			$CMS_TITLE = CMS_PAGE_TITLE_OVERRIDE;
		}
	}
	?>
	<title><?= PUBLIC_PAGE_NAME ?> // fluxo cms</title>

	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ META TAGS +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<link rel="apple-touch-icon" href="<?= http::$sysRoot ?>public/img/system/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= http::$sysRoot ?>public/img/system/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= http::$sysRoot ?>public/img/system/favicons/favicon-16x16.png">
    <link rel="manifest" href="<?= http::$sysRoot ?>public/img/system/favicons/site.webmanifest">
    <link rel="mask-icon" href="<?= http::$sysRoot ?>public/img/system/favicons/safari-pinned-tab.svg" color="#d5bf50">
    <link rel="shortcut icon" href="<?= http::$sysRoot ?>public/img/system/favicons/favicon.ico">
    <meta name="apple-mobile-web-app-title" content="systopic">
    <meta name="application-name" content="systopic">
    <meta name="author" content="systopic GmbH"/>
    <meta name="abstract" content="systopic"/>
    <meta name="msapplication-config" content="<?= http::$sysRoot ?>public/img/system/favicons/browserconfig.xml">
	<meta property='og:image' content='<?= http::$sysRoot ?>public/img/system/share_default.jpg' />
	<meta itemprop='image' content='<?= http::$sysRoot ?>public/img/system/share_default.jpg' />

	<meta name="theme-color" content="#4C6873">
	<meta name="msapplication-TileColor" content="#4C6873">

    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">

	<meta name="robots" content="noindex,follow" />

	<?php
	/*
	 * Three cascade layers, in this order - later wins, whatever the
	 * specificity says:
	 *
	 *   cms      the system ui
	 *   project  what a project overrides about that ui
	 *   preview  the site's own css, inside the page preview only
	 *
	 * That is what lets the site style the preview without fighting
	 * `.cms .layout` for specificity, and without a single !important.
	 * Declared once, before anything that names a layer.
	 */
	echo '<style>@layer cms, project, preview;</style>' . "\n";

	echo '<!-- +-+-+-+-+-+-+-+-+-+ CMS CSS +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->';
	echo fsLoader::includeCSSFilesInLayer(fs::$sysRoot . 'public/css/', 'cms');
	echo fsLoader::includeCSSFilesInLayer(fs::$sysRoot . 'public/css/system/', 'cms');
	echo fsLoader::includeCSSFilesInLayer(fs::$sysRoot . 'public/css/system/fixes/', 'cms', TRUE);
	?>
	<!-- +-+-+-+-+-+-+-+-+-+ MODULE CSS -+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<?php
	$cssModulePath = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [];
	foreach (\Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [] as $moduleName) {
		$modulePathString = implode('/', $cssModulePath);
		echo fsLoader::includeCSSFilesInLayer(fs::$sysRoot . "panels/$modulePathString/", 'cms');
		foreach (\Systopic\System\Sys\Packages::panels() as $package) {   // systopic/cms …
			echo fsLoader::includeCSSFilesInLayer(fs::toInternal($package['dir']) . "$modulePathString/", 'cms');
		}
		echo fsLoader::includeCSSFilesInLayer(fs::$projectRoot . "panels/$modulePathString/", 'cms');
		echo fsLoader::includeCSSFilesInLayer(fs::$siteRoot . "panels/$modulePathString/", 'cms');
		array_pop($cssModulePath);
	}
	?>
	<!-- +-+-+-+-+-+-+-+-+-+ SITE CSS IN THE PAGE PREVIEW +-+-+-+-+-+-+-+-+-->
	<?php
	/*
	 * The preview renders the real page inside <article class="cmsContent">,
	 * so the site's css has to reach in there - and only there.
	 *
	 * Two halves, because three at-rules cannot live inside @scope:
	 * @font-face, :root and @keyframes are document-wide by nature. The
	 * project names those in css/cms_overrides.css (today: an @import of
	 * fonts.css); everything else is scoped.
	 *
	 * Not scoped either: x_responsive.css. Its media queries would measure
	 * the BROWSER window, not the preview pane, so the preview would claim
	 * mobile on a narrow window and desktop on a wide one - neither being
	 * what the visitor sees. Responsive css stays on the site.
	 */
	echo fsLoader::includeCSSFilesInLayer(fs::$siteRoot . 'css/cms_overrides.css', 'project');
	echo fsLoader::includeCSSFilesScoped(
		fs::$siteRoot . 'css/',
		'article.cmsContent',
		'preview',
		['cms_overrides.css', 'fonts.css', 'animate.css', 'x_responsive.css'],
	);
	echo '<!-- +-+-+-+-+-+-+-+-+-+ PROJECT UI OVERRIDES +-+-+-+-+-+-+-+-+-+-+-->';
	echo fsLoader::includeCSSFilesInLayer(fs::$siteRoot . 'overrides/css/', 'project');
	?>
	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ JAVASCRIPT  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<!-- +-+-+-+-+-+-+-+-+-+ SETTINGS USED IN JS  +-+-+-+-+-+-+-+-+-+-+-+-+-->
	<script>
		var contextMenusConfig = <?= '{}' //json_encode($contextMenus)                                    ?>;
		var settings = <?= json_encode(client::$jsSettings) ?>;
		var imageFormats = <?= json_encode(mediaFormats::get()) ?>;
	</script>

	<!-- +-+-+-+-+-+-+-+-+-+ CMS JS -+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<?php
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/ext/jquery.js');

	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/sys.js');
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/lib.js');
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/loader.js');
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/panelTree.js');
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/panelNode.js');
	echo client::flushScripts();

	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/core/');
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/cms/');
	if (!defined('JSOFF')) {
		?>
		<script>
			sys.lib.updateClientData(<?= json_encode(clientInterfaces::clientData_export()) ?>);
		</script>
		<?php
	}
	?>
	<!-- +-+-+-+-+-+-+-+-+-+ MODULE JS -+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<?php
	$cssModulePath = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [];
	foreach (\Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [] as $moduleName) {
		$modulePathString = implode('/', $cssModulePath);
		echo fsLoader::includeScriptFiles(fs::$sysRoot . "panels/$modulePathString/");
		foreach (\Systopic\System\Sys\Packages::panels() as $package) {   // systopic/cms …
			echo fsLoader::includeScriptFiles(fs::toInternal($package['dir']) . "$modulePathString/");
		}
		echo fsLoader::includeScriptFiles(fs::$projectRoot . "panels/$modulePathString/");
		echo fsLoader::includeScriptFiles(fs::$siteRoot . "panels/$modulePathString/");
		array_pop($cssModulePath);
	}
	?>
	<?php
	echo '<!-- +-+-+-+-+-+-+-+-+-+ PROJECT UI OVERRIDES +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ -->';
	echo fsLoader::includeScriptFiles(fs::$siteRoot . 'overrides/js/'); // overrides 
	?>
	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
</head>
