<?php

#[AllowDynamicProperties]
class template {

	static $templates; // obj collection of templates by name
	static $dir = 'templates';

	function __construct($name, $fileOrFolder, $type) {
		$this->type = $type;
		$this->name = $name;

		switch ($type) {
			case 'file':
				$this->file = "$fileOrFolder";
				$this->folder = self::$dir;
				$this->hasBody = FALSE;
				break;
			default:
				if (fs::isFile('body.tpl.php')) {
					$this->file = 'body.tpl.php';
					$this->hasBody = TRUE;
				} else {
					$this->file = 'main.tpl.php';
					$this->hasBody = FALSE;
				}
				$this->folder = self::$dir . "/$fileOrFolder/";
				break;
		}
	}

	static function exists($name) {
		if (!isset(self::$templates)) {
			self::scanDir();
		}
		return isset(self::$templates->$name);
	}

	static function get($name) {
		if (!isset(self::$templates)) {
			self::scanDir();
		}
		if (isset(self::$templates->$name)) {
			return self::$templates->$name;
		} else {

			//message::error("template class: Template not found <b>$name</b>")->target('template');
		}
	}

	static function getAll() {
		if (!isset(self::$templates)) {
			self::scanDir();
		}
		return self::$templates;
	}

	/**
	 * Every template there is, by name (numeric prefix stripped).
	 *
	 * templates/pages/ when the project uses the structured layout
	 * (templates/{document,components,pages}), templates/ otherwise. Then the
	 * page templates of every site (sites/<domain>/templates/pages/): a name
	 * the shared folder does not have is added, so the CMS can assign it. Which
	 * folder a page actually renders from — shared or its site's — is decided
	 * per page by Pages\Builder\ViewResolver, not here.
	 */
	static function scanDir() {
		if (!fs::openRootDir()) {
			error_log('template::scanDir: failed to open root dir, cwd: ' . fs::cwd());
			return;
		}
		if (self::$templates) {
			fs::closeDir();
			return;
		}
		self::$templates = (object) [];
		if (fs::isDir('templates/pages')) {
			self::$dir = 'templates/pages';
		}
		self::scanFolder(self::$dir);
		foreach (fs::glob('sites/*/templates/pages', GLOB_ONLYDIR) ?: [] as $siteDir) {
			// glob answers in native spelling; openDir() counts '/' segments
			self::scanFolder(str_replace('\\', '/', $siteDir));
		}
		fs::closeDir(); // fs::openRootDir()
		if (!count((array) self::$templates)) {
			message::guide()->noTemplates()->target('template');
		}
	}

	/**
	 * The templates a page of one site can get, by name, in prefix order: the
	 * shared folder, then sites/<site>/templates/pages/ (a name the shared
	 * folder has wins). Other sites' templates are not in it — the CMS offers
	 * only what the page can render. Leaves getAll() alone.
	 *
	 * @param ?string $site the site's name (= its folder), null for none
	 */
	static function forSite(?string $site): object {
		if (!fs::openRootDir()) {
			return (object) [];
		}
		$all = self::$templates;
		self::$templates = (object) [];
		if (fs::isDir('templates/pages')) {
			self::$dir = 'templates/pages';
		}
		self::scanFolder(self::$dir);
		if ($site !== null && $site !== '' && fs::isDir("sites/$site/templates/pages")) {
			self::scanFolder("sites/$site/templates/pages");
		}
		fs::closeDir(); // fs::openRootDir()
		$found = (array) self::$templates;
		self::$templates = $all;
		uasort($found, static fn($a, $b) => strnatcmp((string) $a->prefix, (string) $b->prefix));
		return (object) $found;
	}

	/** Adds the templates of one folder; names already known are kept. */
	private static function scanFolder(string $dir) {
		if (!fs::openDir($dir)) {
			return;
		}
		$shared = self::$dir;
		self::$dir = $dir; // the constructor builds ->folder from it
		foreach (fs::glob('*') as $file) {
			if (!str_ends_with($file, '.php') && !fs::isDir($file)) {
				continue; // readme.txt and the like
			}
			preg_match('~^(\d+_|)(.*?)(\.php|)$~', $file, $info);
			$name = $info[2];
			if ($name === '' || isset(self::$templates->$name)) {
				continue;
			}
			$prefix = $info[1];
			if (fs::isDir($file)) {
				fs::openDir($file);
				if (fs::isFile("template.php")) {
					include_once "template.php";
					$classname = 'template' . ucfirst($name);
					self::$templates->$name = new $classname($name, $file, 'class');
				} else {
					self::$templates->$name = new template($name, $file, 'folder');
				}
				fs::closeDir(); // fs::openDir($file);
			} else {
				self::$templates->$name = new template($name, $file, 'file');
			}
			self::$templates->$name->prefix = $prefix;
		}
		self::$dir = $shared;
		fs::closeDir(); // fs::openDir($dir)
	}
	static function getDefaultTemplate() {
		return fs::file_get_contents(__DIR__ . '/default.php');
	}
}
