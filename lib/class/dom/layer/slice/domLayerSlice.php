<?php

#[AllowDynamicProperties]
class domLayerSlice {

	static $bufferPos = 0;

	static function create() {
		return new domLayerSlice();
	}

	public $layer;
	public $html = '';

	public function isLayer() {
		return FALSE;
	}

	function close() {
		$this->bufferSize = ob_get_length();
		if (!$this->bufferSize) { // remove empty slices .. 
			array_pop($this->layer->items);
		}
		$this->html = ob_get_clean();
		ob_start();
	}

	function info() {
		$size = isset($this->bufferSize) ? $this->bufferSize : '?';
		$info = "{$this->layer->moduleName} $size Bytes";
		return $info;
	}

	public function __toString() {
		if (TRUE || domRenderer::checkMode('layers')) {
			return $this->html;
			// mode->static rendermode // $layer->type => 'render','session','alias' //
		}
		$indent = $this->layer ? str_repeat("\t>>", $this->layer->level() + 1) : '';
		$openMarker = "\n<!--$indent openSlice {$this->layer->moduleName} -->\n";
		$closeMarker = "\n<!--$indent closeSlice {$this->layer->moduleName} -->\n";
		return $openMarker . $this->html . $closeMarker;
	}


	function debugSlice() {
		$slice = html::create('div.slice')->text(isset($this->bufferSize) ? "$this->bufferSize" : '?');
		$info = htmlTable::create('table.keyvalue')->rows($this->debugInfo())->class('info');
		$slice->append($info);
		return $slice;
	}

	function debugInfo() {
		$maxlen = 300;
		if (strlen($this->html) > $maxlen) {
			return[
				'start' => htmlentities(substr($this->html, 0, $maxlen / 2)),
				'end' => htmlentities(substr($this->html, -$maxlen / 2)),
			];
		}
		return[
			'html' => htmlentities($this->html),
		];
	}

}
