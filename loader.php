<?php

/* * **************************** CHECK AND DEFINE CONSTANTS ************************** */
defined('DEBUG') || define('DEBUG', null);
defined('DEBUGRENDER') || define('DEBUGRENDER', null);
defined('DEBUGTIME') || define('DEBUGTIME', null);
defined('SKIP_COMPOSER') || define('SKIP_COMPOSER', false);
defined('STAGE') || define('STAGE', 'DEV');
defined('PUBLIC_VERSIONAPPENDIX') || define('PUBLIC_VERSIONAPPENDIX', '');
defined('DEFAULT_TABLE_PREFIX') || define('DEFAULT_TABLE_PREFIX', 'cms');
defined('DB_NAME') || define('DB_NAME', '');
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('PUBLIC_ACCESS_ROUTES') || define('PUBLIC_ACCESS_ROUTES', []);

/* * **************************** EG SHOW VERSION ************************** */
if (is_file('lib/setup/system.funcs.php')) {
	include 'lib/setup/system.funcs.php';
	scripttime('system funcs ready')->clear();
}

/* * **************************** LOAD SITE LIB (IF PRESENT) ************************** */
class_exists(\Systopic\System\Sys\Hooks\Hooks::class); // class_alias → hooks
if (fs::isFile((string) fs::$projectRoot . 'lib/funcs.lib.php')) {
	fs::includeFile((string) fs::$projectRoot . 'lib/funcs.lib.php');
}
/* * **************************** INCLUDES BEFORE DB CONNECT ************************** */

// Plain foreach, NOT array_map(fn($f) => include $f, …): an include inside an
// arrow function runs in that function's scope, so every variable the included
// file defines at "global" scope becomes a local and is thrown away. That
// silently dropped settings.php's $media_groups and $image_formats, and
// medias::__get() then hit an unknown group and killed the request via d().
foreach ([
	'lib/helper.funcs.php',
	'lib/cms.funcs.php',
	'lib/config/constants.config.php',
	fs::toNative((string) fs::$projectRoot . 'settings.php'),
	'lib/config/server.config.php'
] as $__configFile) {
	if (is_file($__configFile)) {
		include $__configFile;
	}
}
unset($__configFile);
/* * **************************** CHECK AND DEFINE CONSTANTS (AFTER SETTINGS.PHP) ************************** */

defined('PUBLIC_PAGE_NAME') || define('PUBLIC_PAGE_NAME', 'fluxo cms');
defined('PUBLIC_PAGE_TITLE') || define('PUBLIC_PAGE_TITLE', 'fluxo cms');
defined('PUBLIC_PAGE_DESCRIPTION') || define('PUBLIC_PAGE_DESCRIPTION', 'fluxo cms');
defined('PUBLIC_PAGE_KEYWORDS') || define('PUBLIC_PAGE_KEYWORDS', 'fluxo cms');
defined('PUBLIC_PAGE_AUTHOR') || define('PUBLIC_PAGE_AUTHOR', 'fluxo cms');
defined('PUBLIC_PAGE_COPYRIGHT') || define('PUBLIC_PAGE_COPYRIGHT', 'fluxo cms');
scripttime('post includes pre DB');

/* * **************************** DB CONNECT ************************** */
if (DB_NAME !== '') {
	try {
		// From the instance config, or the DB_* constants of a project without
		// config/. The legacy `db` borrows this connection on first use.
		$__db = \Systopic\System\Config\Config::current()?->instance->db() ?? [];
		if (($__db['name'] ?? '') === '') {
			$__db = ['name' => DB_NAME, 'host' => DB_HOST, 'user' => DB_USER, 'pass' => DB_PASS];
		}
		\Systopic\Db\Connection::open($__db);
		unset($__db);
	} catch (Exception $exc) {
		p('unable to connect to database - entering system recovery mode');
		p($exc);
		if ($exc->getMessage() === 'could not find driver') {
			d("PDO misses MySQL Driver - plaese run: sudo apt install php-mysql");
		}
		\Systopic\System\Auth\Session::recover();
	}
} else {
	// d('no database name set');
	// e.g syscoder app runs without database
}
/* * **************************** SITE ************************** */
// Packages that need the database before anything else hook in here —
// systopic/cms loads the sites from cms_sites (which one this request is for
// follows from host + root path). Without it, config.json's sites stay.
\Systopic\System\Sys\Hooks\Hooks::emit('afterConnect');

if (fs::isFile((string) fs::$projectRoot . 'lib/funcs.php')) {
	fs::includeFile((string) fs::$projectRoot . 'lib/funcs.php');
}

/* * **************************** INCLUDES AFTER DB CONNECT ************************** */

$includesAfterDB = [
	fs::toNative((string) fs::$projectRoot . 'lib/html.print.funcs.php'),
	fs::toNative((string) fs::$projectRoot . 'settings.after.php'),
];
foreach ($includesAfterDB as $include) {
	if (is_file($include)) {
		include $include;
	}
}
scripttime('post includes post DB connect');

/* * **************************** ASYNC COMMAND LINE CALL ************************** */

// Skip this legacy async path for new-style CLI test calls (@file / STDIN).
// Those are identified by $argv[1] starting with '@' or being '-'.
$_cliIsNewStyle = php_sapi_name() === 'cli'
	&& isset($argv[1])
	&& (str_starts_with($argv[1], '@') || $argv[1] === '-');

if (php_sapi_name() === "cli" && !$_cliIsNewStyle && (!defined('OUTPUT_FORMAT') || OUTPUT_FORMAT === 'html')) {
	if (is_callable($argv[2])) {
		$aParams = array();
		$aIndex = 3;
		while (isset($argv[$aIndex])) {
			$aParams[] = $argv[$aIndex];
			$aIndex++;
		}
		call_user_func_array($argv[2], $aParams);
	}
	if (DEBUG()) {
		$logDir = (string) fs::$siteRoot . 'var/log/';
		if (!fs::isDir($logDir)) {
			fs::fsMkdir($logDir, 0777, true);
		}
	}
	exit;
}

/* * **************************** LOAD site.php | app.php | media.php | css.php ************************** */
scripttime('lib & db ready');
$user = \Systopic\System\Auth\Session::current();   // $user: the global legacy code reads
scripttime('user ready');

logroute('fs::debugInfo()', fs::debugInfo());
logroute('http::debugInfo()', http::debugInfo());
logroute('app::request()', app::request()->debugInfo());
logroute('app::referrer()', app::referrer()->debugInfo());
scripttime('path ready');

// A diff url names two states: '<reference>~<target>'. It is meant to be
// fetched, not navigated to — arriving as an ordinary request (pasted,
// bookmarked, followed by a crawler) there is nothing to diff against, and
// rendering would put a json body in the address bar. Send it to the target
// it names, which also makes the url safe to paste while debugging.
if (http::$referenceUrl !== null && !http::ajax()) {
	logroute('dispatch: diff url without ajax — redirect to target', http::$requestUrl);
	http::redirect(http::$requestUrl);
}

// Requests a frontend package answers alone (systopic/cms: sitemap.xml).
foreach (\Systopic\System\Sys\Packages::frontends() as $_frontend) {
	$_early = $_frontend->early();
	if ($_early !== null) {
		logroute('include ' . basename($_early));
		include $_early;
		exit;
	}
}
unset($_frontend, $_early);
if (count(app::request()->modulePath) > 0) {
	switch (app::request()->modulePath[0]) {
		case 'css_parsed':
			logroute('include css.php');
			include 'css.php';
			exit;
		case 'var':
			logroute('include media.php');
			include 'media.php';
			exit;
	}
}
/* ---- PanelSystem bootstrap ---- */
require __DIR__ . '/src/bootstrap.php';

$sysPanels     = fs::$sysRoot     ? fs::$sysRoot->getFolder('panels')     : null;
$projectPanels = fs::$projectRoot ? fs::$projectRoot->getFolder('panels') : null;

// Register the panels-roots in resolution order (sys first, project last).
// FolderResolver merges them with last-wins on name collisions, so a project
// can add new top-level panels (e.g. cms/events) and override system panels
// by placing a folder of the same logical name. The cms-mount emerges
// automatically from the cms/ folder existing inside panels/.
//
// PROJECT_NAMESPACE: define this constant in the project's index.php to set
// the root PHP namespace of the project (e.g. define('PROJECT_NAMESPACE', 'Spaceticker')).
// Panel classes will be addressed as <PROJECT_NAMESPACE>\Panels\Root\<Path>\Panel.
$projectPanelNs = defined('PROJECT_NAMESPACE') ? PROJECT_NAMESPACE . '\Panels' : '';

if ($sysPanels && $sysPanels->exists) {
	$panelTree->addRoot(new \Systopic\System\Panels\PanelRootFolder($sysPanels, 'Systopic\System\Panels'));
}
// Packages with panels (systopic/cms: the page panels in cms/) sit between.
foreach (\Systopic\System\Sys\Packages::panels() as $_pkgPanels) {
	$_pkgDir = \fsDir::get(fs::toInternal($_pkgPanels['dir']));
	if ($_pkgDir->exists) {
		$panelTree->addRoot(new \Systopic\System\Panels\PanelRootFolder($_pkgDir, $_pkgPanels['namespace']));
	}
}
unset($_pkgPanels, $_pkgDir);
if ($projectPanels && $projectPanels->exists) {
	$panelTree->addRoot(new \Systopic\System\Panels\PanelRootFolder($projectPanels, $projectPanelNs));
}

// Register the panel autoloader for module-based namespace resolution.
// Must run after roots are registered (namespaceRoot needed) and before build().
\Systopic\System\Panels\Autoload\PanelAutoloader::registerRoots($panelTree->getRoots());

if (in_array(app::request()->modulePath, PUBLIC_ACCESS_ROUTES, true)) {
	$panelTree->disableAccessControl();
}

$panelTree->build(app::request()->modulePath);

$nav = new \Systopic\System\Panels\Navigator\PanelNavigator($panelTree, $layerTree, $domRenderer);
if ($panelTree->getRoot() !== null) {
	$nav->setActive($panelTree->getRoot());
}

\Systopic\System\Panels\PanelNode::setNavigator($nav);
\Systopic\System\Panels\PanelNode::setTree($panelTree);
\Systopic\System\Panels\Tree\PanelTree::setInstance($panelTree);

// Register panel tree export with the legacy clientData system so that
// html.tpl.php / app.php include the panel tree in sys.lib.updateClientData().
clientInterfaces::$clientDataClasses[] = \Systopic\System\Panels\Tree\PanelTree::class;
\Systopic\System\Auth\ClientData::register();   // users.js + the logged-in user

// Wire the new DomRenderer instance into the legacy domRenderer shim so that
// clientData_export() returns the correct layer data.
domRenderer::setNewInstance($domRenderer);

logroute('panelTree::build() done', [
	'rootPanel'     => $panelTree->getRoot()?->name,
	'selectedPanel' => $panelTree->getSelected()?->name,
	'inApp'         => $panelTree->inApp(),
	'path'          => $panelTree->getPath(),
]);
scripttime('build ready');
client::addJsSettings(sys::getCoreJsSettings());

/* ---- Frontends: boot ----
 * Packages that serve requests besides the panels set up here, after the
 * panel tree (systopic/cms: the page route and its renderer). Included in the
 * global scope — the entry files use what they define.
 */
foreach (\Systopic\System\Sys\Packages::frontends() as $_frontend) {
	$_boot = $_frontend->boot();
	if ($_boot !== null) {
		include $_boot;
	}
}
unset($_frontend, $_boot);

/* ---- dispatch ----
 *
 * A panel matched by the URL PATH wins outright. Everything else is decided by
 * page resolution, and there site.php wins when ambiguous.
 *
 * The panel-first rule matters because a page id can also arrive as ?id=,
 * which is the CMS's own page selector: without it, /cms/pages/?id=20 resolves
 * to page 20 and renders the public website instead of the editor. It cannot
 * be folded into "site wins when ambiguous" either, because that rule exists
 * for the root URL "/", where no panel was selected by the path and both a
 * page and the synthetic app root are candidates.
 *
 * Note this only fires for panels that were actually BUILT — an anonymous
 * visitor on /cms/ fails buildGranted(), no panel is selected, and the request
 * falls through to the inApp() branch below exactly as before (login redirect).
 */
$_panelTree = \Systopic\System\Panels\Tree\PanelTree::getInstance();
$_panelByUrl = $_panelTree !== null
	&& $_panelTree->getSelected() !== null
	&& $_panelTree->getSelected() !== $_panelTree->getRoot();

$_entry = null;
if ($_panelByUrl) {
	$mode = 'app';
	logroute('dispatch: app.php (panel selected by url)', $_panelTree->getPath());
} else {
	// A frontend package (systopic/cms: is there a page for this URL?) wins
	// before the app root, as site.php did when ambiguous.
	foreach (\Systopic\System\Sys\Packages::frontends() as $_frontend) {
		if ($_frontend->claims()) {
			$mode = $_frontend->name();
			$_entry = $_frontend;
			break;
		}
	}
	if (!isset($mode)) {
		if ($_panelTree?->inApp() ?? false) {
			$mode = 'app';
			logroute('dispatch: app.php (no page, module active)');
		} else {
			logroute('dispatch', '(no page, no module)');
		}
	}
}
unset($_panelByUrl, $_frontend);
if (isset($mode)) {
	// sys::getCoreJsSettings() derived inApp from PanelTree::inApp(), which is
	// true whenever *some* panel root ships a body.tpl.php — including the
	// system's own. On a frontend request that would make server.command.js
	// call sys.renderer (panels) instead of the frontend's renderer. The
	// dispatch decision is the authority, so restate it here.
	client::addJsSettings(['inApp' => $mode === 'app']);
	$_entry = $_entry === null ? 'app.php' : $_entry->dispatch();
	logroute('include', "$mode.php");
	\Systopic\System\Sys\Hooks\Hooks::emit('beforeDispatch', $mode);
	include $_entry;
} else {
	logroute('include', 'nothing');
}
