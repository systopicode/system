<?php

#[AllowDynamicProperties]
class fsUploader implements clientClass, \Systopic\System\Client\Contracts\PreloadsClientScript {
	/*	 * **************** GET/CREATE ****************** */

	static $uploaders = [];

	static function get($id = NULL, $params = []) {
		$uid = $id ?? str::randomHex();
		$uploader = self::$uploaders[$uid] ?? self::$uploaders[$uid] = new fsUploader($uid);
		foreach ($params as $param => $value) {
			$uploader->$param = $value;
		}
		return $uploader;
	}

	/*	 * **************** PROPERTIES ****************** */
	// Virtual, recomputed on every read - they were __get cases before.
	// `path` stays a plain property so get($id, ['path' => …]) can still
	// override it; the derived ones are read-only.

	public string $id;
	public string $path = 'var/uploader/';
	protected $defaultHook;
	protected $onAfterStoreSpanFunc;
	protected $onFinishFunc;

	/** Web-reachable temp uploads live under public/var/ (siteRoot), not project root */
	public string $dirpath {
		get => (fs::$siteRoot ? (string) fs::$siteRoot : '') . $this->path;
	}

	public string $hrefpath {
		get => http::$root . $this->path;
	}

	public string $filepath {
		get => $this->getStoredFile()->filepath;
	}

	public fsFile $file {
		get => $this->getStoredFile();
	}

	public string $partialFilepath {
		get => $this->dirpath . $this->id . '.part';
	}

	public fsFile $partialFile {
		get => fsFile::get($this->partialFilepath);
	}

	/**
	 * Resolve the stored upload blob. After finish the file is renamed to
	 * `{id}.{mimeext}` so Apache may serve it from /var/ (extension allow-list
	 * + nosniff). During multipart upload it is still `{id}.part` / `{id}`.
	 */
	function getStoredFile(): fsFile {
		$exact = fsFile::get($this->dirpath . $this->id);
		if ($exact->exists) {
			return $exact;
		}
		$matches = fs::glob($this->dirpath . $this->id . '.*') ?: [];
		foreach ($matches as $match) {
			if (str_ends_with($match, '.part')) {
				continue;
			}
			return fsFile::get($match);
		}
		return $exact;
	}

	/** Rename bare `{id}` to `{id}.{mimeext}` for direct /var/ HTTP serving. */
	function ensureWebFilename(): fsFile {
		$file = fsFile::get($this->dirpath . $this->id);
		if (!$file->exists) {
			return $this->getStoredFile();
		}
		$ext = $file->mimeext;
		if (!$ext) {
			return $file;
		}
		$webName = $this->id . '.' . $ext;
		if ($file->name !== $webName) {
			$file->rename($webName);
		}
		return fsFile::get($this->dirpath . $webName);
	}

	public function __construct($id) {
		$this->id = $id;
		$this->defaultHook = function ($uploader) {
			return $uploader;
		};
		$this->onAfterStoreSpanFunc = $this->defaultHook;
		$this->onFinishFunc = $this->defaultHook;
	}

	/*	 * **************** ACTION ****************** */

	function getClientFileInfo() {
		t('depr. use getFileinfo');
		return $this->getFileinfo();
	}

	function getFileinfo() { // maybe add session support
		if (http::put('fileInfo')) {
			return json_decode(http::put('fileInfo'));
		}
	}

	function storeSpan() {
		$input = (object) [
			    'spanIndex' => http::put('spanIndex'),
			    'lastSpan' => http::put('lastSpan'),
			    'blob' => http::binary(),
		];
		fs::mkdir($this->dirpath);
		if ((int) $input->spanIndex === 0) {
			fs::file_put_contents($this->partialFilepath, $input->blob);
			($this->onAfterStoreSpanFunc)($this);
		} else {
			fs::file_put_contents($this->partialFilepath, $input->blob, FILE_APPEND);
			($this->onAfterStoreSpanFunc)($this);
		}
		if ($input->lastSpan) {
			$this->partialFile->rename($this->id);
			// 2do check size / hash
			// Add mime extension so public/.htaccess may serve /var/* (allow-list + nosniff)
			$this->ensureWebFilename();

			($this->onFinishFunc)($this);
		}
	}

	function response() {
		return (object)[
		    'id' => $this->id,
		];
	}

	function onFinish($onFinishFunc) {
		$this->onFinishFunc = $onFinishFunc;
		return $this;
	}

	function onAfterStoreSpan($onAfterStoreSpanFunc) {
		$this->onAfterStoreSpanFunc = $onAfterStoreSpanFunc;
		return $this;
	}

	function reset() {
		$this->file->unlink();
		$this->partialFile->unlink();
	}

	/** Handle storeSpan / fetchFromUrl on whatever panel is selected (keeps lightbox/form in the DOM). */
	static function handleAction(string $action): bool {
		switch ($action) {
			case 'storeSpan':
				$id = \http::put('id');
				$uploader = self::isValidId((string) $id) ? self::get((string) $id) : self::get();
				$uploader
					->onAfterStoreSpan(function ($u) {
						\client::done($u->response());
					})
					->storeSpan();
				return true;
			case 'fetchFromUrl':
				$url = (string) (\http::put('url') ?? '');
				$accept = (string) (\http::put('accept') ?? '');
				$id = \http::put('id');
				$result = self::fetchFromUrl($url, $accept !== '' ? $accept : null, self::isValidId((string) $id) ? (string) $id : null);
				\client::done($result);
				return true;
			default:
				return false;
		}
	}

	static function isValidId($id): bool {
		return is_string($id) && (bool) preg_match('/^[a-f0-9]{8,64}$/i', $id);
	}

	static function mimeMatchesAccept(string $mime, string $filename, string $accept): bool {
		$accept = trim($accept);
		if ($accept === '') {
			return true;
		}
		$mime = strtolower($mime);
		$name = strtolower($filename);
		foreach (array_map('trim', explode(',', $accept)) as $token) {
			$token = strtolower($token);
			if ($token === '') {
				continue;
			}
			if (str_ends_with($token, '/*')) {
				if (str_starts_with($mime, substr($token, 0, -1))) {
					return true;
				}
			} elseif ($token[0] === '.') {
				if (str_ends_with($name, $token)) {
					return true;
				}
			} elseif ($mime === $token) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Download a remote URL into var/uploader/{id} (same staging as storeSpan).
	 * Used when a browser drop only provides text/uri-list (other websites).
	 */
	static function fetchFromUrl(string $url, ?string $accept = null, ?string $id = null): object {
		$maxBytes = 15 * 1024 * 1024;
		$parsed = parse_url($url);
		$scheme = strtolower((string) ($parsed['scheme'] ?? ''));
		$host = strtolower((string) ($parsed['host'] ?? ''));
		if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
			return (object) ['ok' => false, 'error' => 'Ungültige URL'];
		}
		$blocked = ['localhost', '127.0.0.1', '::1', '169.254.169.254'];
		if (in_array($host, $blocked, true) || str_ends_with($host, '.localhost')) {
			return (object) ['ok' => false, 'error' => 'URL nicht erlaubt'];
		}

		$blob = false;
		$contentType = '';
		$pathName = basename((string) ($parsed['path'] ?? 'download'));
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 3,
				CURLOPT_TIMEOUT => 12,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_USERAGENT => 'fsUploader/1.0',
				CURLOPT_PROTOCOLS => defined('CURLPROTO_HTTP') ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : 3,
				CURLOPT_REDIR_PROTOCOLS => defined('CURLPROTO_HTTP') ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : 3,
			]);
			$blob = curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			$err = curl_error($ch);
			curl_close($ch);
			if ($blob === false || $code < 200 || $code >= 300) {
				return (object) ['ok' => false, 'error' => $err !== '' ? $err : ('HTTP ' . $code)];
			}
		} else {
			$ctx = stream_context_create([
				'http' => ['timeout' => 12, 'follow_location' => 1, 'header' => "User-Agent: fsUploader/1.0\r\n"],
				'ssl' => ['verify_peer' => true],
			]);
			$blob = @file_get_contents($url, false, $ctx);
			if ($blob === false) {
				return (object) ['ok' => false, 'error' => 'Download fehlgeschlagen'];
			}
		}
		if (!is_string($blob) || $blob === '') {
			return (object) ['ok' => false, 'error' => 'Leere Antwort'];
		}
		if (strlen($blob) > $maxBytes) {
			return (object) ['ok' => false, 'error' => 'Datei zu groß'];
		}

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->buffer($blob) ?: preg_replace('/;.*$/', '', $contentType) ?: 'application/octet-stream';
		$mime = strtolower((string) $mime);
		if ($accept && !self::mimeMatchesAccept($mime, $pathName, $accept)) {
			return (object) ['ok' => false, 'error' => 'Dateityp nicht erlaubt'];
		}

		$uploader = ($id && self::isValidId($id)) ? self::get($id) : self::get();
		fs::mkdir($uploader->dirpath);
		fs::file_put_contents($uploader->dirpath . $uploader->id, $blob);
		$stored = $uploader->ensureWebFilename();
		$safeName = str::getSafeName($pathName !== '' && $pathName !== '/' ? $pathName : 'download');
		if ($safeName === '' || $safeName === '.') {
			$ext = $stored->ext ?: '';
			$safeName = 'download' . ($ext ? '.' . $ext : '');
		} elseif ($stored->ext && !str_ends_with(strtolower($safeName), '.' . strtolower((string) $stored->ext))) {
			$safeName .= '.' . $stored->ext;
		}

		return (object) [
			'ok' => true,
			'id' => $uploader->id,
			'name' => $safeName,
			'type' => $mime,
			'href' => $stored->href,
		];
	}

	/**
	 * Read a staged uploader from POST `{field}_uploader` / `{field}_uploader_name`,
	 * falling back to $_FILES[$field]. Returns tmp_name/name like $_FILES, plus uploader instance.
	 */
	static function postedFile(string $fieldName): ?array {
		$id = (string) (http::posted($fieldName . '_uploader') ?? http::put($fieldName . '_uploader') ?? '');
		$name = (string) (http::posted($fieldName . '_uploader_name') ?? http::put($fieldName . '_uploader_name') ?? '');
		if ($id !== '' && self::isValidId($id)) {
			$uploader = self::get($id);
			$file = $uploader->getStoredFile();
			if ($file->exists) {
				return [
					'tmp_name' => $file->filepath,
					'name' => $name !== '' ? $name : $file->name,
					'uploader' => $uploader,
				];
			}
		}
		if (!empty($_FILES[$fieldName]['tmp_name']) && ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
			return [
				'tmp_name' => $_FILES[$fieldName]['tmp_name'],
				'name' => $_FILES[$fieldName]['name'] ?? 'upload',
				'uploader' => null,
			];
		}
		if (!empty($_FILES['file']['tmp_name'][0]) && ($_FILES['file']['error'][0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && $fieldName === 'file') {
			return [
				'tmp_name' => $_FILES['file']['tmp_name'][0],
				'name' => $_FILES['file']['name'][0] ?? 'upload',
				'uploader' => null,
			];
		}
		return null;
	}

	/*	 * **************** TEMPLATE ****************** */

	function toForm() {
		$mode = $this->uploadMode ?? 'instant';
		$upload = html::create('div#uploader');
		$upload->attr('data-upload', $mode);
		$upload->append('p#info');
		$controls = html::create('div#controls');
		$controls->append('div#actions');
		$upload->append($this->toPreview());
		$controls->append('div.bar div#bar b#barinfo1')->close()->append('b#barinfo2');
		$upload->append($controls);
		return $upload;
	}

	function toPreview() {
		$image = html::create('img')->doc("img");
		$video = html::create('div.video')
				->append('video[muted]')->doc("video")->close()
				->append('input[type=range]')->min(0)->max(100)->doc("videoRange")->close();
		$audio = html::create('div.audio')
				->append('audio[controls]')->doc("audio")->close();
		$generic = html::create('div.generic')->doc("generic");

		$preview = html::create("div.uploader")->doc("uploader");
		$preview->append($image);
		$preview->append($video);
		$preview->append($audio);
		$preview->append($generic);
		$preview->append('input[type=file]')->accept($this->acceptFormats)->doc('input');
		return $preview;
	}
}