<?php

#[AllowDynamicProperties]
class htmlTree extends htmlElement {

	static function create($type = NULL) {
		if ($type) {
			return htmlElementSelector::getElement("ul.$type", __CLASS__);
		}
		return htmlElementSelector::getElement("ul", __CLASS__);
	}

	function json($json) {
		$decode = json_decode($json);
		switch (gettype($decode)) {
			case 'array':
				$this->array($decode);
				return $this;
			case 'object':
				$this->object($decode);
				return $this;
		}
	}

	function array($values) {
		$this->class('array');
		foreach ($values AS $value) {
			$this->processValue($value);
		}
		return $this;
	}

	function object($object) {
		$this->class('object');
		if ($object === null) {
			return $this;
		}
		foreach ($object AS $key => $value) {
			$this->processValue($value, $key);
		}
		return $this;
	}

	function processValue($value, $key = '') {
		$type = obj::getClassOrType($value);
		$li = $this->append('li');
		$li->append('a')->text("$key => ($type) ");
		switch ($type) {
			case 'stdClass':
				$li->append(htmlTree::create()->object($value));
				return $this;
			case 'array':
				$li->append(htmlTree::create()->array($value));
				return $this;
			case 'null':
				$li->append('b')->text('NULL');
				return $this;
			case'boolean':
				$li->append('b')->text($value ? 'TRUE' : 'FALSE');
				return $this;
			default:
				$li->append('b')->text("$value");
				return $this;
		}
	}

}
