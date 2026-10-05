<?php

#[AllowDynamicProperties]
class htmlElementSelector {

	static function getElement($selector, $callerClass) {
		$elements = (new self($selector, $callerClass))->getElements();
		$element = array_shift($elements);
		$element->rootSelectors[$selector] = $element; // moved to root on append
		foreach ($elements AS $childElement) {
			$element->append($childElement);
			$element = $childElement;
		}
		return $element;
	}

	public $elements = [];
	public $element;

	function __construct($selector, $callerClass) {
		$this->selector = $selector;
		$this->class = $callerClass;
	}

	function getElements() {
		$this->parse();
		return $this->elements;
	}

	function parse() {
		$mode = 'tag'; // expect string to begin with tag
		$lastmode = 'tag';
		$buf = '';
		$force = FALSE; // force Flush
		foreach (str_split($this->selector) as $chr) {
			switch ($chr) {
				case '.':
					$force = $mode === 'class';
					$mode = 'class';
					break;
				case '#':
					$mode = 'id';
					break;
				case '[':
					$mode = 'attrName';
					break;
				case '=':
					$mode = 'attrValue';
					break;
				case ']':
					$mode = 'closed';
					break;
				case '+': // not implemented yet
					$mode = 'sibling';
					break;
				case ' ':
					$force = $mode === 'tag';
					$mode = 'tag';
					break;
				default:
					$buf .= $chr;
			}
			if (($lastmode !== $mode) || $force) {
				$this->storeBuffer($lastmode, $buf);
				$lastmode = $mode;
				$buf = '';
				$force = FALSE;
			}
		}
		$this->storeBuffer($lastmode, $buf);
		
	}

	function storeBuffer($mode, $buf) {
		switch ($mode) {
			case 'tag':
				$this->element = (new $this->class)->tag($buf);
				// count($this->elements) && end($this->elements)->setParent($this->element);
				return $this->elements[] = $this->element;
			case 'id':
				$this->element->root()->addId("#$buf",$this->element);
				return $this->element->id($buf);
			case 'class':
				$this->element->root()->addClassname(".$buf",$this->element);
                // p("->addClass('$buf');");
				return $this->element->addClass($buf);
			case 'attrName':
				return $this->attrName = $buf;
			case 'attrValue':
				return $this->element->{$this->attrName}($buf);
		}
	}

}
