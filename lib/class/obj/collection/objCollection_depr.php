<?php

#[AllowDynamicProperties]
class objCollection_depr implements Iterator, Countable, ArrayAccess {

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

	function forEach($callback, $object = NULL) {
		foreach ($this->items AS $index => $item) {
			if ($object) {
				$object->$callback($item, $this->keys[$index]);
			} else {
				call_user_func($callback, $item, $this->keys[$index]);
			}
		}
	}

	function reduce($callback, $carry) {
		foreach ($this->items AS $item) {
			$callback($carry, $item);
		}
		return $carry;
	}

	function add($item, $key = NULL) {
		$this->items[] = $item;
		$this->keys[] = $key;
		return $this;
	}

	function join($iterable) {
		foreach ($iterable AS $value) {
			$this->add($value);
		}
	}
	function merge($iterable) {
		foreach ($iterable AS $key => $value) {
			$this->add($value,$key);
		}
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
		return in_array($key, $this->keys);
	}

	function array() {
		return array_combine($this->keys, $this->items);
	}

	function object() {
		return (object) $this->array();
	}

	function keys() {
		return $this->keys;
	}

	function values() {
		return $this->items;
	}

	function sort($callback = NULL) {
		if ($callback) {
			p($this->keys);
			$array = $this->array(); // combine keys => values
			uasort($array, $callback);
			$this->keys = array_keys($array);
			$this->values = array_values($array);
		} else {
			array_multisort($this->keys, $this->items, SORT_NATURAL);
		}
	}

// ******************* overloading Object Access **********************
	private $items = []; // collection of items  - array keys numbered
	private $keys = []; // alphanumeric keys  - array keys numbered
	private $properties = []; // storage for setter methods
	private $position = 0;
	private $ref; // may be passed on create e.g. parent

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

		foreach ($this->items AS $pos => $item) {
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
	#[\ReturnTypeWillChange]
	public
		function rewind() {
		$this->position = 0;
	}

	#[\ReturnTypeWillChange]
	public
		function current() {
		return $this->items[$this->position];
	}

	#[\ReturnTypeWillChange]
	public
		function key() {
		return $this->keys[$this->position];
	}

	#[\ReturnTypeWillChange]
	public
		function next() {
		++$this->position;
	}

	#[\ReturnTypeWillChange]
	public
		function valid() {
		return $this->position < count($this->keys);
	}

// ******************* countable methods **********************
	#[\ReturnTypeWillChange]
	public
		function count() {
		return count($this->items);
	}

// ******************* ArrayAccess methods **********************
	#[\ReturnTypeWillChange]
	public
		function offsetSet($key, $item) {
		if (is_null($key)) { // $col[]=$item;
			$this->items[] = $item;
			$this->keys[] = array_key_last($this->items);
			return $item;
		}
		$pos = array_search($key, $this->keys, TRUE);
		if ($pos === FALSE) { // new
			$this->keys[] = $key;
			$this->items[] = $item;
		} else { // overwrite
			$this->items[$pos] = $item;
		}
		if (is_object($item) && method_exists($item, 'isItemOf')) {
			$item->isItemOf($this);
		}
		return $item;
	}

	#[\ReturnTypeWillChange]
	public
		function offsetExists($key) {
		$pos = array_search($key, $this->keys, TRUE);
		return isset($this->items[$pos]);
	}

	#[\ReturnTypeWillChange]
	public
		function offsetUnset($key) { // untested
		$pos = array_search($key, $this->keys, TRUE);
		if ($pos !== FALSE) {
			array_splice($this->items, $pos, 1);
			array_splice($this->keys, $pos, 1);
		} else {
			d("unset undefined Index '$key' in '" . static::class . "' - keys: " . implode(',', $this->keys));
		}
	}

	#[\ReturnTypeWillChange]
	public
		function offsetGet($key) {
		$pos = array_search($key, $this->keys, TRUE);
		if ($pos !== FALSE) {
			return $this->items[$pos];
		}
		p("undefined Index $key in " . static::class . " dumping key->value array()");
		d($this->array());
	}

}
