<?php

class http implements \Systopic\System\Client\Contracts\PreloadsClientScript
{

	static $method;
	static $get;
	static $post;
	static $form;
	static $values; // form elements without form
	static $put; // containing all structured data sent from client
	static $binary;  // containing binary data encoded in put
	static $render; // array of views 
	static $dataset;
	static $dragdata;
	static $request; // post + get merged
	static $session;
	static $params; // session params + request (get + post)
	static $server;
	static $response = [];
	static $referrer;
	static $url; // uses referrer on put request -> should always be the visible browserbar url
	static $patterns = ['default' => '~^[A-Z0-9_/ \.:@\-]*$~i'];
	// host paths
	static $protocol; // http or https
	static $host; // name of the server / virtual host called
	static $hostUrl; // no terminating slash - can be concatenated with rootPaths including '/' - no '../' or '..'
	
	static $root; // project root path e.g. '/projects/projectname/public/' (DEV) or '/' (LIVE)
	static $rootUrl; // url of root
	static $projectRoot; // url path to project root (one level above public/) e.g. '/projects/projectname/'
	static $siteRoot; // url path to site (public) root - identical to $root when DOCUMENT_ROOT == public/
	static $siteUrl; // full url to site root
	// 
	static $sysPath; // e.g. /projects/projectname/vendor/systopic/system/ (DEV)
	static $sysRoot; // e.g. /projects/projectname/vendor/systopic/system/ (DEV)
	static $sysUrl;	// e.g. https://servername/projectsprojectname/vendor/systopic/system/	
	
	static $requestPath;
	static $requestRoot;
	static $requestUrl;

	/**
	 * A diff request addresses two URLs at once: the state the client is
	 * showing, and the state it wants. A tilde separates them.
	 *
	 *   /cms/settings/~/cms/pages/
	 *   /cms/pages/?id=20~/cms/pages/?id=18
	 *
	 * splitDiffUrl() takes the request apart before anything else looks at
	 * it, so the whole system only ever sees the TARGET half. This holds the
	 * other one — path and query, no host — or NULL on an ordinary request.
	 *
	 * It is the key the previous render was filed under: the renderer stores
	 * its layer tree under the url it rendered, and the next request names
	 * that url here to get it back. See DomRendererSession.
	 */
	static ?string $referenceUrl = null;

	static function getSocketIp(): string
	{
		return \Systopic\System\Http::getSocketIp();
	}

	static function getUrl(array $server): string
	{	// get the full url from the server array - not available in $SERVER array
		$protocol = isset($server['HTTPS']) ? 'https://' : 'http://';
		$host = $server['HTTP_HOST'];
		return $protocol . $host . $server['REQUEST_URI'];
	}

	static function getUrlInfo(string $url): object
	{	// designed to use url from getUrl() or from referrer()
		$parsed = parse_url($url);
		return (object) [
			'url' => $url,
			'protocol' => $parsed['scheme'],
			'host' => $parsed['host'],
			'path' => $parsed['path'] ?? '',
			'query' => $parsed['query'] ?? '',
		];
	}

	/**
	 * Url path of a filesystem path below the document root — NULL when the
	 * path lies outside it and is therefore not reachable over http.
	 * Accepts native ('V:\www\x') and internal ('/V/www/x') spelling.
	 *
	 * This is the one place that maps filesystem to url; resolveSysRootUrl()
	 * and callers that need the url of a folder both go through it.
	 */
	static function urlPath(string $path): ?string
	{
		$path = rtrim(fs::toInternal($path), '/');
		foreach (self::documentRoots() as $root) {
			if ($path === $root || str_starts_with($path, $root . '/')) {
				return rtrim(substr($path, strlen($root)), '/') . '/';
			}
		}
		return null;
	}

	private static ?array $documentRootsValue = null;
	private static ?string $documentRootsFor = null;

	/**
	 * The document root as configured and as resolved. They differ when the
	 * root is a symlink - Synology serves from /var/services/web, which points
	 * at /volume1/web - while __DIR__ and everything derived from it is always
	 * the resolved path. Comparing against the configured spelling alone
	 * would put every folder "outside" the document root.
	 *
	 * @return list<string> internal spelling, no trailing slash
	 */
	private static function documentRoots(): array
	{
		$configured = rtrim(fs::toInternal((string) (fs::$hostRoot ?? '')), '/');
		if (self::$documentRootsFor !== $configured) {
			self::$documentRootsFor = $configured;
			$roots = $configured === '' ? [] : [$configured];
			$real  = $configured === '' ? false : realpath(fs::toNative($configured));
			if ($real !== false) {
				$roots[] = rtrim(fs::toInternal($real), '/');
			}
			self::$documentRootsValue = array_values(array_unique($roots));
		}
		return self::$documentRootsValue;
	}

	/** absolute url of a filesystem path below the document root */
	static function urlOf(string $path): ?string
	{
		$urlPath = self::urlPath($path);
		return $urlPath === null ? null : self::$hostUrl . $urlPath;
	}

	static function setRoot(array $server): void
	{
		// dirname() yields the platform separator for a script at the root
		// ('/index.php' -> '\' on windows), so normalize before appending
		$scriptDir = str_replace('\\', '/', dirname($server['SCRIPT_NAME']));
		self::$root = rtrim($scriptDir, '/') . '/';
		// Before anything reads the request: a diff url carries two of them.
		$server = self::splitDiffUrl($server);
		$urlinfo = self::getUrlInfo(self::getUrl($server));
		self::$protocol = $urlinfo->protocol;
		self::$host = $urlinfo->host;
		fs::$hostRoot = fsDir::get($server['DOCUMENT_ROOT']);
		self::$sysRoot = self::resolveSysRootUrl($server);
		self::$projectRoot = fs::simplifyPath(self::$root . '../');
		self::$siteRoot = self::$root;
		self::$hostUrl = self::$protocol . '://' . self::$host;
		self::$rootUrl = self::$hostUrl . self::$root;
		self::$siteUrl = self::$hostUrl . self::$siteRoot;
		self::$sysUrl = self::$hostUrl . self::$sysRoot;
		self::$requestUrl = $urlinfo->url;
		self::$requestRoot = $urlinfo->path;
		self::$requestPath = str_replace(self::$root,'/',$urlinfo->path);
	}

	/**
	 * Takes a diff request apart into reference and target.
	 *
	 * The separator is a tilde followed by a slash. It sits BETWEEN the two
	 * urls and belongs to neither, so each half keeps its own slashes — and
	 * that matters: appRequest::parsePathString() tells a module path from an
	 * action by the trailing slash alone, so a reference that lost it would
	 * make 'settings' look like an action.
	 *
	 *   /cms/settings/~/cms/pages/
	 *      → '/cms/settings/'      + '/cms/pages/'
	 *   /cms/pages/?id=20~/cms/pages/?id=18
	 *      → '/cms/pages/?id=20'   + '/cms/pages/?id=18'
	 *
	 * $_GET is rebuilt here rather than in readGet(), because PHP filled it
	 * from the WHOLE query string — on the second form it holds
	 * id="20~/cms/pages/?id=18". Fixing it at the source also covers the two
	 * places that read $_GET directly instead of going through http::get():
	 * httpHelper's 'ajax' and mediaImage's 'rebuild'.
	 *
	 * A request without the separator is returned untouched, so nothing
	 * changes for ordinary traffic.
	 *
	 * @return array the server array, REQUEST_URI/QUERY_STRING now the target's
	 */
	private static function splitDiffUrl(array $server): array
	{
		$requestUri = (string) ($server['REQUEST_URI'] ?? '');
		$pos        = strpos($requestUri, '~/');
		// $pos === 0 would leave an empty reference — not a diff url.
		if ($pos === FALSE || $pos === 0) {
			return $server;
		}

		$reference = substr($requestUri, 0, $pos);
		$target    = substr($requestUri, $pos + 1); // the '/' stays with the target
		if (parse_url($target, PHP_URL_PATH) === NULL) {
			return $server; // not a url after the tilde — leave the request alone
		}

		// The client shortens the target against the project root, because
		// repeating it makes the url twice as long for no information:
		//   /projects/x/public/cms/pages/?id=19~/cms/pages/?id=20
		// Everything downstream expects a REQUEST_URI as the browser would
		// send it, so put the root back. A target that already carries it is
		// left alone — both spellings work.
		$root = rtrim(self::$root, '/');
		if ($root !== '' && !str_starts_with($target, $root . '/')) {
			$target = $root . $target;
		}

		self::$referenceUrl = $reference;

		$query = (string) parse_url($target, PHP_URL_QUERY);

		// Keys that came from the url are replaced by the target's; keys that
		// did NOT are kept. On the web that is the whole of $_GET and the
		// distinction costs nothing — on the cli it is what saves the params
		// the test harness injects directly (index.php, $cliInput['get']),
		// which never passed through a query string.
		$fromUrl = [];
		parse_str((string) ($server['QUERY_STRING'] ?? ''), $fromUrl);
		$fromTarget = [];
		parse_str($query, $fromTarget);
		$_GET = array_merge(array_diff_key($_GET, $fromUrl), $fromTarget);

		// $_REQUEST needs the same surgery: php built it from the whole query
		// string too, and it is what http::params() and http::request() read
		// (readParams / readRequest), plus the handful of places that reach
		// for $_REQUEST directly. Without this a target parameter is invisible
		// to every params() caller while 'id' still holds the reference's
		// mangled value - which is how cms/pages/editor/file/?target_id=58
		// found no media and redirected itself back to the page.
		// A key the body also sent keeps its value: only the url's are stale.
		$staleUrlKeys = array_diff_key($fromUrl, $_POST);
		$_REQUEST = array_merge(array_diff_key($_REQUEST, $staleUrlKeys), $fromTarget);

		$server['REQUEST_URI']   = $target;
		$server['QUERY_STRING']  = $query;
		$_SERVER['REQUEST_URI']  = $target;
		$_SERVER['QUERY_STRING'] = $query;

		return $server;
	}

	/**
	 * Compute the URL-path for the system root.
	 *
	 * Normally this is just `root/../SYS_PATH` (e.g. /project/vendor/systopic/system/).
	 * On servers where vendor/systopic/system/ is a symlink to a shared package directory
	 * (e.g. /packages/system/), Apache refuses to serve assets through the symlink with 403
	 * even when the files are readable.  In that case we resolve the real filesystem path
	 * via realpath() and re-derive the URL relative to the document root, so the browser
	 * requests /packages/system/public/css/… directly — no symlink traversal needed.
	 */
	private static function resolveSysRootUrl(array $server): string
	{
		$defaultRoot = fs::simplifyPath(self::$root . '../' . fs::$sysPath);

		$scriptFilename = $server['SCRIPT_FILENAME'] ?? '';
		$docRoot        = rtrim(str_replace('\\', '/', $server['DOCUMENT_ROOT'] ?? ''), '/');

		if ($scriptFilename === '' || $docRoot === '') {
			return $defaultRoot;
		}

		$scriptDir  = rtrim(str_replace('\\', '/', dirname($scriptFilename)), '/');
		$sysLogical = $scriptDir . '/../' . ltrim(str_replace('\\', '/', fs::$sysPath), '/');

		// Normalize the logical path by resolving '..' segments without touching the filesystem.
		// This gives us the "expected" path if there were no symlinks.
		$sysLogicalNorm = self::normalizeFsPath($sysLogical);

		// realpath() resolves both '..' and symlinks; if the result differs from the
		// manually-normalized path, a symlink was traversed.
		$sysReal = realpath($sysLogical);
		if ($sysReal === false) {
			return $defaultRoot;
		}
		$sysReal = rtrim(str_replace('\\', '/', $sysReal), '/');

		if ($sysReal === $sysLogicalNorm) {
			// No symlink involved — preserve existing behaviour.
			return $defaultRoot;
		}

		// Symlink resolved to a different real path. Use it as the URL only when
		// it still lives inside the document root (i.e. it is web-accessible).
		return self::urlPath($sysReal) ?? $defaultRoot;
	}

	/** Collapse '..' segments in an absolute path without hitting the filesystem. */
	private static function normalizeFsPath(string $path): string
	{
		$path  = rtrim(str_replace('\\', '/', $path), '/');
		$parts = explode('/', $path);
		$out   = [];
		foreach ($parts as $part) {
			if ($part === '..') {
				if ($out) {
					array_pop($out);
				}
			} elseif ($part !== '' && $part !== '.') {
				$out[] = $part;
			}
		}
		// Preserve leading slash for Unix paths; Windows paths start with e.g. "C:"
		$prefix = str_starts_with($path, '/') ? '/' : '';
		return $prefix . implode('/', $out);
	}

	static function getCoreJsSettings()
	{
		return [
			'protocol' => self::$protocol,
			'host' => self::$host,
			// as a string, like every other root here: an fsDir would be walked
			// property by property by json_encode() and by the debug dumper,
			// and its hooks would scan the directory and climb the parent chain
			'hostRoot' => (string) fs::$hostRoot,
			'hostUrl' => self::$hostUrl,
			'root' => self::$root,
			'rootUrl' => self::$rootUrl,
			// URL prefix for project-root assets (lib/class/*.js, panels/**/panel.js)
			// — used by loader.js when root === 'app' (DEV/STAGING).
			'projectRoot' => self::$projectRoot,
			'siteRoot' => self::$siteRoot,
			'siteUrl' => self::$siteUrl,
			'sysRoot' => self::$sysRoot,
			'sysUrl' => self::$sysUrl,
			// root name => URL prefix of add-on packages (loader.js, other roots)
			'packageRoots' => \Systopic\System\Sys\Packages::urls(),
			'requestRoot' => self::$requestRoot,
			'requestUrl' => self::$requestUrl,
			'requestPath' => self::$requestPath,
		];
	}

	static function debugInfo(): object
	{
		if (self::$host === null) {
			return (object) ['error' => 'setRoot() has not been called yet'];
		}
		return (object) self::getCoreJsSettings();
	}

	// static $app; // contains the path from server root to to the rutancms root folder

	/**
	 * Form field from PUT `form`, then POST body (migration helper).
	 * Use when a save action should accept both method=post and PUT-without-method.
	 */
	static function posted($name = null, $default = null)
	{
		$form = self::form();
		$post = self::post();
		if ($name === null) {
			if (is_object($form)) {
				return $form;
			}
			return is_object($post) ? $post : $form;
		}
		if (is_object($form) && isset($form->$name)) {
			return $form->$name;
		}
		if (is_object($post) && isset($post->$name)) {
			return $post->$name;
		}
		return $default;
	}

	static function __callStatic($name, $args)
	{ // e.g. http::get()->field or http::get('field','default')
		if (property_exists('http', $name)) {
			if (is_null(self::$$name)) { // self::$get created once by calling http::get() 
				$functionName = 'read' . ucfirst($name);
				if (is_callable(['http', $functionName])) {
					self::$functionName();
				}
			}
			if (count($args) === 0) { // return object (get, posst etc ..)
				return self::$$name;
			} else { // call with key/default -> return value http::get('id',42);
				$pathItems = explode('->', $args[0]);
				$index = 0;
				$trunk = self::$$name;
				while (isset($pathItems[$index])) {
					if (count($pathItems) > 1) {
						$pathItem = strtolower($pathItems[$index]); // why strtolower?? pls comment
					} else {
						$pathItem = $pathItems[$index]; // strtolower killed HTTP_REFERER
					}
					if (isset($trunk->$pathItem)) {
						$trunk = $trunk->$pathItem;
					} else { // return default
						return isset($args[1]) ? $args[1] : NULL;
					}
					$index++;
				}
				return $trunk;
			}
		}
		if (is_callable(['httpHelper', $name])) { // forward to helper
			return httpHelper::$name(...$args);
		}
		d(static::class . "::$name() not defined");
	}

	static function removeValue($group = 'get', $name = '')
	{
		self::$group(); // trigger callStatic -> read if not already read;
		unset(self::$$group->$name); // remove from array
	}

	static function readMethod()
	{
		self::$method = $_SERVER['REQUEST_METHOD'];
	}

	static function readGet()
	{
		self::$get = self::array2objFilter($_GET);
	}


	static function readPost()
	{
		self::$post = self::array2objFilter($_POST);
	}

	static function readPut()
	{
		self::$put = (object) [];
		if (isset($GLOBALS['__cli_put_data'])) {
			// Deep-convert nested arrays (dataset/values/…) so http::dataset()->key works in CLI.
			self::$put = self::array2objFilter($GLOBALS['__cli_put_data']);
			self::$binary = $GLOBALS['__cli_binary'] ?? null;
			return;
		}
		if (self::method() === 'PUT') {
			$putStream = file_get_contents('php://input');
			// condition may also be header
			if (preg_match('~^/\*json:(\d+)\*/~', $putStream ?? '', $match)) { // contains binary data
				$jsonLength = $match[1];
				$jsonStart = 9 + strlen($match[1]);
				$json = substr($putStream, $jsonStart, $jsonLength);
				self::$binary = substr($putStream, $jsonStart + $jsonLength);
				self::$put = json_decode($json) ?? FALSE;
			} else {
				self::$put = json_decode($putStream) ?? FALSE;
			}
			// php://input is not available with enctype="multipart/form-data"
		}
	}

	static function readRender()
	{
		self::$render = self::put('render') ?: [];
	}

	static function readReferrer()
	{
		if (self::server('HTTP_REFERER')) {
			self::$referrer = self::server('HTTP_REFERER');
		} else {
			self::$referrer = self::$requestUrl;
		}
	}

	static function readUrl()
	{
		if (self::method() === 'PUT') {
			self::$url = self::referrer();
		} else {
			self::$url = self::$requestUrl;
		}
	}

	/** The binary part comes with the PUT body — reading that fills both. */
	static function readBinary() {
		if (self::$put === null) {
			self::readPut();
		}
	}

	static function readValues()
	{ // shorthand for put->dataset
		self::$values = self::put('values') ?? FALSE;
	}

	static function readDataset()
	{ // shorthand for put->dataset
		self::$dataset = self::put('dataset') ?? FALSE;
	}

	static function readForm()
	{ // shorthand for put->dataset
		self::$form = self::put('form') ?? FALSE;
	}

	static function readDragdata()
	{ // shorthand for put->dragdata.dataset (or put->dragdata if flat)
		$putDragdata = self::put('dragdata');
		if (!$putDragdata) {
			self::$dragdata = (object) [];
			return;
		}
		$dataset = $putDragdata->dataset ?? null;
		// Normal: put.dragdata.dataset has node_id/nodeId
		if ($dataset && (isset($dataset->node_id) || isset($dataset->nodeId))) {
			self::$dragdata = $dataset;
			return;
		}
		// Fallback: put.dragdata is flat (node_id/nodeId at top level)
		if (isset($putDragdata->node_id) || isset($putDragdata->nodeId)) {
			self::$dragdata = $putDragdata;
			return;
		}
		self::$dragdata = $dataset ?: (object) [];
	}

	static function readRequest()
	{
		// merge untested
		self::$request = self::array2objFilter(array_merge($_REQUEST, (array) self::readPut()));
	}

	static function readServer()
	{
		self::$server = self::array2objFilter($_SERVER);
	}

	static function readParams()
	{
		if (isset($_SESSION['systopic_params'])) {
			$params = array_merge((array) $_SESSION['systopic_params'], $_REQUEST);
			self::$params = self::array2objFilter($params);
		} else {
			self::$params = self::array2objFilter($_REQUEST);
		}
	}

	static function paramUnset($name)
	{
		if (isset($_SESSION['systopic_params']) && isset($_SESSION['systopic_params'][$name])) {
			unset($_SESSION['systopic_params'][$name]);
		}
		if (isset(self::$params->$name)) {
			unset(self::$params->$name);
		}
	}

	static function sessionWriteClose()
	{
		session_write_close();
	}

	static function sessionResume()
	{
		self::sessionStartOnce();
	}

	static function sessionStartOnce()
	{
		if (headers_sent()) { // it's too late :(
			return;
		}
		// t('sessionStartOnce');
		scripttime('pre session status');
		if (session_status() == PHP_SESSION_NONE) {
			scripttime('pre session start');
			session_start();
			scripttime('session start done');
			if (!empty($GLOBALS['__cli_session_merge']) && is_array($GLOBALS['__cli_session_merge'])) {
				foreach ($GLOBALS['__cli_session_merge'] as $k => $v) {
					$_SESSION[$k] = $v;
				}
				unset($GLOBALS['__cli_session_merge']);
			}
		}
		scripttime('session status done');
		if (!isset($_SESSION['systopic_public_params'])) {
			$_SESSION['systopic_public_params'] = (object) [];
		}
	}

	static function initSession()
	{
		self::sessionStartOnce();
		if (!isset($_SESSION['systopic_params']) || !is_object($_SESSION['systopic_params'])) {
			$_SESSION['systopic_params'] = (object) [];
		}
	}

	static function session($key = NULL, $default = NULL)
	{ // write access: http::session()->$key = $value
		self::initSession();
		if (is_null(self::$session)) {
			self::$session = &$_SESSION['systopic_params'];
		}
		if ($key) {
			return isset(self::$session->$key) ? self::$session->$key : $default;
		}
		return self::$session;
	}

	static function killSession()
	{
		$_SESSION = [];
		if (ini_get("session.use_cookies")) {
			$cookie = (object) session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httponly);
		}
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}
	}


	static function array2objFilter($array)
	{
		$obj = new stdClass;
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$obj->$key = self::array2objFilter($value);
			} else {
				$pattern = isset(self::$patterns[$key]) ? self::$patterns[$key] : self::$patterns['default'];
				if (preg_match($pattern, $value ?? '')) {
					$obj->$key = $value;
				} else {
					$obj->$key = $value; // filter disabled !vorsicht
					// $obj->$key = 'REMOVED_INPUT_FILTER:http class';
				}
			}
		}
		return $obj;
	}
}
