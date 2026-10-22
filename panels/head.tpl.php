<?php
/**
 * The head of the system's own root panel — what a project without panels
 * of its own shows (body.tpl.php: hello world). A project with a
 * panels/head.tpl.php of its own never gets here.
 *
 * Slices render the body on the client, so the head carries the core
 * scripts and the client data, as a project head does.
 */
?>
<head lang="en">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?= htmlspecialchars(defined('PROJECT_NAMESPACE') ? PROJECT_NAMESPACE : 'systopic') ?></title>
	<?php
	// jquery, the cms core (helper / event / lib / server), the project's js/ and css/
	include fs::toNative((string) fs::$sysRoot . 'site/head.loader.tpl.php');
	// slice hydration needs the rest of core (domLayer.js) and the layer tree
	echo fsLoader::includeScriptFiles(fs::$sysRoot . 'public/js/core/');
	if (!defined('JSOFF')) {
		$clientData = json_encode(clientInterfaces::clientData_export());
		if (json_last_error() === JSON_ERROR_NONE) {
			echo '<script>sys.lib.updateClientData(' . $clientData . ');</script>';
		}
	}
	?>
</head>
