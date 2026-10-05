<?php

trait trait_clientData {

	static $clientData = [
		'ids' => [],
	];
	static $clientDataCallbacks = [];

	function registerUpdateClientData($func) {
		self::$clientDataCallbacks[] = $func;
	}

	function updateClientData() {
		self::$clientData['ids'][$this->index()] = $this->exportRecord();
	}

	static function clientData_export() {
		foreach (self::$clientDataCallbacks as $func) {
			$func();
		}
		// d(self::$clientData,10);
		return (object) [
				'route' => self::$clientDataRoute,
				'data' => self::$clientData,
		];
	}

}
