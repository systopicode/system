<?php

trait trait_dynamicProperties {
	/*	 * ******* debug params ********** */

	private $__DP_maxUnresolvedCallsToSameProperty = 10;
	private $__DP_maxLevels = 100;
	private $__DP_maxCalls = 10000;
	private $__DP_maxGets = 10000;

	/*	 * ******* cache ********** */
	public $__DP_dynamicProperties = [];

	// public $dynamicProperties = [];

	/*	 * ******* magics **********
	  write access is disabled by default
	  may be activated in use statement:

	  use trait_dynamicProperties {
	  _set as __set;
	  _unset as __unset;
	  }
	 */

	function __get($name) {
		try {
			return $this->__tryToGet($name);
		} catch (UnhandledMatchError $e) {
			$this->__debugGetterError($name, $e);
		}
	}

	function __tryToGet($name) {
		if (key_exists($name, $this->__DP_dynamicProperties)) {
			if ($this->__DP_calls++ === $this->__DP_maxGets) {
				p("STOP:trait_dynamicProperties +$this->__DP_maxGets calls from cache");
				$this->__getterStackResolve($name, $this->__DP_dynamicProperties[$name]);
				$this->__debugGetterError($name);
			}
			return $this->__DP_dynamicProperties[$name];
		}
		$this->__getterStackAdd($name);
		try {
			$cval = $this->__getPropertyOnce($name);
			$this->__getterStackResolve($name, $cval);
			return $this->__DP_dynamicProperties[$name] = $cval;
		} catch (UnhandledMatchError $e) {
			try {
				$val = $this->__getProperty($name);
				$this->__getterStackResolve($name, $val);
				return $val;
			} catch (UnhandledMatchError $e) {
				if ($this->__DP_level++ === $this->__DP_maxLevels) {
					p("STOP:trait_dynamicProperties +$this->__DP_maxLevels Parent calls");
					$this->__debugGetterError($name);
				}
				// self:: required for climbing hierarchy when trait used in both parent and child
				$fn = '__parentGet';
				return self::$fn($name);
			}
		}
	}

	public function __isset(string $name): bool {
		try {
			$this->__tryToGet($name);
		} catch (UnhandledMatchError $e) {
			$this->__getterStackSetUndefined($name, 'undefined');
			return false;
		}
		return true;
	}

	public function _set(string $name, mixed $value): void {
		$this->__DP_dynamicProperties[$name] = $value;
	}

	public function _unset(string $name): void {
		unset($this->__DP_dynamicProperties[$name]);
	}

	/**
	 * Walk up the class hierarchy to resolve a dynamic property.
	 * Uses Reflection to invoke parent methods with correct class scope,
	 * ensuring proper climbing when trait is used in multiple hierarchy levels.
	 */
	function __parentGet($name) {
		$parentClass = get_parent_class(self::class);
		if ($parentClass) {
			if (method_exists($parentClass, '__tryToGet')) {
				$method = new \ReflectionMethod($parentClass, '__tryToGet');
				return $method->invoke($this, $name);
			}
			if (method_exists($parentClass, '__get')) {
				$method = new \ReflectionMethod($parentClass, '__get');
				return $method->invoke($this, $name); // e.g. shop_orders_module uses dynamicProps but parent has __get defined
			}
		}
		p("look for $name -- {" . get_called_class() . "}->$name is undefined");
		throw new Error("look for $name -- {" . get_called_class() . "}->$name is undefined");
	}

	function __getPropertyOnce($name) {
		$parentClass = get_parent_class(self::class);
		if ($parentClass && method_exists($parentClass, '__getPropertyOnce')) {
			$method = new \ReflectionMethod($parentClass, '__getPropertyOnce');
			return $method->invoke($this, $name);
		}
		throw new UnhandledMatchError();
	}

	function __getProperty($name) {
		$parentClass = get_parent_class(self::class);
		if ($parentClass && method_exists($parentClass, '__getProperty')) {
			$method = new \ReflectionMethod($parentClass, '__getProperty');
			return $method->invoke($this, $name);
		}
		throw new UnhandledMatchError();
	}

	function __propertyExists($name) { // not magic just use instead of property_exists($this,$name)
		return array_key_exists($name, $this->__DP_dynamicProperties);
	}

	// (optional helper from earlier)
	protected function __parentSet(string $name, mixed $value): mixed {
		for ($cls = get_parent_class($this); $cls; $cls = get_parent_class($cls)) {
			if (method_exists($cls, '__set')) {
				$m = new \ReflectionMethod($cls, '__set');
				return $m->invoke($this, $name, $value);
			}
		}
		// default if no parent __set exists:
		return $this->__DP_dynamicProperties[$name] = $value;
	}

	// ******************************************* DEBUG ************************************ //
	// private props may hold multiple values in class and ancestors

	protected $__DP_calls = 0;
	protected $__DP_level = 0;
	protected $__DP_getterStack = [];
	protected $__DP_getterStackResolved = [];
	protected $__DP_getterStackIndices = [];
	protected $__DP_resolveCount = [];

	function __getterStackAdd($name) {
		// p("START", spl_object_id($this), $this->__DP_getterStack)();
		$this->__DP_getterStack[$name] ?? ($this->__DP_getterStack[$name] = ['patternCounts' => []]);
		$callPattern = self::class . "->$name";
		$this->__DP_getterStack[$name]['patternCounts'][$callPattern] ?? ($this->__DP_getterStack[$name]['patternCounts'][$callPattern] = 0);
		$this->__DP_getterStack[$name]['patternCounts'][$callPattern]++;
		if ($this->__DP_getterStack[$name]['patternCounts'][$callPattern] > $this->__DP_maxUnresolvedCallsToSameProperty) {
			p("STOP:trait_dynamicProperties +$this->__DP_maxUnresolvedCallsToSameProperty unresolved recursions of callPattern '$callPattern'");
			$this->__debugGetterError($name);
		}
		// p("BEFORE", spl_object_id($this) . "__getterStackAdd($callPattern)", $this->__DP_getterStack)();
		/* if (array_filter($this->__DP_getterStack, fn($sub) => in_array($callPattern, $sub, true))) {
			p("STOP:trait_dynamicProperties recursion $callPattern already called on spl_id " . spl_object_id($this) . ".");
			$this->__debugGetterError($name);
		} */
		// $this->callPatterns [] = $callPattern;
		// $this->__DP_getterStack[$name]['%' . $this->__DP_getterStackIndices[$name]] = debug_backtrace();
		$this->__DP_getterStackIndices[$name] ??= 1;
		$this->__DP_getterStack[$name]['#' . $this->__DP_getterStackIndices[$name]] = $callPattern;
		$this->__DP_getterStackIndices[$name]++;
		if (array_sum($this->__DP_getterStackIndices) > $this->__DP_maxCalls) {
			p("STOP:trait_dynamicProperties +$this->__DP_maxCalls loops");
			$this->__debugGetterError($name);
		}
		// p("AFTER:", spl_object_id($this), $this->__DP_getterStack)();
	}

	function __getterStackResolve($name, $value) {
		$this->__DP_getterStackResolved[$name] = $this->__DP_getterStack[$name] ?? [];
		// p("RESOLVE", spl_object_id($this), $name)();
		unset($this->__DP_getterStack[$name], $this->__DP_getterStackIndices[$name]);
		$this->__DP_resolveCount[$name] = ($this->__DP_resolveCount[$name] ?? 0) + 1;
		$this->__DP_getterStackResolved[$name]['resolveCount'] = $this->__DP_resolveCount[$name];
		$this->__DP_getterStackResolved[$name]['value'] = $value;
		$this->__DP_level = 0;
		return $value;
	}

	function __getterStackSetUndefined($name) {
		$this->__DP_getterStackResolved[$name . '(undefined)'] = $this->__DP_getterStack[$name] ?? [];
		unset($this->__DP_getterStack[$name], $this->__DP_getterStackIndices[$name]);
		$this->__DP_resolveCount[$name] = ($this->__DP_resolveCount[$name] ?? 0) + 1;
		$this->__DP_getterStackResolved[$name . '(undefined)']['resolveCount'] = $this->__DP_resolveCount[$name];
		$this->__DP_level = 0;
	}

	function __debugGetterError($name, \Throwable|false $error = false) {
		$p = p(get_class($this) . "::__get($name) \n-- dynamicProperties Error Report:\n1. Error Stack \n2. Resolved values in this Object", $this->__DP_getterStack, $this->__DP_getterStackResolved)->depth(10);
		$error && p($error->getMessage()); //->error($error);
		$p->stop();
	}
}
