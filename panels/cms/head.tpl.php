<head lang="en">
	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ TITLE -+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ -->
	<title><?= htmlspecialchars(\Systopic\System\Sys\WebApp::documentTitle(), ENT_QUOTES, 'UTF-8') ?></title>
	<script>
	if (matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
		document.title = <?= json_encode(\Systopic\System\Sys\WebApp::name(), JSON_UNESCAPED_UNICODE) ?>;
	}
	</script>

	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ META TAGS +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<?php
	$asset = static fn(string $file): string => htmlspecialchars(\Systopic\System\Sys\Packages::panelAssetHref($file), ENT_QUOTES);
	$brandColor = '#' . \Systopic\System\Sys\Theme::color('cmsMainColor');
	$onAuth = isset($headHost) && $headHost->name === 'auth';
	// A QR sign-in sets this for one landing. The page does not offer to
	// install the CMS; the cookie is then gone, so a later visit still can.
	$freshSignIn = (($_COOKIE['sys_plain'] ?? '') === '1');
	$skipApp = $onAuth || $freshSignIn;
	if (!$onAuth && $freshSignIn && !headers_sent()) {
		$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
		setcookie('sys_plain', '', [
			'expires' => time() - 3600,
			'path' => '/',
			'secure' => $https,
			'httponly' => true,
			'samesite' => 'Lax',
		]);
	}
	// The status bar sits on the petrol header. The page grey is the background
	// behind the address bar, set on html/body, not on this tag.
	$themeColor = $brandColor;
	?>
	<?php
	$overrideRoot = \fs::toNative(rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/');
	$iconSvg = \Systopic\System\Sys\Packages::panelAsset('img/icon.svg');
	$iconPng = \Systopic\System\Sys\Packages::panelAsset('img/favicons/favicon-32x32.png');
	$svgOwn = $iconSvg !== null && str_starts_with(\fs::toNative($iconSvg), $overrideRoot);
	$pngOwn = $iconPng !== null && str_starts_with(\fs::toNative($iconPng), $overrideRoot);
	if ($svgOwn || !$pngOwn) { ?>
	<link rel="icon" type="image/svg+xml" href="<?= $asset('img/icon.svg') ?>">
	<?php } ?>
	<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(\http::$root . 'cms/icon-180.png', ENT_QUOTES) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $asset('img/favicons/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $asset('img/favicons/favicon-16x16.png') ?>">
    <?php if (!$skipApp) { ?>
    <link rel="manifest" href="<?= htmlspecialchars(\Systopic\System\Sys\WebApp::href(), ENT_QUOTES) ?>">
    <?php } ?>
    <link rel="mask-icon" href="<?= $asset('img/favicons/safari-pinned-tab.svg') ?>" color="<?= $brandColor ?>">
    <link rel="shortcut icon" href="<?= $asset('img/favicons/favicon.ico') ?>">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(\Systopic\System\Sys\WebApp::shortName(), ENT_QUOTES) ?>">
    <?php if (!$skipApp) { ?>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <?php } ?>
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="application-name" content="fluxo cms">
    <meta name="description" content="fluxo cms">
    <meta name="author" content="systopic GmbH"/>
    <meta name="copyright" content="systopic GmbH"/>
    <meta name="abstract" content="fluxo cms"/>
    <meta name="msapplication-config" content="<?= $asset('img/favicons/browserconfig.xml') ?>">
	<?php $shareImage = http::$hostUrl . htmlspecialchars(\Systopic\System\Settings\ThemeAssets::href('share'), ENT_QUOTES); ?>
	<meta property='og:image' content='<?= $shareImage ?>' />
	<meta itemprop='image' content='<?= $shareImage ?>' />

	<?php if (!$onAuth) { ?>
	<meta name="theme-color" content="<?= $themeColor ?>">
	<?php } ?>
	<meta name="color-scheme" content="<?= $onAuth ? 'dark' : 'light dark' ?>">
	<meta name="msapplication-TileColor" content="<?= $brandColor ?>">

    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,interactive-widget=<?= $onAuth ? 'resizes-visual' : 'resizes-content' ?>">

	<meta name="robots" content="noindex,follow" />

	<script>
	try {
		document.cookie = '<?= \Systopic\System\Sys\LocalTime::COOKIE ?>=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone) + ';path=/;max-age=31536000;samesite=lax';
	} catch (e) {}
	</script>

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

	// The layer is named on the link itself. An @import in a style tag is
	// skipped when Chrome opens a fresh document, which left sign-in and
	// signed out unstyled. On DEV the URL changes every request, so a
	// reload still picks up CSS.
	$cmsCss = static function (string $path, string $layer, bool $recursive = false): string {
		$html = fsLoader::includeCSSFilesInLayer($path, $layer, $recursive);
		if ($html !== '' && defined('STAGE') && str_starts_with((string) STAGE, 'DEV')) {
			$html = str_replace('?t=', '?r=' . time() . '&amp;t=', $html);
		}
		return $html;
	};

	echo '<!-- +-+-+-+-+-+-+-+-+-+ CMS CSS +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->';
	// the system first, then each package: css/ by name (the number prefixes
	// are the cascade order), then its subfolders
	foreach ([fs::$sysRoot . 'public/', ...\Systopic\System\Sys\Packages::panelAssets()] as $assetDir) {
		$cssDir = fs::toInternal($assetDir) . 'css/';
		echo $cmsCss($cssDir, 'cms');
		foreach (glob(fs::toNative($cssDir) . '*', GLOB_ONLYDIR) ?: [] as $subDir) {
			echo $cmsCss(fs::toInternal($subDir) . '/', 'cms');
		}
	}
	?>
	<!-- +-+-+-+-+-+-+-+-+-+ MODULE CSS -+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
	<?php
	// Marked so a later Ajax navigation can drop the sheets of the module
	// that was just left. Unmarked links are the system UI and stay.
	$moduleCss = static function (string $path) use ($cmsCss): string {
		return preg_replace(
			'/<link rel="stylesheet" href="([^"]+)" layer="cms">/',
			'<link rel="stylesheet" href="$1" layer="cms" data-cms-module="$1">',
			$cmsCss($path, 'cms'),
		) ?? '';
	};
	$cssModulePath = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [];
	foreach (\Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [] as $moduleName) {
		$modulePathString = implode('/', $cssModulePath);
		echo $moduleCss(fs::$sysRoot . "panels/$modulePathString/");
		foreach (\Systopic\System\Sys\Packages::panels() as $package) {   // systopic/cms …
			echo $moduleCss(fs::toInternal($package['dir']) . "$modulePathString/");
		}
		echo $moduleCss(fs::$projectRoot . "panels/$modulePathString/");
		echo $moduleCss(fs::$siteRoot . "panels/$modulePathString/");
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
	 * project names those in css/system_overrides.css (css/cms_overrides.css
	 * while a project has not saved the new name yet); everything else is scoped.
	 *
	 * Not scoped either: x_responsive.css. Its media queries would measure
	 * the BROWSER window, not the preview pane, so the preview would claim
	 * mobile on a narrow window and desktop on a wide one - neither being
	 * what the visitor sees. Responsive css stays on the site.
	 */
	$projectCss = \Systopic\System\Sys\Theme::projectStylesheet();
	if ($projectCss !== null) {
		echo fsLoader::includeCSSFilesScoped($projectCss, ':root', 'project', limit: '.cmsOwn', liftDocument: true);
	}
	echo fsLoader::includeCSSFilesScoped(
		fs::$siteRoot . 'css/',
		'article.cmsContent',
		'preview',
		['system_overrides.css', 'cms_overrides.css', 'pages_overrides.css', 'fonts.css', 'animate.css', 'x_responsive.css'],
		limit: '.cmsOwn',
	);
	$responsiveFile = fs::$siteRoot . 'css/x_responsive.css';
	if (fs::isFile($responsiveFile)) {
		$responsive = preg_replace('/@media\b/', '@container cms-preview', fs::getFileContents($responsiveFile));
		echo "<style>\n@layer preview {\n@scope (article.cmsContent) to (.cmsOwn) {\n$responsive\n}\n}\n</style>\n";
	}
	echo '<!-- +-+-+-+-+-+-+-+-+-+ PROJECT UI OVERRIDES +-+-+-+-+-+-+-+-+-+-+-->';
	// Page CSS is parsed into the preview. System CSS is a plain stylesheet
	// for the CMS only. The public site does not load it. The colour file is
	// linked afterwards, in the project layer, so it beats the cms links.
	\Systopic\System\Settings\ThemeFile::migratePagesFile();
	$pagesCss = \Systopic\System\Settings\ThemeFile::pagesStylesheet();
	if ($pagesCss !== null && \Systopic\System\Settings\ThemeFile::pagesPanelPresent()) {
		echo fsLoader::includeCSSFilesScoped($pagesCss, ':root', 'project', limit: '.cmsOwn', liftDocument: true);
	}
	echo fsLoader::includeCSSFilesScoped(
		fs::$siteRoot . 'overrides/css/',
		':root',
		'project',
		['system_overrides.css', 'cms_overrides.css', 'pages_overrides.css', 'zz_system_theme.css', 'zz_cms_theme.css'],
		limit: '.cmsOwn',
		liftDocument: true,
	);
	$systemCss = \Systopic\System\Settings\ThemeFile::systemStylesheet();
	if ($systemCss !== null) {
		echo fsLoader::includeCSSFiles($systemCss);
	}
	// The colour file has to be a link in the project layer. A style tag
	// that repeats "@layer project" does not join the layer named on the
	// cms links, so those links kept the default colours.
	$themeCss = \Systopic\System\Settings\ThemeFile::path();
	if (is_file($themeCss)) {
		echo $cmsCss($themeCss, 'project');
	}
	// The site reset sits in the preview layer and zeros every border. These
	// frames stay unlayered so they win inside the page preview.
	?>
	<style>
		/* where a dropped or pasted file lands once it is uploaded (upload/panel.js) */
		article.cmsContent span.cmsUploading{
			display: inline-block;
			margin: 0 2px;
			padding: 2px 8px;
			border: 1px dashed var(--cmsMainColor);
			border-radius: 4px;
			background-color: var(--cmsBgColor);
			font: 400 12px/1.4 var(--cmsFont);
			opacity: .85;
			animation: cmsUploading 1s ease-in-out infinite alternate;
		}
		@keyframes cmsUploading{
			to{ opacity: .35; }
		}
		article.cmsContent [contenteditable='true'],
		article.cmsContent .plaintext{
			cursor: text;
			border: 1px dashed rgba(151, 215, 249, .22);
			border-radius: 4px;
			min-height: 20px;
			position: relative;
			overflow: visible;
		}
		article.cmsContent [contenteditable='true']:hover:not(:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn))),
		article.cmsContent .plaintext:hover:not(:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn))){
			border-style: solid;
			border-color: rgba(151, 215, 249, .75);
			background: rgba(151, 215, 249, .05);
		}
		article.cmsContent [contenteditable='true']:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn)),
		article.cmsContent .plaintext:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn)){
			border-color: transparent;
			background: none;
		}
		article.cmsContent [contenteditable='true']:after,
		article.cmsContent .plaintext:after{
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
		article.cmsContent [contenteditable='true']:hover:not(:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn))):after,
		article.cmsContent [contenteditable='true']:focus:not(:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn))):after,
		article.cmsContent .plaintext:focus:not(:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn))):after,
		article.cmsContent .plaintext:hover:not(:has(img:is(:hover, .cmsMediaOn), video:is(:hover, .cmsMediaOn))):after{
			opacity: 1;
		}
		article.cmsContent div.editor.empty{
			background: rgba(0, 0, 0, .15);
		}
		article.cmsContent [contenteditable='true'] :is(td, th){
			outline: 1px dashed rgba(151, 215, 249, .35);
			outline-offset: -1px;
			min-width: 2em;
		}
		article.cmsContent :is(img, video),
		article.cmsContent [contenteditable='true'] :is(img, video, iframe){
			cursor: default;
			-webkit-touch-callout: none;
		}
		/* Inside the picture: a cropped tile hides a frame drawn outside it. */
		article.cmsContent :is(img, video):is(:hover, .cmsMediaOn){
			outline: 1px solid rgba(151, 215, 249, .75);
			outline-offset: -1px;
			box-shadow: inset 0 0 0 1px rgba(151, 215, 249, .75), inset 0 0 0 100vmax rgba(151, 215, 249, .05);
		}
		.cmsMediaFormat{
			position: fixed;
			z-index: 20;
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
		// data-pointer: the input used last - a desktop with a touch screen has both
		document.documentElement.dataset.pointer = matchMedia('(hover: none)').matches ? 'touch' : 'mouse';
		addEventListener('pointerdown', function (event) {
			document.documentElement.dataset.pointer = event.pointerType === 'mouse' ? 'mouse' : 'touch';
		}, true);
		var contextMenusConfig = <?= '{}' //json_encode($contextMenus)                                    ?>;
		var settings = <?= json_encode(client::$jsSettings) ?>;
		var editorFormats = <?= json_encode(['image' => mediaFormats::editorImages(), 'video' => mediaFormats::editorVideos()], JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) ?>;
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
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/gui/');
	foreach (\Systopic\System\Sys\Packages::panelAssets() as $assetDir) {
		echo fsLoader::includeScriptFiles(fs::toInternal($assetDir) . 'js/');
	}
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
	<?php
	// Safari 26 ignores theme-color. It tints the status bar from a fixed
	// element's background-color (the shorthand is not read) or, failing that,
	// from the root. A variable here is resolved; a missing color falls back to white.
	?>
	<?php if (!$onAuth) { ?>
	<style id="cmsSafariChrome">html{background-color:<?= $themeColor ?>}body.cms>.cmsMobileHeader{background-color:<?= $themeColor ?>}</style>
	<?php } ?>
	<!-- +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
</head>
