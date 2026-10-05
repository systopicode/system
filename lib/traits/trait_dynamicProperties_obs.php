<?php

trait trait_dynamicProperties_obs {

	public $dynamicProperties = [];

	function __get($name) {
		if (key_exists($name, $this->dynamicProperties)) {
			if ($this->calls++ === $this->maxGets_obs) {
				p("STOP:trait_dynamicProperties +$this->maxGets_obs calls from cache");
				self::__getterStackResolve($name, $this->dynamicProperties[$name]);
				self::__debugGetterError($name);
			}
			return $this->dynamicProperties[$name];
		}
		self::__getterStackAdd($name);
		try {
			$cval = self::__getPropertyOnce($name);
			self::__getterStackResolve($name, $cval);
			return $this->dynamicProperties[$name] = $cval;
		} catch (UnhandledMatchError) {
			try {
				$val = self::__getProperty($name);
				self::__getterStackResolve($name, $val);
				return $val;
			} catch (UnhandledMatchError) {
				return self::__parentGet($name); 
			}
		} catch (Throwable $e) {
			self::__debugGetterError($name, $e);
		}
	}

	function __parentGet($name) {
		p(self::class,$name,get_parent_class(self::class));
		if (get_parent_class(self::class)) {
			return parent::__get($name);
		}
		p("look for $name -- {" . get_called_class() . "}->$name is undefined");
		throw new Error("look for $name -- {" . get_called_class() . "}->$name is undefined");
	}

	function __getPropertyOnce($name) {
		return match ($name) { // throws UnhandledMatchError
		};
	}

	function __getProperty($name) {
		return match ($name) { // throws UnhandledMatchError
		};
	}

	function __propertyExists($name) { // not magic just use instead of property_exists($this,$name)
		return array_key_exists($name, $this->dynamicProperties);
	}

	public function __set(string $name, mixed $value): void {
		// Optional: track/debug writes similar to your getter stack if you like
		// $this->__getterStackAdd("__set:$name");
		// Default behavior: store into the trait’s dynamic cache
		// maybe add __val to explicitly set bypassing recursion protection
		// $keyTrimmed = str_starts_with($key, '__') ? substr($key, 2) : $key;
		$this->dynamicProperties[$name] = $value;

		// Optional: resolve/debug
		// $this->__getterStackResolve("__set:$name", $value);
	}

	public function __unset(string $name): void {
		unset($this->dynamicProperties[$name]);
	}

	public function __isset(string $name): bool {
		return array_key_exists($name, $this->dynamicProperties);
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
		return $this->dynamicProperties[$name] = $value;
	}

	// ******************************************* DEBUG ************************************ //
	protected $getterStack = [];
	protected $getterStackResolved = [];
	protected $getterStackIndex = 1;
	private $calls = 0;
	protected $maxGets_obs = 1000000;
	protected $maxCalls_obs = 100000;

	function __getterStackAdd($name) {
		$this->getterStack[$name] ?? ($this->getterStack[$name] = []);
		$this->getterStack[$name]['#' . $this->getterStackIndex++] = self::class . "->$name";
		if ($this->getterStackIndex > $this->maxCalls_obs) {
			p("STOP:trait_dynamicProperties +$this->maxCalls_obs loops");
			self::__debugGetterError($name);
		}
	}

	function __getterStackResolve($name, $value) {
		$this->getterStackResolved[$name] = $this->getterStack[$name] ?? [];
		unset($this->getterStack[$name]);
		$this->getterStackResolved[$name][] = $value;
		return $value;
	}

	function __debugGetterError($name, $error = FALSE) {
		$p = p(get_class($this) . "::__get($name)", $this->getterStack, $this->getterStackResolved);
		$error && $p($error->getMessage()); //->error($error);
		$p->stop();
	}
}
