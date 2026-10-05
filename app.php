<?php

http::sessionStartOnce();
// Packages hook in here before the panels run — systopic/system-legacy writes
// its default rows (id 1) for record::get() once per session.
hooks::emit('beforeApp');

/* * **************************** SET GLOBAL VARIABLES ******************************** */

$renderStyle = 'cms';
$ajax = http::ajax();
$debugajax = $ajax && http::params('debug');
$changeModule = http::params('changeModule');
$showeditableareas = http::params('showeditableareas');
$updatePage = false;
$updateTree = false;
$ip = $_SERVER["REMOTE_ADDR"] ?? null;
$action = app::request()->action;
logroute('app.php', [
	'action'        => $action,
	'selectedRoot'  => $panelTree->getSelectedRoot(),
	'selectedPanel' => $panelTree->getSelected()?->name,
]);

scripttime('modules start');
/* * **************************** LOAD ACTION SWITCHES ************************** */
if (!fs::openDir($panelTree->getSelectedRoot())) {
	logroute('app.php — failed to open selectedRoot', $panelTree->getSelectedRoot());
	return;
}

$openChildLimit = 25; // guard against infinite loops caused by wrong module/path state
while ($openChildLimit-- > 0 && $nav->openChildSelected(false)) { // false: skip template check
	// Per-panel auto-includes (legacy funcs.php and include.php) have been
	// removed. Helpers belong in lib/ classes, request-time data belongs on
	// the panel itself as public properties (set in onLoadThisClass /
	// onLoadSelected). See cms/040_backup for the canonical migration pattern.
}

$module = $nav->getActive(); // deepest opened panel — used by action dispatch below

if (!empty($action)) {
	if (!$module->actionGranted($action)) {
		header('HTTP/1.0 403 Forbidden');
		die('no access to execute actions in this module:(');
	}
	if (isset($module->$action) && $module->$action instanceof \Closure) {
		$module->$action->call($module);
	}
	if (method_exists($module, $action) && is_callable([$module, $action])) {
		$parentClass = get_parent_class($module);
		if ($parentClass && method_exists($parentClass, $action)) {
			// Inherited API methods (e.g. PanelNode::updateView / updateViews) must
			// NOT be invoked as HTTP actions — their signature is not the action
			// contract. Fall through to actions.php instead of aborting the request
			// (legacy `return` skipped runActions and left AJAX without updateDocument).
			logroute('app.php — skip inherited method, fall through to actions.php', [
				'action'      => $action,
				'parentClass' => $parentClass,
			]);
		} else {
			$module->$action();
		}
	}
	$module->runActions();
}

if (is_file('menus.config.php')) {
	include 'menus.config.php';
}

$nav->closeAll();
scripttime('modules ready');

fs::closeDir();

if (!fs::openDir($panelTree->getSelectedRoot())) {
	logroute('app.php — second openDir failed', [
		'selectedRoot' => $panelTree->getSelectedRoot(),
		'cwd'          => fs::cwd(),
	]);
	return;
}

/* * **************************** LOAD TEMPLATE STRUCTURE / SEND AJAX RESPONSE ************************** */
$domRenderer->beginRender(); // activate slices output
$domRenderer->setBodyClasses($panelTree->getBodyClasses());
\Systopic\System\Auth\Session::storeState();

if (defined('OUTPUT_FORMAT') && OUTPUT_FORMAT === 'json') {
	// AJAX + JSON: diff render against the previous request's reference
	// tree (loaded from session). Plain JSON (no XHR header): full
	// initial layers render — store the result as the new reference for
	// subsequent diffs.
	$isDiff = http::ajax();
	$isDiff ? $nav->beginDiff() : $nav->beginLayers();
	// Only openRoot('body') — never openSelected as a fallback. The
	// fallback would emit different layer counts depending on which
	// branch fired (selected has body.tpl vs. doesn't), breaking the
	// stable layer structure that diff rendering relies on.
	// findRenderRoot() inside openRoot() walks selected→…→synthetic
	// root and picks the deepest ancestor that ships body.tpl.php, so
	// a selected panel that has its own body.tpl.php still wins.
	if ($nav->openRoot('body')) {
		$nav->getActive()->render('body.tpl.php');
		$nav->close();
	} else {
		d($nav->debugInfo());
	}
	$isDiff ? $nav->endDiff() : $nav->endLayers();
	// Persist the layer tree so a subsequent AJAX request (same session)
	// can find this request's layers via the panel-address reference map
	// and alias unchanged subtrees. Done for both initial and diff
	// renders — diff renders also become the reference for the next.
	$domRenderer->storeReference();
	ob_clean();
	$jsonResponse = [
		'status'   => 'ok',
		'renderer' => $domRenderer->exportJson(),
		'client'   => clientInterfaces::clientData_export(),
		'commands' => client::$response,
	];
	if (DEBUGRENDER) {
		// box-tree data (Debug\BoxTree) - the same the debug prompts draw
		$jsonResponse['panelTree']  = $panelTree->debug();
		$jsonResponse['domLayers']  = $domRenderer->debug();
	}
	if (class_exists(\systopic\debug\sysdebug::class, false)) {
		\systopic\debug\sysdebug::setAppResponse($jsonResponse);
	}
	return;
}
if (http::ajax()) {
	if (http::$method === 'GET' || http::$method === 'POST' || $panelTree->getUpdateViewsCount()) {
		// Panel-Diff (PanelTree / DomLayer), not pages (site.php + renderPagesDiff.php).
		// Uses the SAME closure-based template walk as the full render below —
		// the navigator switches into 'diff' mode via beginDiff(), confirm()
		// compares each opened layer to the previous request's reference layer
		// (alias / replace / original), and PanelNode::runWith() short-circuits
		// closures of alias layers so unchanged sub-trees don't re-execute.
		scripttime('begin render diff');
		$nav->beginDiff();
		// See JSON branch above: only openRoot to keep the layer
		// structure identical across requests so diff comparisons
		// align positionally.
		if ($nav->openRoot('body')) {
			$nav->getActive()->render('body.tpl.php');
			$nav->close();
		} else {
			d($nav->debugInfo());
		}
		$nav->endDiff();
		$domRenderer->storeReference();
		client::updateDocument();
		$selected = $panelTree->getSelected();
		if ($selected !== null) {
			$selected->onAfterRenderSelected();
		}
	}
	scripttime('begin flush response');
	echo json_encode(client::flushResponse());
} else {
	$renderMode = $panelTree->getSelected()?->getRenderMode() ?? $panelTree->getRoot()?->getRenderMode() ?? 'slices';
	if ($renderMode === 'slices') {
		scripttime('begin render layers');
		$nav->beginLayers();
		// Single-opener — see JSON branch above for rationale.
		if ($nav->openRoot('body')) {
			$nav->getActive()->render('body.tpl.php');
			$nav->close();
		} else {
			d($nav->debugInfo());
		}
		$nav->endLayers();
		ob_clean();
		echo "<!DOCTYPE html><html lang=en>";
		// head.tpl.php conventionally lives next to body.tpl.php in the
		// render-host panel's folder. Try the body host first (head and
		// body should be co-located), then fall back to a separate
		// findRenderRoot('head') walk in case a project ships head.tpl.php
		// at a different ancestor than body.
		$bodyHost = $nav->findRenderRoot('body');
		$headHost = ($bodyHost && \fs::isFile(rtrim($bodyHost->absPath, '/') . '/head.tpl.php'))
			? $bodyHost
			: $nav->findRenderRoot('head');
		if ($headHost && $headHost->absPath !== '') {
			$headFile = rtrim($headHost->absPath, '/') . '/head.tpl.php';
			if (\fs::isFile($headFile)) {
				// absPath is in fs internal form (/V/www/…); include needs
				// the native form (V:\… on Windows) to actually open it.
				//
				// Every other template renders from inside its own folder
				// (PanelNavigator::openRoot → fs::openDir), so a template may
				// include a sibling or a parent relatively — cms/auth/head.tpl.php
				// does exactly that. Included from here without changing
				// directory, that relative path resolves against the project
				// root and the whole <head> is silently lost: no css, no js,
				// no clientData — and the client renders nothing into the
				// body placeholder.
				$headCwd = \getcwd();
				@chdir(\fs::toNative(rtrim($headHost->absPath, '/')));
				include \fs::toNative($headFile);
				if (\getcwd() !== $headCwd) {
					@chdir($headCwd);
				}
			}
		}
		echo "<body><div id=systemDomRendererPlaceholder></div></body>";
		echo "</html>";
		$domRenderer->storeReference();
	} elseif ($renderMode === 'markers') {
		scripttime('begin render document');
		$nav->beginDocument();
		if ($nav->openRoot('body')) {
			$nav->getActive()->render('body.tpl.php');
			$nav->close();
		}
		$nav->endDocument();
		ob_clean();
		echo "<!DOCTYPE html><html lang=en>";
		echo (string) $layerTree->root();
		echo "</html>";
	} elseif ($renderMode === 'html') {
		scripttime('begin render plain html');
		echo "<!DOCTYPE html><html lang=en>";
		if ($nav->openRoot('body')) {
			if (is_file('head.tpl.php')) {
				include 'head.tpl.php';
			}
			$nav->getActive()->render('body.tpl.php');
			$nav->close();
		}
		echo "</html>";
	}
}
logroute('DEBUGRENDER', ['value' => DEBUGRENDER, 'rootPanel' => $panelTree->getRoot()?->name]);
if (DEBUGRENDER) {
	// Data, not html: the debug tool draws both trees (Debug\BoxTree), and
	// its copy button hands them out as JSON.
	p(...$panelTree->debug())->target('panelTreeDebug')->type('boxTree');
	p(...$domRenderer->debug())->target('domLayersServer')->type('boxTree');
}
