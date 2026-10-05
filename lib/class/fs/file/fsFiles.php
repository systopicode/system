<?php

#[AllowDynamicProperties]
class fsFiles extends fsItems {

	function import($dirOrFiles, $recursive = FALSE) {
		if (is_string($dirOrFiles)) {
			if (substr($dirOrFiles, -1) === '/') {
				$dirOrFiles = fsDir::get($dirOrFiles);
			} else {
				$dirOrFiles = fsFile::get($dirOrFiles);
			}
		}
		$type = obj::getClassOrType($dirOrFiles);
		switch ($type) {
			case 'fsDir' : $this->importDir($dirOrFiles, $recursive);
				break;
			case 'fsFiles' : $this->merge($dirOrFiles);
				break;
			case 'fsFile': $this->add($dirOrFiles);
				break;
		}
		return $this;
	}

	function importDir($dir, $recursive = FALSE) {
		$this->merge($dir->files);
		if ($recursive) {
			foreach ($dir->folders AS $folder) {
				$this->importDir($folder, TRUE);
			}
		}
	}

	function copy() {
		return fsFiles::create()->setItems($this->getItems());
	}
}
