<?php

#[AllowDynamicProperties]
class objGetter {

	private $obj, $switch, $cashed, $cashe = [];

	function __construct($obj, $name) {
		$this->obj = $obj;
		$this->switch = method_exists($obj, "__$name") ? "__$name" : $name;
	}

	function __get($name) {
		if ($this->cashed) {
			if (!key_exists($name, $this->cache)) {
				$this->$cashe[$name] = $this->obj->{$this->switch}($name);
			}
			return $this->$cashe[$name];
		}
		return $this->obj->{$this->switch}($name);
	}

	function cache_start($names = NULL) {
		$this->cashed = TRUE;
		return $this;
	}
}
