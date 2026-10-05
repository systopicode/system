<!--+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+ SYSTEM JAVASCRIPT  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-->
<script>
	var settings = <?= json_encode(client::$jsSettings) ?>;
</script>

<?php
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/ext/jquery.js');
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/core/helper.js');
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/sys.js');
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/lib.js');
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/loader.js');
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/panelTree.js');
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/panelNode.js');
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/', FALSE);
// core/*.js (domLayer.js above all) — pagesRenderer.js builds its layers from
// sys.core.dom.layer, so the class has to be on the page. Non-recursive:
// core/event, core/lib and core/server are emitted separately below.
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/core/', FALSE);
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/core/event/',);
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/core/lib/',);
echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/core/server/',);

// Load JS for panel classes needed on site pages (replaces legacy moduleBuilder::build).
// Walks the named panels in the tree and calls loadJS() on each so their
// panel.js files end up in the client::flushScripts() output below.
$_pt = \Systopic\System\Panels\Tree\PanelTree::getInstance();
if ($_pt !== null) {
	foreach (['cms', 'pages'] as $_panelName) {
		$_panel = $_pt->findByName($_panelName);
		if ($_panel !== null) {
			$_panel->loadJS();
		}
	}
	unset($_pt, $_panelName, $_panel);
}
echo client::flushScripts();
echo fsLoader::includeScriptFiles(fs::$siteRoot . 'js/');

echo fsLoader::includeCSSFiles(fs::$siteRoot . 'css/');

// Packages add to the head here — the legacy shop (systopic/system-legacy)
// its PayPal script.
hooks::emit('headLoader');
