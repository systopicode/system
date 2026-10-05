<?php

#[AllowDynamicProperties]
class html {
	/*	 * ************************************** htmlElement shorthand **************************************** */

	static function create($selector = NULL, $constructorClass = 'htmlElement') {
		return htmlElement::create($selector, $constructorClass);
	}

	static function __callStatic($name, $args) {
		switch ($name) {
			case 'nodelist':
				return htmlElements::create();
		}

		if (in_array($name, htmlReference::$tags) || in_array($name, htmlReference::$selfclosingTags)) {
			return htmlElement::create()->tag($name)->text($args[0] ?? '');
		}

		d(static::class . "::$name() undefined");
	}

	/*	 * ************************************** STATIC LIB **************************************** */

	static function arrayToDataAttr($data) {
		$dataAttrs = '';
		array_walk($data, function ($value, $key) use (&$dataAttrs) {
			if (!is_null($value)) {
				$dataAttrs .= " data-$key='$value'";
			}
		});
		return $dataAttrs;
	}

	static function arrayToDataAttrs($data) {
		return self::arrayToDataAttr($data);
	}

	static function arrayToStyleAttr($css, $noAttr = FALSE) {
		if (!is_array($css) || !count($css)) {
			return '';
		}
		$styleAttrs = $noAttr ? '' : ' style="';
		array_walk($css, function ($value, $key) use (&$styleAttrs) {
			$styleAttrs .= "$key:$value;";
		});
		$styleAttrs .= $noAttr ? '' : '"';
		return $styleAttrs;
	}

	static function arrayToClassAttr($data) {
		$classNames = array();
		array_walk($data, function ($value, $key) use (&$classNames) {
			if (is_numeric($key)) { // classnames in values
				$classNames [] = $value;
			} elseif ($value) {  // classnames in keys
				$classNames [] = $key;
			}
		});
		return empty($classNames) ? '' : " class='" . implode(' ', $classNames) . "'";
	}

	static function getTextContentFromHTML($html, $classSelector = false) {
		$fieldTextContent = '';
		libxml_use_internal_errors(true);
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
		$body = $doc->getElementsByTagName("body")->item(0);
		if (gettype($body) !== 'object') {
			$fieldTextContent = "DOMDocument parse error in getTextContentFromHTML()";
		} else {
			$finder = new DomXPath($doc); // only select items dedicated for indexing by classname 
			if ($classSelector) {
				$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classSelector ')]");
			} else {
				$nodes = array($body);
			}
			$thisFieldTextContent = ''; // duplikate ignorieren
			foreach ($nodes as $item) {
				$fieldTextContent .= self::getTextContentFromNode($item, !$classSelector);
			}
			$fieldTextContent = preg_replace(array(
				"/[\s\t\r]+/", // belibige kombinationen aus umbruch und leerzeichen ...
					), array(
				" ", // ... immer zu genau einem leerzeichen wandeln
					), $fieldTextContent);
		}
		return trim($fieldTextContent);
	}

	static function getTextContentFromNode($node, $HTMLlinebreaks = true) {
		if ($node->nodeName == 'br' && $HTMLlinebreaks) {
			return '<br/> ';
		}
		if ($node->nodeType == XML_TEXT_NODE) {
			return $node->nodeValue;
		} else {
			$text = '';
			foreach ($node->childNodes as $childNode) {
				$text .= self::getTextContentFromNode($childNode, $HTMLlinebreaks);
			}
			return $text . " ";
		}
	}

}
