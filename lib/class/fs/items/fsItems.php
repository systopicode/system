<?php

class fsItems extends objCollection {

	// collection class for files and/or folders
	function filter($pattern) {
		$filter = new static;
		foreach ($this AS $file) {
			if (preg_match($pattern, $file->name)) {
				$filter->add($file);
			}
		}
		return $filter;
	}
}
