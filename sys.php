<?php

// OS-Konstante fuer plattformuebergreifende Pfad-Behandlung
define('OS', PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux');

class sys
{

	static function init($rootToSyspath, $server = NULL)
	{
		include __DIR__ . '/lib/class/fs/fs.php';
		include __DIR__ . '/lib/traits/trait_dynamicProperties.php';
		include __DIR__ . '/lib/class/fs/dir/fsDir.php';
		fs::setRootAndSitePath($rootToSyspath);
		include __DIR__ . '/lib/class/fs/autoloader/fsAutoloader.php';
		spl_autoload_register(['fsAutoloader', 'loadClass']);
		include __DIR__ . '/lib/interfaces/client.php';
		http::setRoot($server ?? $_SERVER);

		// The site is picked in loader.php, once the database is connected:
		// the sites live in cms_sites (Pages\Sites\SiteRegistry::boot()).

		// One file, in the project root, rolled out by the tool manager.
		// Nothing to search for, nothing to configure and nothing to fall back
		// to: the file is there and debug exists, or it is not and it does not.
		// Whether the tool then actually does anything (DEBUG === FALSE
		// installs the cloak), and whether newer sources next to it should run
		// instead, are both decided inside it - see tools/debug/builder/stub.php.
		//
		// The paths below are only meaningful once something was included, so
		// they sit inside the branch: no debug, no roots.
		$debugFile = (string) fs::$projectRoot . 'debug.php';
		if (fs::isFile($debugFile)) {
			fs::includeFile($debugFile);
			p()->addPath(__DIR__, 'sys');
			// panels/, lib/ etc. of the project live outside the webroot,
			// so without this root their callers stay unshortened and unlinked.
			if (fs::$projectRoot) {
				p()->addPath((string) fs::$projectRoot, 'project');
			}
		}
		// p('hallo sys:init');
		// client::addJsSettings(sys::getCoreJsSettings()); // (modules not built yet)
	}

	static function getCoreJsSettings()
	{
		// d('core');
		// settings required in js core before cilientData is loaded
		return [
			'stage' => STAGE,
			'instance' => defined('INSTANCE') ? INSTANCE : null,
			'site' => \Systopic\System\Config\Config::current()?->site()?->name,
			'inCms' => \Systopic\System\Panels\Tree\PanelTree::getInstance()?->inCms() ?? false,
			'inApp' => \Systopic\System\Panels\Tree\PanelTree::getInstance()?->inApp() ?? false,
			...http::getCoreJsSettings(),
			...fs::getCoreJsSettings(),
			'PUBLIC_VERSIONAPPENDIX' => PUBLIC_VERSIONAPPENDIX,
			'debug' => DEBUG(),

		];
	}
}