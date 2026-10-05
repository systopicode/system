<?php

#[AllowDynamicProperties]
class httpHelper {

	static function getHrefInfo($href) {
		// 2do Definition:
		// pfad /cms/pages/ vs /projekt.ordner/cms/pages/ vs ./cms/pages/
		// auf welches root beziehen sich relative pfade ?
		// - aktuelle browser url => http konform
		// 
		preg_match('~^(/|\.\./|).*?(/\.\./|).*?(/|)$~', $href, $match) || d("invalid href passed '$href'");
		return (object) [
				'isAbsolute' => $match[1] === '/',
				'isSimplified' => $match[2] === '' && $match[1] !== '../',
				'isTerminated' => $match[3] === '/' ? '' : '/',
		];
	}

	static function hrefToDirPath($href) {
		// find 
		p(self::getHrefInfo($href));
		return $href;
	}

	static function filepathToHref($filepath) {
		if (0 === strpos($filepath, fs::$siteRoot)) {
			return http::$root . substr($filepath, strlen(fs::$siteRoot));
		}
		if (0 === strpos($filepath, fs::$sysRoot)) {
			return http::$sysRoot . substr($filepath, strlen(fs::$sysRoot));
		}
		if (0 === strpos($filepath, fs::$projectRoot)) {
			return http::$projectRoot . substr($filepath, strlen(fs::$projectRoot));
		}
		// add-on packages (systopic/cms …), where they are web-reachable
		$urls = \Systopic\System\Sys\Packages::urls();
		foreach (\Systopic\System\Sys\Packages::libs() as $name => $dir) {
			$dir = fs::toInternal($dir);
			if (isset($urls[$name]) && 0 === strpos($filepath, $dir)) {
				return $urls[$name] . substr($filepath, strlen($dir));
			}
		}
		d("cant get href of '$filepath'");
	}

	static function queryString($dataOrPrefix = '?') { // -> finish class httpQuery
		$query = clone http::get();
		if (is_string($dataOrPrefix)) { // prefix
			$queryString = http_build_query((array) $query);
			return $queryString ? $dataOrPrefix . $queryString : '';
		}
		foreach ($dataOrPrefix AS $field => $value) {
			if (is_null($value)) { // data -> pass NULL value to remove property from querystring
				unset($query->$field);
				continue;
			}
			$query->$field = $value;
		}
		return http_build_query((array) $query);
	}

	static function redirect($path, $action = '', $get = []) { // page is not rendered
		// d($path);
		if (http::ajax()) {
			// 2do
			// skip execution
			// set fake url // internal redirect counter
			// rerun loader
			// or set redirect header -> update call.send -> 
			client::addCommand('redirect')
				->data([
					'path' => $path,
					'action' => $action,
					'get' => $get,
					'method' => 'get',
				])
				->delay(0);
		} else {
			$queryString = empty($get) ? '' : '?' . http_build_query($get);
			header("Location: $path$action$queryString");
			exit;
		}
	}

	static function refresh($path, $action = '', $get = [], $delay = 1, $method = null) { // page is rendered
		if ($method === null) {
			// Auto: PUT when a named action is dispatched (multi-step flows), GET otherwise
			$method = (is_string($action) && $action !== '') ? 'put' : 'get';
		}
		if (http::ajax()) {
			client::addCommand('refresh')
				->data([
					'path'   => $path,
					'action' => $action,
					'get'    => $get,
					'method' => $method,
				])
				->delay($delay);
		} else {
			$queryString = empty($get) ? '' : '?' . http_build_query($get);
			header("Refresh:$delay; url=$path$action$queryString");
		}
	}

	static function permissionDenied() { // page is not rendered
		header('HTTP/1.0 403 Forbidden');
		d('access denied');
	}

	static function getCtype($ext) {
		switch ($ext) {
			case "gif": return "image/gif";
			case "png": return "image/png";
			case "jpeg":
			case "jpg": return "image/jpeg";
			case "svg": return "image/svg+xml";
			case "pdf": return "application/pdf";
			default: return "application/$ext";
		}
	}

	static function header($extOrFilename = 'pdf', $action = 'show') {
		// outputs header for file downlodas or inlinen viewer
		// checks noheader flag for debugging
		$ext = substr($extOrFilename, -3);
		$filename = $ext === $extOrFilename ? "file.$ext" : $extOrFilename;
		$disp = [
			'show' => 'inline',
			'download' => 'application',
			][$action];
		if (http::get('noheader', TRUE)) {
			header("Content-type: " . self::getCtype($ext));
			header("Content-Disposition:$disp;filename=$filename");
			return TRUE;
		}
		return FALSE;
	}

	static function ajax() {
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
				return TRUE;
			}
		}
		if (DEBUG() && isset($_GET['ajax'])) {
			return TRUE;
		}
		return FALSE;
	}

}
