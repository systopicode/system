<?php

#[AllowDynamicProperties]
class httpParam { // mannages updating and override between session and params

	private $parent = [];
	private $children = [];
	private $value;
	private $name;
	
	public function __construct($parent) {
		$this->parent = $parent;
	}

	function getValue() {
		return $this->value;
	}

	function getName() {
		
	}

	function isValue() {
		return count($this->children) === 0;
	}

	function __get($name) {
		if (isset($this->children['name'])) {
			if ($this->children['name']->isValue()) {
				return $this->children['name']->getValue();
			} else {
				return $this->children['name'];
			}
		}
		return NULL;
	}

	public function __set(string $name, mixed $value): void {
		;
	}

}
