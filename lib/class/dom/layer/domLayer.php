<?php

#[AllowDynamicProperties]
class domLayer {

	static $stack = [];
	static $instances = [];
	static $referenceStack;
	static $referencesByModuleAddress;
	static $lastClosed;

	// ************* STATIC ******************** //

	static function referenceRoot() { // dom structure from session
		if (is_null(self::$referenceStack)) {
			self::$referenceStack = [];
			if (isset($_SESSION['referenceDomLayers'])) {
				self::$referenceStack [] = $_SESSION['referenceDomLayers'];
			}
		}
		return reset(self::$referenceStack);
	}

	static function getReference($module, $viewName) {
		$moduleAddress = $module->getAddress();
		if (is_null(self::$referencesByModuleAddress)) {
			if (isset($_SESSION['referencesByModuleAddress'])) {
				self::$referencesByModuleAddress = $_SESSION['referencesByModuleAddress'];
			} else {
				self::$referencesByModuleAddress = [];
			}
		}
		if (isset(self::$referencesByModuleAddress[$moduleAddress][$viewName])) {
			return self::$referencesByModuleAddress[$moduleAddress][$viewName];
		}
		return FALSE;
	}

	static function root() { // dom structure just built
		return reset(self::$stack);
	}

	static function active() {
		return end(self::$stack);
	}

	static function addSlice() {
		$slice = domLayerSlice::create();
		self::active()->add($slice);
	}

	static function create($method, $args) {
		$layer = new domLayer();
		$layer->method = $method;
		switch ($method) { // assign caller args
			case 'openChild':
				$layer->requestedModuleName = $args[0];
				$layer->viewName = $args[1];
				$layer->callback = $args[2] ?? NULL;
				break;
			default:
				$layer->viewName = $args[0];
				$layer->callback = $args[1] ?? NULL;
				break;
		}
		$layer->renderMode = domRenderer::mode();
		DEBUG() && $layer->createTime = date::getUsecTimestamp();
		// p($layer->renderMode);
		$layer->callerModule = \Systopic\System\Panels\PanelNode::getNavigator()?->getActive();
		$layer->callerModuleName = \Systopic\System\Panels\PanelNode::getNavigator()?->getActive()->name; // ->sleep
		$layer->callerModulePath = \Systopic\System\Panels\PanelNode::getNavigator()?->getActive()->path; // ->sleep
		$layer->cwdOnCreate = fs::cwd(); // ->debug
		if (self::active()) { // get buffer before opening sub layer
			$layer->parent = self::active();
			self::active()->closeLastItem();
			self::active()->add($layer);
		} else {
			// p('CREATE root INC');
		}
		self::$stack [] = $layer;
		if (count(self::$stack) > 100) {
			t('max nesting of dom layer exceeded (100). dumping layer');
			d($layer);
		}

		return $layer;
	}

	static function close() {
		$layer = array_pop(self::$stack);
		$layer->closeLastItem();
		if (self::active()) {
			self::addSlice();
		}
		DEBUG() && $layer->closeTime = date::getUsecTimestamp();
	}

	static function closeLast() {
		$close = reset(self::$stack); // root stays in stack
		$close->closeLastItem();
	}

	// ************* INSTANCES ******************** //

	public $parent;
	public $type = 'original'; // reference, alias
	public $items = []; // array of slices and child layers
	public $activeSublayerIndex; // pointer for diff renderings
	public $callerModule;
	public $callerModuleName = '[]';
	public $requestedModuleName = '[]';
	public $module;
	public $callback;
	public $result;
	public $moduleName = '[]';
	public $modulePath = [];
	public $loopMode;
	public $createTime;

	function isLayer() {
		return TRUE;
	}

	function name() {
		return "$this->method>$this->moduleName:$this->viewName";
	}

	function address() {
		if ($this->parent) {
			return array_merge($this->parent->address(), [$this->name()]);
		} else {
			return [$this->name()];
		}
	}

	function level() {
		if ($this->parent) {
			return $this->parent->level() + 1;
		} else {
			return 0;
		}
	}

	function setTypeRec($type) {
		$this->type = $type;
		foreach ($this->items AS $item) {
			if ($item->isLayer()) {
				$item->setTypeRec($type);
			}
		}
	}

	function add($item) {
		if (!$this->module) {
			$this->result = TRUE;
			$this->module = \Systopic\System\Panels\PanelNode::getNavigator()?->getActive();
			$this->addModuleReference();
			$this->moduleName = $this->module->name; // ->sleep
			$this->modulePath = $this->module->path; // ->sleep
		}
		$this->items [] = $item;
		$item->layer = $this;
		// p('add: ' . $item->info . $item->info());
	}

	function addModuleReference() {
		$address = \Systopic\System\Panels\PanelNode::getNavigator()?->getActive()->getAddress();
		if (!isset(self::$referencesByModuleAddress[$address])) {
			self::$referencesByModuleAddress[$address] = [];
		}
		self::$referencesByModuleAddress[$address][$this->viewName] = $this;
	}

	function closeLastItem() {
		if (count($this->items)) {
			$last = end($this->items);
			if (get_class($last) === 'domLayerSlice') {
				$last->close();
			}
		} else {
			// p("LAYER::empty -- " . $this->info());
		}
	}

	// ************* LAYER RENDERER ******************** 
	public $reference; // link to reference Layer in 

	function getNextSublayer() { // reference called ...
		$itemCount = count($this->items);
		if (is_null($this->activeSublayerIndex)) { // init or break
			if ($itemCount) {
				$this->activeSublayerIndex = 0;
			} else {
				return FALSE;
			}
		}
		for ($i = $this->activeSublayerIndex; $i < $itemCount; $i++) { // walk on
			if ($this->items[$i]->isLayer()) {
				$this->activeSublayerIndex = $i + 1;
				// p(1 + $i . ' of ' . $itemCount);
				// p($this->items[$i]);
				return $this->items[$i];
			}
		}
		return FALSE;
	}

	function checkUpdate() {
		if ($this->type === 'original') {
			return TRUE;
		}
		$module = $this->getModuleForUpdateCheck();
		if ($module && in_array($this->viewName, $module->viewsToBeUpdated)) {
			return TRUE;
		}
		return FALSE;
	}

	/** Modul für Update-Check: nach Session-Restore ist ->module nicht gesetzt, dann per modulePath auflösen */
	function getModuleForUpdateCheck() {
		if (isset($this->module) && is_object($this->module)) {
			return $this->module;
		}
		$path = $this->modulePath ?? [];
		if (!is_array($path) || empty($path)) {
			return null;
		}
		if (!(\Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRoot() !== null)) {
			return null;
		}
		$m = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRoot();
		$skip = count($m->path ?? []);
		for ($i = $skip; $i < count($path); $i++) {
			if (!isset($m->childrenByName[$path[$i]])) {
				return null;
			}
			$m = $m->childrenByName[$path[$i]];
		}
		return $m;
	}

	function templateExists() {
		return fs::isFile("$this->viewName.tpl.php");
	}

	function getTemplate() {
		return "$this->viewName.tpl.php";
	}

	function __toString() { // join structured html output
		$indent = str_repeat("\t>>", $this->level());
		$openMarker = "\n<!--$indent openLayer $this->moduleName::$this->viewName-->";
		$closeMarker = "\n<!--$indent closeLayer $this->moduleName::$this->viewName-->";
		return $openMarker . implode('', $this->items) . $closeMarker;
	}

	// ************* SESSION ********************

	function __sleep() { // remove module references before storing in session
		$vars = get_object_vars($this);
		unset($vars['module']);
		unset($vars['callerModule']);
		unset($vars['activeSublayerIndex']);
		return array_keys($vars);
	}

	function export() {
		$slices = [];
		$layers = [];
		foreach ($this->items AS $item) {
			if ($item->isLayer()) {
				$layers [] = $item->export();
				$slices [] = "\n<div class=domLayerPlaceholder></div>\n";
			} else {
				$slices [] = $item;
			}
		}
		return (object) [
				'data' => (object) [
					'method' => $this->method,
					'result' => $this->result,
					'moduleName' => $this->moduleName,
					'modulePath' => implode('.', $this->modulePath),
					'viewName' => $this->viewName,
					'callback' => $this->callback,
					'type' => $this->type,
				],
				'html' => $this->result ? implode('', $slices) : '',
				'childLayers' => $layers,
		];
	}

	// ************* DEBUG OUTPUT ********************

	function debugInfo() {
		$closeTIme = $this->closeTime ?? date::getUsecTimestamp(); // body layer not yet closed when printed
		return [
			'caller' => $this->callerModuleName ?: 'unknown',
			'callerPath' => implode('/', $this->callerModulePath) . '/',
			'uptateCallerPath' => $this->module->updateViewsCallerInfo[$this->viewName] ?? '-',
			'method' => $this->method,
			'view' => $this->viewName,
			'callback' => $this->callback,
			'result' => $this->result ? 'TRUE' : 'FALSE',
			'-------------' => '--------------',
			'cwd on open' => $this->cwdOnCreate,
			'module' => $this->moduleName,
			'modulePath' => implode('/', $this->modulePath) . '/',
			'address' => implode('/', $this->address()) . '/',
			'ref' => $this->reference ? implode('/', $this->reference->address()) . '/' : '[]',
			'rendermode' => $this->renderMode,
			'type' => $this->type,
			'moduleRequested' => $this->requestedModuleName,
			'time' => round($closeTIme - $this->createTime, 3),
		];
	}

}
