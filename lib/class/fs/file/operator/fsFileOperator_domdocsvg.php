<?php

class fsFileOperator_domdocsvg extends fsFileOperator {

	static array $capabilities = [
		'read' => ['svg'],
		'write' => ['svg'],
	];

	/** the DOMDocument wrapper - created on first access, dropped by destroy() */
	public ?fsFileOperatorDomdocsvg $domdocsvg {
		get => $this->domdocsvg ??= new fsFileOperatorDomdocsvg($this);
	}

	public mixed $width    { get => $this->domdocsvg->viewbox->width; }
	public mixed $height   { get => $this->domdocsvg->viewbox->height; }
	public mixed $sizeUnit { get => $this->domdocsvg->viewbox->sizeUnit; }

	// svg has no raster resolution
	public mixed $resolutionX { get => NULL; }
	public mixed $resolutionY { get => NULL; }
	public mixed $resolution  { get => NULL; }

	function read() {
		$this->domdocsvg->createDocument($this->file->contents);
		
	}

	function render($format): static {
		switch ($format) {
			case 'svg':
				$this->resultBlob = $this->domdocsvg->toSvg();
				return $this;
		}
		return parent::render($format);
	}

	function destroy(): ?bool {
		// a hooked property cannot be unset - null makes the getter rebuild it
		$this->domdocsvg = null;
		return parent::destroy();
	}

}
