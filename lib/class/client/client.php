<?php

#[AllowDynamicProperties]
class client {

	public static $response = [];
	private static $jsAppFiles = [];
	public static $jsAppFilesSent = [];
	public static $cssAppFiles = [];

// HTML Document updates
	public static function __callStatic($name, $arguments) {
		if (method_exists('clientDocument', $name)) { // invoke sublass clientDom for standard dom manipulation functions
			return call_user_func_array(['clientDocument', $name], $arguments);
		}
		die("no method here :'$name'");
	}

	static function done($dataPassedToCallbackFunction) {
		return self::addCommand('done')->data($dataPassedToCallbackFunction);
	}

	static function loadCSS($cssFiles) {
		switch (gettype($cssFiles)) {
			case 'string': return self::$cssAppFiles[] = $cssFiles;
			case 'array': return self::$cssAppFiles += $cssFiles;
		}
	}

	static function fileExists($fileInfo) {
		// app = projectRoot/lib/class (outside public); sys = system package;
		// any other root is a package that announced a lib root (Sys\Packages)
		$fsRoot = match ($fileInfo->root) {
			'app' => fs::$projectRoot,
			'sys' => fs::$sysRoot,
			default => fs::toInternal((string) \Systopic\System\Sys\Packages::libDir($fileInfo->root)),
		};
		$fsSource = "$fsRoot$fileInfo->path$fileInfo->className.js";
		return fs::isFile($fsSource);
	}

	static function loadJSfromFileInfo($fileInfo) {
		if ($fileInfo->reflectionClass->implementsInterface('clientSingleton')) {
			$type = 'singleton';
		} else {
			$type = 'class';
		}
		while (TRUE) {
			$parentReflectionClass = $fileInfo->reflectionClass->getParentClass();
			if (self::fileExists($fileInfo)) {
				client::loadJS($fileInfo->root, "$fileInfo->path$fileInfo->className.js", $type, [$fileInfo->className]);
				// p("LOAD JS: $fileInfo->path$fileInfo->className.js");
			} else {
				// p("SKIP JS: $fileInfo->path$fileInfo->className.js");
			}
			if ($parentReflectionClass) {
				fs::load($parentReflectionClass->getName());
				$fileInfo = fsAutoloader::getFileInfoByClassname($parentReflectionClass->getName());
				if ($fileInfo === false) {
					// Parent class (e.g. Panels\PanelNode) is loaded via Composer, not fsAutoloader — stop walking.
					break;
				}
				continue;
			}
			break;
		}
	}

	static function loadJS($root, $src, $type, $classNames) { 
		// p('loadJS',$src);
		if ($root === 'cms') {
			d("loadJS depr. root type '$root'");
		}
		self::$jsAppFiles[] = (object) [
				'id' => "$root:$src",
				'root' => $root,
				'src' => $src,
				'type' => $type, // type is classname or sigleton
				'classNames' => $classNames, // containing classes
		];
	}

	public static $jsSettings = [];

	static function addJsSettings($settings) {
		static::$jsSettings = array_merge(static::$jsSettings, $settings);
	}

	static function updateClientData($object, $data) {
		$class = get_class($object);
		$index = $object->index();
		// p("updateClientData $class #$object->id");
		if (!key_exists('ids', $class::$clientData)) {
			$class::$clientData['ids'] = [];
		}
		if (!key_exists($index, $class::$clientData['ids'])) {
			$class::$clientData['ids'][$index] = [];
		}
		foreach ($data as $key => $value) {
			$class::$clientData['ids'][$index][$key] = $value;
		}
		$class::$clientData['ids'][$index]['selector'] = $object->jsHandle();
	}

	static function flushScripts() {
		if (defined('JSOFF')) {
			return '';
		}
		// d(self::$jsAppFiles);
		$html = "<!-- ************** client::flushScripts() ************** -->\n";
		$loadingScripts = json_encode(self::$jsAppFiles);
		$html .= "<script>sys.loader.addScripts($loadingScripts)</script>\n";
		return "$html<!-- ************** END flushScripts() ************** -->\n\n";
	}

	static function flushCSS() {
		$html = "<!-- ************** client::flushCSS() ************** -->\n";
		foreach (self::$cssAppFiles AS $jsFile) {
			// $src = http::$app . $jsFile;
			// $html .= "	<script src='$src'></script>\n";
		}
		return "$html<!-- ************** END flushCSS() ************** -->\n\n";
	}

	static function collectActiveModuleCssFiles() {
		$files = [];
		$seen = [];
		$realPath = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRealPath() ?? [];
		if (!is_array($realPath) || empty($realPath)) {
			return $files;
		}

		$collectFromDir = function($fsDir, $urlBase) use (&$files, &$seen) {
			if (!fs::isDir($fsDir)) {
				return;
			}
			$cssFiles = fs::glob(rtrim($fsDir, '/') . '/*.css') ?: [];
			sort($cssFiles, SORT_STRING | SORT_FLAG_CASE);
			foreach ($cssFiles as $cssFile) {
				$name = basename($cssFile);
				if ($name === 'compressed.css') {
					continue;
				}
				$url = rtrim($urlBase, '/') . '/' . $name . PUBLIC_VERSIONAPPENDIX;
				$key = preg_replace('~\?.*$~', '', $url);
				if (isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$files[] = $url;
			}
		};

		$projectRootUrl = fs::simplifyPath(http::$root . '../');
		$packageUrls = \Systopic\System\Sys\Packages::urls();

		$pathParts = $realPath;
		foreach ($realPath as $unused) {
			$modulePathString = implode('/', $pathParts);
			$collectFromDir(
				fs::$sysRoot . "panels/$modulePathString/",
				http::$sysRoot . "panels/$modulePathString/"
			);
			foreach (\Systopic\System\Sys\Packages::panels() as $package) {   // systopic/cms …
				$url = $packageUrls[$package['name']] ?? NULL;
				if ($url !== NULL) {
					$collectFromDir(fs::toInternal($package['dir']) . "$modulePathString/", $url . "panels/$modulePathString/");
				}
			}
			$collectFromDir(
				fs::$projectRoot . "panels/$modulePathString/",
				$projectRootUrl . "panels/$modulePathString/"
			);
			$collectFromDir(
				fs::$siteRoot . "panels/$modulePathString/",
				http::$root . "panels/$modulePathString/"
			);
			array_pop($pathParts);
		}

		return $files;
	}

	static function flushResponse() {
		$queued   = self::$response;
		$moduleCssFiles = self::collectActiveModuleCssFiles();
		if (!empty($moduleCssFiles)) {
			self::loadCSS($moduleCssFiles);
		}
		// updateClientData MUST be the first command so that JS commands like
		// updateDocument can read the freshly updated singletons (e.g.
		// sys.singletons.dom.renderer.__data.layers) before they are consumed.
		$return = [[
			'command' => 'updateClientData',
			'data' => clientInterfaces::clientData_export()
		]];
		foreach ($queued as $cmd) {
			$return[] = $cmd;
		}
		if (is_callable('printPrompt')) {
			if (isset($GLOBALS['phpPrompt'])) {
				$return [] = array(
					'command' => 'prompt',
					'selector' => 'div.debug.ajaxResponse',
					'html' => printPrompt(),
				);
			}
			if (!empty($GLOBALS['scripttimeLog'])) {
				$return [] = array(
					'command' => 'prompt',
					'selector' => 'div.debug.scripttime',
					'html' => printScripttime('', FALSE),
				);
			}
		}
		// The server layer tree used to ride along here as html, into a
		// div.debug.domLayersServer of the old debug ui that nothing renders
		// any more. app.php / site.php hand it to the debug tool as data:
		// p(...)->target('domLayersServer')->type('boxTree').
		if (count(self::$jsAppFiles)) {
			$return [] = [
				'command' => 'loadJS',
				'scriptFiles' => self::$jsAppFiles
			];
		} else {
			p('really nothing to load -> should run onJSready');
		}
		if (count(self::$cssAppFiles)) {
			$return [] = [
				'command' => 'loadCSS',
				'cssFiles' => self::$cssAppFiles
			];
		}
		self::$response = [];
		return $return;
	}

}
