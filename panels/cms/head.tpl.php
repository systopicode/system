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
	<title><?php
		$cmsProduct = 'fluxo cms';
		$cmsTitle = defined('PUBLIC_PAGE_NAME') && trim((string) PUBLIC_PAGE_NAME) !== ''
			? trim((string) PUBLIC_PAGE_NAME) . ' // ' . $cmsProduct
			: $cmsProduct;
		echo htmlspecialchars($cmsTitle, ENT_QUOTES, 'UTF-8');
	?></title>

	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ META TAGS +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<?php $faviconVersion = '?v=' . (int) @filemtime(fs::$sysRoot . 'public/img/system/favicons/favicon.ico'); ?>
	<link rel="icon" type="image/svg+xml" href="<?= http::$sysRoot ?>public/img/system/icon.svg<?= $faviconVersion ?>">
	<link rel="apple-touch-icon" href="<?= http::$sysRoot ?>public/img/system/favicons/apple-touch-icon.png<?= $faviconVersion ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= http::$sysRoot ?>public/img/system/favicons/favicon-32x32.png<?= $faviconVersion ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= http::$sysRoot ?>public/img/system/favicons/favicon-16x16.png<?= $faviconVersion ?>">
    <link rel="manifest" href="<?= http::$sysRoot ?>public/img/system/favicons/site.webmanifest<?= $faviconVersion ?>">
    <link rel="mask-icon" href="<?= http::$sysRoot ?>public/img/system/favicons/safari-pinned-tab.svg<?= $faviconVersion ?>" color="#243e48">
    <link rel="shortcut icon" href="<?= http::$sysRoot ?>public/img/system/favicons/favicon.ico<?= $faviconVersion ?>">
    <meta name="apple-mobile-web-app-title" content="fluxo cms">
    <meta name="application-name" content="fluxo cms">
    <meta name="description" content="fluxo cms">
    <meta name="author" content="systopic GmbH"/>
    <meta name="copyright" content="systopic GmbH"/>
    <meta name="abstract" content="fluxo cms"/>
    <meta name="msapplication-config" content="<?= http::$sysRoot ?>public/img/system/favicons/browserconfig.xml">
	<meta property='og:image' content='<?= http::$sysRoot ?>public/img/system/share_default.jpg' />
	<meta itemprop='image' content='<?= http::$sysRoot ?>public/img/system/share_default.jpg' />

	<meta name="theme-color" content="#243e48">
	<meta name="msapplication-TileColor" content="#243e48">

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

	// Chrome revalidates <link> styles on a normal reload and skips @import.
	// On DEV the import URL changes every request, so a reload picks up CSS.
	$cmsCss = static function (string $path, string $layer, bool $recursive = false): string {
		$html = fsLoader::includeCSSFilesInLayer($path, $layer, $recursive);
		if ($html !== '' && defined('STAGE') && str_starts_with((string) STAGE, 'DEV')) {
			$html = str_replace('?t=', '?r=' . time() . '&t=', $html);
		}
		return $html;
	};

	echo '<!-- +-+-+-+-+-+-+-+-+-+ CMS CSS +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->';
	echo $cmsCss(fs::$sysRoot . 'public/css/', 'cms');
	echo $cmsCss(fs::$sysRoot . 'public/css/system/', 'cms');
	echo $cmsCss(fs::$sysRoot . 'public/css/system/fixes/', 'cms', TRUE);
	?>
	<!-- +-+-+-+-+-+-+-+-+-+ MODULE CSS -+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<?php
	$cssModulePath = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [];
	foreach (\Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [] as $moduleName) {
		$modulePathString = implode('/', $cssModulePath);
		echo $cmsCss(fs::$sysRoot . "panels/$modulePathString/", 'cms');
		foreach (\Systopic\System\Sys\Packages::panels() as $package) {   // systopic/cms …
			echo $cmsCss(fs::toInternal($package['dir']) . "$modulePathString/", 'cms');
		}
		echo $cmsCss(fs::$projectRoot . "panels/$modulePathString/", 'cms');
		echo $cmsCss(fs::$siteRoot . "panels/$modulePathString/", 'cms');
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
	echo $cmsCss(fs::$siteRoot . 'css/cms_overrides.css', 'project');
	echo fsLoader::includeCSSFilesScoped(
		fs::$siteRoot . 'css/',
		'article.cmsContent',
		'preview',
		['cms_overrides.css', 'fonts.css', 'animate.css', 'x_responsive.css'],
	);
	$responsiveFile = fs::$siteRoot . 'css/x_responsive.css';
	if (fs::isFile($responsiveFile)) {
		$responsive = preg_replace('/@media\b/', '@container cms-preview', (string) file_get_contents($responsiveFile));
		echo "<style>\n@layer preview {\n@scope (article.cmsContent) {\n$responsive\n}\n}\n</style>\n";
	}
	echo '<!-- +-+-+-+-+-+-+-+-+-+ PROJECT UI OVERRIDES +-+-+-+-+-+-+-+-+-+-+-->';
	echo $cmsCss(fs::$siteRoot . 'overrides/css/', 'project');
	// The site reset sits in the preview layer and zeros every border. These
	// frames stay unlayered so they win inside the page preview.
	?>
	<style>
		article.cmsContent [contenteditable='true'],
		article.cmsContent div.plaintext{
			cursor: text;
			border: 1px dashed rgba(151, 215, 249, .22);
			border-radius: 4px;
			min-height: 20px;
			position: relative;
			overflow: visible;
		}
		article.cmsContent [contenteditable='true']:hover,
		article.cmsContent div.plaintext:hover{
			border-style: solid;
			border-color: rgba(151, 215, 249, .75);
			background: rgba(151, 215, 249, .05);
		}
		article.cmsContent [contenteditable='true']:after,
		article.cmsContent div.plaintext:after{
			content: "EDITOR";
			position: absolute;
			top: 4px;
			right: 4px;
			z-index: 5;
			padding: 2px 6px;
			border-radius: 4px;
			background: var(--cmsChip);
			color: var(--cmsChipText);
			font-family: var(--cmsFont);
			font-size: 10px;
			font-weight: 600;
			line-height: 14px;
			letter-spacing: 0.02em;
			text-transform: uppercase;
			pointer-events: none;
			opacity: 0;
		}
		article.cmsContent [data-editorlabel]:not([data-editorlabel='']):after{
			content: attr(data-editorlabel);
		}
		article.cmsContent [contenteditable='true']:hover:after,
		article.cmsContent [contenteditable='true']:focus:after,
		article.cmsContent div.plaintext:hover:after{
			opacity: 1;
		}
		article.cmsContent div.editor.empty{
			background: rgba(0, 0, 0, .15);
		}
		/* Lightbox-Links bleiben klickbar, auch wenn der Seiteninhalt sie sperrt. */
		.cmslightbox a,
		.cmslightbox a *{
			pointer-events: auto !important;
			cursor: pointer !important;
		}
	</style>
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
