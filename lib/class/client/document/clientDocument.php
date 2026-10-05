<?php

#[AllowDynamicProperties]
class clientDocument {

// HTML DOM updates
	static function replaceInner($selector, $html) {
		self::addCommand('replaceInner')
				->selector($selector)
				->html($html);
	}

	static function replace($selector, $html) {
		return self::addCommand('replace')
						->selector($selector)
						->html($html);
	}

	static function append($selector, $html) {
		return self::addCommand('append')
						->selector($selector)
						->html($html);
	}

	static function remove($selector) {
		return self::addCommand('remove')
						->selector($selector)
						->html($html);
	}

	static function updateDocument() {
		self::addCommand('updateDocument');
	}

// CSS updates
	static function addClass($selector, $classsName, $timeout = FALSE) {
		return self::addCommand('addClass')
						->selector($selector)
						->className($classsName)
						->timeout($timeout);
	}

	static function removeClass($selector, $classsName, $timeout = FALSE) {
		return self::addCommand('removeClass')
						->selector($selector)
						->className($classsName)
						->timeout($timeout);
	}

// OTHERS	
	static function pushState($url, $query = []) {
		$queryString = http_build_query((array) $query);
		return self::addCommand('pushState')
						->url($url . ($queryString ? "?$queryString" : ''));
	}

	static function contextmenu($html) {
		return self::addCommand('openContextMenu')
						->html($html);
	}

	static function insertHtmlAtCursor($html, $selector) {
		return self::addCommand('insertHtmlAtCursor')
						->selector($selector) //backup if false
						->html($html);
	}

	static function callFunction($functionName, $args = NULL) {
		return self::addCommand('callFunction')
						->functionName($functionName)
						->args($args);
	}

	static function trigger($selector, $trigger) {
		return self::addCommand('trigger')
						->selector($selector)
						->trigger($trigger);
	}

	static function setAttribute($selector, $attribute, $value) {
		return self::addCommand('setAttribute')
						->selector($selector)
						->attr($attribute)
						->val($value);
	}

	static function setValue($selector, $value) {
		return self::addCommand('setValue')
						->selector($selector)
						->value($value);
	}

	static function addCommand($command) {
		$clientCommand = new clientCommand();
		$clientCommand->command = $command;
		client::$response [] = $clientCommand;
		return $clientCommand;
	}

	static function localStorage($selector, $data, $timeout = FALSE) {
		return self::addCommand('localStorage')
						->selector($selector)
						->data(json_encode($data))
						->timeout($timeout);
	}
}
