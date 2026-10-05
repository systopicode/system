<?php

#[AllowDynamicProperties]
class clientData_nodes {

	// a collection of all dbQuery instances that send data to the client
	// managing the field filtering
	function __construct($className) {
		$this->className = $className;
		$this->nodes = [];
	}

}
