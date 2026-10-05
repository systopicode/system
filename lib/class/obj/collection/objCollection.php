<?php

class objCollection implements Iterator, Countable, ArrayAccess {

	static function create($ref = NULL) {
		return new static($ref);
	}

// ******************* methods **********************
	function length() {
		return count($this);
	}

	function empty() {
		return count($this) === 0;
	}

	function reset() {
		$this->rewind();
		return $this->current();
	}

	function unique() {
		$this->items = array_unique($this->items, SORT_REGULAR);
		return $this;
	}

	function forEach($callback) {
		foreach ($this->items AS $key => $item) {
			call_user_func($callback, $item, $key);
		}
	}

	function reduce($callback, $carry) {
		foreach ($this->items AS $item) {
			$callback($carry, $item);
		}
		return $carry;
	}

	function add(...$args) {
		count($args) === 2 ? $this->items[$args[1]] = $args[0] : $this->items[] = $args[0];
		return $this;
	}

	function join($iterable) {
		foreach ($iterable AS $value) {
			$this->add($value);
		}
	}

	function merge($objCollection) {
		$this->items = array_merge($this->items, $objCollection->array());
	}

	function call($callback) {
		$callback($this);
		return $this;
	}

	function getKey($item) {
		$pos = array_search($item, $this->items, TRUE);
		return $pos === FALSE ? FALSE : $this->keys[$pos];
	}

	function hasKey($key) {
		return $this->offsetExists($key);
	}

	function array() {
		return $this->items;
	}

	function object() {
		return (object) $this->array();
	}

	function keys() {
		return array_keys($this->items);
	}

	function values() {
		return array_values($this->items);
	}

	function sort($callback = NULL) {
		uasort($this->items, $callback ?? function ($a, $b) {
					return $a > $b ? 1 : ( $a === $b ? 0 : -1); // default sorting
				});
	}

// ******************* overloading Object Access **********************
	private $items = []; // collection of items  - alphanumeric keys
	private $ref; // may be passed on create e.g. parent

	function ref() {
		return $ref;
	}

	function __construct($ref = NULL) {
		$this->ref = $ref;
	}

	function __get($key) {
		return $this->offsetGet($key);
	}

	function __set($key, $item) {
		return $this->offsetSet($key, $item);
	}

	function __unset($key) {
		$this->offsetUnset($key);
	}

	function __isset($key) {
		return $this->hasKey($key);
	}

	private $properties = []; // storage for setter/getter methods

	function __call($name, $arguments) {
		// automatic setter / getter for collection props
		// $obj->setPrintMode('bucket') -> $obj->getPrintMode
		if (strpos($name, 'get') === 0) {
			$prop = lcfirst(substr($name, 3));
			return $this->properties[$prop];
		}
		if (strpos($name, 'set') === 0) {
			$prop = lcfirst(substr($name, 3));
			$this->properties[$prop] = reset($arguments);
			return $this;
		}
		d("no function here $name");
		//call_user_func_array($name, $arguments); // just for error message
	}

	function toString($collection = NULL) {
		$items = [];

		foreach ($this->items AS $item) {
			if (method_exists($item, 'toString')) {
				// container collection druchreichen für 'rückfragen'
				// p('objCollection->toString() - key:' . $this->keys[$pos]);
				$items[] = $item->toString($this);
			} else {
				p('toString add default');
				$items[] = $item;
			}
		}
		return implode($items);
	}

	function __toString() {
		return $this->toString();
	}

// ******************* Iterator Methods **********************
	#[\Override]
		function rewind(): void {
		reset($this->items);
	}

	#[\Override]
		function current(): mixed {
		return current($this->items);
	}

	#[\Override]
		function key(): mixed {
		return key($this->items);
	}

	#[\Override]
		function next(): void {
		next($this->items);
	}

	#[\Override]
		function valid(): bool {
		return !is_null(key($this->items));
	}

// ******************* countable methods **********************
	#[\Override]
		function count(): int {
		return count($this->items);
	}

// ******************* ArrayAccess methods **********************
	#[\Override]
		function offsetSet($key, $item): void {
		if (is_null($key)) {
			// p('---countBefore', count($this->items));
			// p('add', $key, $item);
			$this->items[] = $item;
			// p('+++countAfter', count($this->items));
		} else {
			$this->items[$key] = $item;
		}
	}

	#[\Override]
		function offsetExists($key): bool {
		return array_key_exists($key, $this->items);
	}

	#[\Override]
		function offsetUnset($key): void {
		unset($this->items[$key]);
	}

	#[\Override]
		function offsetGet($key): mixed {
		if (!key_exists($key, $this->items)) {
			d("not found '$key'");
		}
		return $this->items[$key];
	}
}
