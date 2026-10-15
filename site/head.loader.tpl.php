<?php
// the system's scripts - a site with its own assets includes only this part
fs::includeFile(__DIR__ . '/head.system.tpl.php');

echo fsLoader::includeScriptFiles(fs::$siteRoot . 'js/');

echo fsLoader::includeCSSFiles(fs::$siteRoot . 'css/');

// Packages add to the head here — the legacy shop (systopic/system-legacy)
// its PayPal script.
hooks::emit('headLoader');
