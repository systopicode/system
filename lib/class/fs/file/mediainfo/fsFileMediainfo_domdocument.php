<?php

class fsFileMediainfo_domdocument extends fsFileMediainfo {

	#[\Override]
			function readMediainfo(): array {
		$svg = $this->file->getOperator('domdocsvg');
		return [
			'width' => $svg->width,
			'height' => $svg->height,
			'sizeUnit' => $svg->sizeUnit ?: 'px',
			'resolutionX' => NULL,
			'resolutionY' => NULL,
			'resolutionUnit' => NULL,
		];
	}

	#[\Override]
			function getDimensionsInfo() {
		return 'not Available';
	}

	#[\Override]
			function getResolutionInfo() {
		return 'not Available';
	}
}
