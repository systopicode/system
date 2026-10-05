<?php

class appRequest
{
	public ?string $presetLang = null;

	public function __construct(
		private readonly string $inputUrl,
		private readonly bool $isMainRequest = false,
	) {}

	private bool $editingContextParsed = false;
	private bool $httpInputParsed = false;

	/* ---------- backing: editing context ---------- */
	private bool $preview_value = false;
	private bool $showTrashed_value = false;
	private mixed $showEditableAreas_value = false;

	/* ---------- backing: HTTP input ---------- */
	private string $rawPath_value = '';
	private string $action_value = '';
	private array $modulePath_value = [];
	private mixed $alias_value = false;

	/* ---------- settable: resolved by caller ---------- */
	public mixed $pageResolutionType = null;
	public mixed $id = null;
	public mixed $lang = null;

	/* ======================== EDITING CONTEXT PROPERTIES ======================== */

	public bool $preview {
		get {
			$this->initEditingContext();
			return $this->preview_value;
		}
		set {
			$this->editingContextParsed = true;
			$this->preview_value = $value;
		}
	}

	public bool $showtrashed {
		get {
			$this->initEditingContext();
			return $this->showTrashed_value;
		}
		set {
			$this->editingContextParsed = true;
			$this->showTrashed_value = $value;
		}
	}

	public mixed $showeditableareas {
		get {
			$this->initEditingContext();
			return $this->showEditableAreas_value;
		}
		set {
			$this->editingContextParsed = true;
			$this->showEditableAreas_value = $value;
		}
	}

	/* ======================== HTTP INPUT PROPERTIES ======================== */

	public string $rawpath {
		get => $this->parseHttpInput()->rawPath_value;
	}

	public string $action {
		get => $this->parseHttpInput()->action_value;
	}

	public array $modulePath {
		get => $this->parseHttpInput()->modulePath_value;
	}

	public mixed $alias {
		get => $this->parseHttpInput()->alias_value;
	}

	/* ======================== PUBLIC API ======================== */

	/**
	 * Resolves $id / $lang from the request URL.
	 *
	 * The properties above are declared "resolved by caller"; this is the
	 * caller. The work itself lives in Pages\Router\UrlResolver because it is
	 * page-specific (cms_rewritepaths) and the dispatcher needs it too, before
	 * it can decide between site.php and app.php.
	 */
	public function initEditingContext(): void
	{
		if ($this->editingContextParsed) {
			return;
		}
		$this->editingContextParsed = true;
		if (\Systopic\System\Auth\Session::hasRole('pageAdmin')) {
			$this->showTrashed_value = !http::params('showtrashed', TRUE) || http::params('showtrashed', FALSE);
			$this->preview_value = $this->showTrashed_value || !http::params('preview', TRUE) || http::params('preview', FALSE);
			$this->showEditableAreas_value = http::params('showeditableareas');
		}
	}

	/* ======================== HTTP INPUT PARSING ======================== */

	private function parseHttpInput(): self
	{
		if ($this->httpInputParsed) {
			return $this;
		}
		$this->httpInputParsed = true;
		$this->parsePathString(str_replace(http::$root,'/',http::getUrlInfo($this->inputUrl)->path));
		// For PUT requests the action is in the JSON body, not the URL
		if ($this->action_value === '' && http::method() === 'PUT') {
			$this->action_value = (string) (http::put('action') ?? '');
		}
		return $this;
	}

	/* ======================== PATH PARSING UTILITY ======================== */

	/**
	 * Parse a path string into segments, action, modulePath, alias.
	 */
	private function parsePathString(string $pathString): void
	{
		$hasTrailingSlash = $pathString !== '' && str_ends_with(rtrim($pathString), '/');
		$this->rawPath_value = str_replace(http::root(), '/', $pathString);
		if ($pathString === '') {
			return;
		}
		$segments = array_values(array_filter(explode('/', $pathString), static fn($s) => $s !== ''));
		$n = count($segments);
		if ($hasTrailingSlash) {
			$this->modulePath_value = $segments;
			return;
		}
		$this->action_value = $segments[$n - 1];
		if ($n < 2) {
			return;
		}
		$prefix = array_slice($segments, 0, -1);
		$this->modulePath_value = $prefix;
		$lastPref = end($prefix);

		if (ctype_digit((string) $lastPref)) {
			$this->alias_value = (int) $lastPref;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function debugInfo(): array
	{
		return [
			'------------- basic info --------------' => '',
			'dbConnected' => \Systopic\Db\Connection::isOpen(),
			'rawpath' => $this->rawpath,
			'------------- page related --------------' => '',
			'pageResolutionType' => $this->pageResolutionType,
			'preview' => $this->preview,
			'showtrashed' => $this->showtrashed,
			'showeditableareas' => $this->showeditableareas,
			'presetLang' => $this->presetLang,
			'alias' => $this->alias,
			'id' => $this->id,
			'lang' => $this->lang,
			'------------- module related --------------' => '',
			'action' => $this->action,
			'modulePath' => $this->modulePath,
		];
	}
}
