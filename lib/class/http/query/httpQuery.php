<?php

class httpQuery {

	static function create($data = NULL) {
		return new self($data);
	}

	public $data = [];

	public function __construct($data) {
		$this->data = $data;
	}

	public function __toString() {
		return strlen($str = $this->toString()) ? "?$str" : '';
	}

	function toString() {
		$query = '';
		$sep = '';
		foreach ($this->data AS $key => $value) {
			if (is_null($value)) { // remove null val
				continue;
			}
			$query .= $value === '' ? "$sep$key" : "$sep$key=" . urlencode($value);
			$sep = '&';
		}
		return $query;
	}

}
