<?php

#[AllowDynamicProperties]
class domModel {

	function __construct($commandList = NULL) {
		$this->items = (object) [];
		$this->groups = (object) [];
		$this->hirarchies = (object) [];
		$this->runCommandList($commandList ?: self::$defaultModelCOmmandList);
	}

	function runCommandList($commandList) {
		foreach ($commandList as $commandInfo) {
			foreach ($commandInfo as $command => $selector) {
				$this->runCommand($command, $selector);
			}
		}
	}

	function runCommand($command, $selectorCSV) {
		if (preg_match('~^(item|group|hirarchy):?(.*)$~', $command, $match)) {
			switch ($match[1]) {
				case 'item':
					$this->createItem($selectorCSV);
					return;
				case 'group':
					$groupName = $match[2];
					$this->createGroup($selectorCSV, $groupName);
					return;
				case 'hirarchy':
					$hirarchyName = $match[2];
					$this->createHirachiy($selectorCSV, $hirarchyName);
					return;
			}
		}

		if (preg_match('~^(.*?)\->(.*)$~', $command, $match)) {
			$selector = $match[1];
			$type = $this->getType($selector);
			$action = $match[2];
			switch ("$type:$action") {
				case 'item:append':
					$item = $this->items->$selector;
					$item->append($selectorCSV);
					return;
				case 'group:append':
					$group = $this->groups->$selector;
					$group->append($selectorCSV);
					return;
				case 'hierarchy:append':
					$hierarchy = $this->hirarchies->$selector;
					$hierarchy->append($selectorCSV);
					return;
			}
		}
	}

	function createItem($selector) {
		$tag = $selector;
		if (!isset($this->items->$tag)) {
			$this->items->$tag = new domModelItem($selector, $this);
		}
		return $this->items->$tag;
	}

	function createGroup($selector, $groupName) {
		$tag = $selector;
		if (!isset($this->groups->$groupName)) {
			$this->groups->$groupName = new domModelItemGroup($tag, $this);
		} else {
			// ?add group members
		}
		return $this->groups->$groupName;
	}

	function createHirachiy($selector, $hirarchyName) {
		if (!isset($this->hirarchies->$hirarchyName)) {
			$this->hirarchies->$hirarchyName = new domModelItemHierachy($selector, $this);
		}
		return $this->hirarchies->$hirarchyName;
	}

	function getType($selector) {
		if (isset($this->hirarchies->$selector)) {
			return 'hierarchy';
		}
		if (isset($this->groups->$selector)) {
			return 'group';
		}
		if (isset($this->items->$selector)) {
			return 'item';
		}
	}

	function __toString() {
		return (string) $this->items->root;
	}

	static $defaultModelCOmmandList = [
		['item' => 'root'],
		['item' => 'textNode'],
		['item' => 'a'],
		['item' => 'img'],
		['item' => 'video'],
		['item' => 'iframe'],
		['item' => 'span'],
		['group:textBlocks' => 'p,h1,h2,h3,h4,h5,h6,button,small'],
		['group:textMarkups' => 'i,b,u,s,br,img,video,span,iframe'],
		['hirarchy:table' => 'table>tbody>tr>td'],
		['hirarchy:ul' => 'ul>li'],
		['hirarchy:ol' => 'ol>li'],
		['root->append' => 'textBlocks,table,ul,ol,img,video,span,iframe'],
		['textBlocks->append' => 'textNode,textMarkups,a,img'],
		['a->append' => 'textNode,textMarkups,img'],
		['textMarkups->append' => 'textNode,a,img'],
		['table->append' => 'textBlocks'],
		['ul->append' => 'textNode,textMarkups,textBlocks,ul,ol,img'], // also appends to ol>li because li = li
	];
}

#[AllowDynamicProperties]
class domModelItem {

	static $items;
	public $domModel;
	public $children;
	public $parents;

	function create($selector, $domModel) {
		
	}

	function __construct($selector, $domModel) {
		$this->domModel = $domModel;
		$this->tag = $selector;
		$this->children = (object) [];
		$this->parents = (object) [];
	}

	function append($selectorCSV) {
		$selectorCSV = explode(',', $selectorCSV);
		foreach ($selectorCSV as $selector) { // switch for wat is appended to item
			switch ($this->domModel->getType($selector)) {
				case 'item':
					$item = $this->domModel->items->$selector;
					$this->addChild($item);
					break;
				case 'group':
					$group = $this->domModel->groups->$selector;
					foreach ($group->items as $item) {
						$this->addChild($item);
					}
					break;
				case 'hierarchy':
					$hierarchy = $this->domModel->hirarchies->$selector;
					$this->addChild($hierarchy->root);
					break;
			}
		}
	}

	function addChild($childItem) {
		$this->children->{$childItem->tag} = $childItem;
		$childItem->addParent($this);
	}

	function addParent($item) {
		$this->parents->{$item->tag} = $item;
	}

	function mayContain($tag) {
		return ($this->children && isset($this->children->$tag));
	}

	function __toString() {
		if (count(debug_backtrace()) > 10) {
			if ($this->children) {
				return '.';
			}
			return '';
		}
		$children = '';
		if ($this->children) {
			$children = '<ul>' . implode('', (array) $this->children) . '</ul>';
		}
		return "<li>$this->tag$children</li>";
	}
}

#[AllowDynamicProperties]
class domModelItemGroup {

	function __construct($selectorCSV, $domModel) {
		$this->items = (object) [];
		foreach (explode(',', $selectorCSV) as $selector) {
			$item = $domModel->createItem($selector);
			if (!$this->items) {
				$this->items = (object) [];
			}
			$this->items->{$item->tag} = $item;
		}
	}

	function append($selectorCSV) {
		foreach ($this->items as $item) {
			$item->append($selectorCSV);
		}
	}
}

#[AllowDynamicProperties]
class domModelItemHierachy {

	public $root;
	public $lastChild;

	function __construct($selectorCSV, $domModel) {
		$this->items = (object) [];
		foreach (explode('>', $selectorCSV) as $selector) {
			$tag = $selector;
			$item = $domModel->createItem($tag);
			if (isset($parentItem)) {
				$parentItem->addChild($item);
			}
			$parentItem = $item;
			if (!$this->root) {
				$this->root = $item;
			}
			$this->items->{$item->tag} = $item;
			$this->end = $item;
		}
	}

	function append($selectorCSV) {
		$this->end->append($selectorCSV);
	}
}
