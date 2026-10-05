<?php

class domEditable implements clientSingleton, \Systopic\System\Client\Contracts\PreloadsClientScript {

	public object $params;
	public object $dataset;

	/** the wrapping element, built once from the selector - was __get */
	public mixed $element {
		get => $this->element ??= html::create($this->param('selector'));
	}

	public mixed $type {
		get => $this->dataset->type;
	}

	static function text($selector = 'b') {
		return new domEditable($selector, 'text');
	}

	static function varchar($selector = 'p') {
		return new domEditable($selector, 'varchar');
	}

	static function int($selector = 'b') {
		return new domEditable($selector, 'int');
	}

	static function float($selector = 'b') {
		return new domEditable($selector, 'float');
	}

	function __construct($selector, $type) {
		$this->params = (object) [
					'selector' => $selector,
		];
		$this->dataset = (object) [
					'type' => $type,
		];
	}

	function __call($name, $arguments) {
		switch ($name) {
			case 'value':
				$this->params->$name = reset($arguments);
				return $this;
			case 'doc':
			case 'min':
			case 'max':
			case 'maxlength':
			case 'decimals':
			case 'precision':
			case 'increment':
			case 'onconfirm':
				$this->dataset->$name = reset($arguments);
				return $this;
			default:
				p('unhandled call :' . $name);
				return $this;
		}
	}

	function param($key, $default = NULL) {
		if (property_exists($this->params, $key)) {
			return $this->params->$key;
		}
		if (property_exists($this->dataset, $key)) {
			return $this->dataset->$key;
		}
		return $default;
	}

	function __toString() {
		$value = $this->param('value');
		switch ($this->type) {
			case 'float':
				$round = round($value, $this->param('decimals', 2));
				$prefix = (string) $round === (string) $value ? '' : '&#8764;';
				$this->element->text($prefix . $round);
				if ($prefix . $round !== (string) $value) {
					$this->dataset->value = $value;
				}
				break;
			default:
				$this->element->text($value);
				break;
		}
		$this->element->data($this->dataset);

		return (string) $this->element;
	}

}
