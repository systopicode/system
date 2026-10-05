<?php

#[AllowDynamicProperties]
class menu extends htmlElement implements clientClass, \Systopic\System\Client\Contracts\PreloadsClientScript {

	static function create($selectorOrType = 'menu') {
		if (in_array($selectorOrType, ['menu', 'contextmenu', 'barmenu'])) {
			return htmlElementSelector::getElement("ul.$selectorOrType", __CLASS__)
					// avoid trigger parent events - e.g. toggle menubar
					->attr('onclick', 'event.stopPropagation()')
			;
		} else {
			return htmlElementSelector::getElement("$selectorOrType", __CLASS__)
					// avoid trigger parent events - e.g. toggle menubar
					->attr('onclick', 'event.stopPropagation()')

			;
		}
	}

	public $menuType;
	public $activeItem;
	public $activeMenu;

	function __call($name, $args) {
		$arg = reset($args);
		if (in_array($name, ['if'])) {
			$this->skipNext = FALSE;
		}
		if ($this->skipNext) {
			// $this->skipNext = FALSE;
			$this->skipNext = in_array($name, ['link', 'label']);
			// keep skip for item substructure

			return $this;
		}
		$target = $this->activeItem ?: $this;
		switch ($name) {
			case 'openSubmenu':
				if (is_string($arg)) { // label passed
					$this->item('toggleMenu')->label($arg);
				}
				$menu = menu::create('ul.open');
				$this->activeItem->append($menu);
				if (is_iterable($arg)) { // key->value options passed
					$action = $this->activeItem->attributes['data-on_click'];
					$menu->appendOptions($arg, $action);
				}
				return $menu;
			case 'closeSubmenu':
				return $this->parent->parent;
			case 'action':
			case 'item':
				$item = menu::create('li');
				$item->append('a')->click($arg);
				$this->activeItem = $item;
				$this->append($item);
				return $this;
			case 'external':
				$item = menu::create('li');
				$item->append('a')->href($arg)->target('_blank');
				$this->activeItem = $item;
				$this->append($item);
				return $this;
			case 'link':
				$item = menu::create('li');
				$item->append('a')->href($arg);
				$this->activeItem = $item;
				$this->append($item);
				return $this;
			case 'download':
				$a = $this->activeItem->getAnchor();
				$a->emptyAttributes[] = 'download';
				$a->removeClass('ajax');
				return $this;
			case 'class':
				$target->addClass($arg);
				return $this;
			case 'title':
				$target->attributes[$name] = $arg;
				return $this;
			case 'selected':
				if ($arg) {
					$target->data(['selected' => TRUE]);
				}
				return $this;
			case 'input':
				$item = menu::create('li')->click('inputPrompt')->data([
				    'action' => $arg
				]);
				$this->activeItem = $item;
				$this->append($item);
				return $this;
			case 'label':
				$label = htmlElement::create('span')->text($arg);
				if (count($target->children) && reset($target->children)->tag === 'a') {
					reset($target->children)->append($label);
				} else {
					$target->append($label);
				}
				return $this;
			case 'data':
				foreach ($arg AS $attr => $value) {
					$target->attributes["data-$attr"] = $value;
				}
				return $this;
			case 'action':
				$this->data([$name => $arg]);
				return $this;
			case 'color':
				$label = htmlElement::create('input')->value($arg)->spellcheck('FALSE');
				if (isset($this->activeItem->attributes['data-on_click'])) {
					$label->input($this->activeItem->attributes['data-on_click']);
					// unset($this->activeItem->attributes['data-on_click']);
				}
				$this->activeItem->append($label);
				return $this;
			case 'icon':
				$icon = htmlElement::create('i')->class($arg);
				$target->appendToItemOrAnchor($icon);
				return $this;
			case 'reverse':
				$this->children = array_reverse($this->children);
				return $this;
			case 'insert':
				$this->activeItem->append($arg);
				return $this;
			case 'spacer':
				$item = html::create('li.spacer');
				$this->activeItem = $item;
				$this->append($item);
				return $this;
		}
		return parent::__call($name, $args);
	}

	function getAnchor() {
		foreach ($this->children AS $child) {
			if ($child->tag === 'a') {
				return $child;
			}
		}
	}

	function appendToItemOrAnchor($appendMe) {
		if (count($this->children) && reset($this->children)->tag === 'a') {
			reset($this->children)->append($appendMe);
		} else {
			$this->append($appendMe);
		}
		return $this;
	}

	function appendOptions($options, $action) {
		foreach ($options AS $value => $label) {
			$this->append('li')->click($action)->data(['value' => $value])->text($label);
		}
	}
}
