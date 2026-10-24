<?php

#[AllowDynamicProperties]
class message {

	public static $length = 0;

	public static function noop() {
		return TRUE;
	}

	public static function guide() {
		return new messageGuide();
	}

	public static function notice($notice) {
		return new message($notice, 'notice');
	}

	public static function info($info) {
		return new message($info, 'info');
	}

	public static function confirm($confirm) {
		return new message($confirm, 'confirm');
	}

	public static function question($question) {
		return new message($question, 'question');
	}

	public static function warning($question) {
		return new message($question, 'warning');
	}

	public static function error($error) {
		return new message($error, 'error');
	}

	public static function raw($html) { 
		return new message($html, 'raw');
	}

	public static function empty() {
		return !isset($_SESSION['messages']) || count($_SESSION['messages']) === 0;
	}

	public static function flush($targets = []) { // targets empty => flush all
		if (is_string($targets)) {
			$targets = [$targets];
		}
		if (self::empty()) {
			return '';
		}
		$html = '';
		foreach ($_SESSION['messages'] AS $message) {
			if (count($targets)) {
				if (!$message->target) {
					continue;
				} elseif (!in_array($message->target, $targets)) {
					continue;
				}
			}
			$html .= $message;
		}
		return "<div class='cms message'>$html</div>";
	}

	public $target;
	public $links = [];
	public $buttons = [];

	public function __call($name, $arguments) { // e.g. set Type ->type('confirm');
		$this->$name = $arguments[0];
		return $this;
	}

	public function __toString() {
		unset($_SESSION['messages'][$this->id]);
		if ($this->type === 'raw') {
			return $this->message;
		}
		$links = count($this->links) ? '<p>' . implode('', $this->links) . '</p>' : '';
		$info = DEBUG() ? "<pre><b>caller:</b>$this->callerInfo <br/><b>output:</b>" . debug::getCaller() . "</pre>" : '';
		return "<div class='$this->type'><p class=message>$this->message</p>$links$info</p></div>";
	}

	public function __construct($message = '', $type = 'notice') {
		self::$length++;
		$this->type = $type;
		$this->message = $message;
		if (!isset($_SESSION['messages'])) {
			$_SESSION['messages'] = [];
			$_SESSION['nextMessageId'] = 1;
		}
		$this->id = $_SESSION['nextMessageId'];
		$_SESSION['messages'][$_SESSION['nextMessageId']] = $this;
		$_SESSION['nextMessageId']++;
		$this->callerInfo = debug::getCaller();
	}

	public function action($label, $href) {
		$path = http::$root;
		$link = "<a class='button submit' href='$path$href'>$label</a>";
		$this->links [] = $link;
		return $this;
	}

	public function help($label, $href) {
		$href = (string) $href;
		if (!str_starts_with($href, 'http')) {
			$href = 'https://fluxo-help.1f2c.de/' . ltrim($href, '/');
		}
		$label = htmlspecialchars((string) $label, ENT_QUOTES);
		$href = htmlspecialchars($href, ENT_QUOTES);
		$this->links[] = "<a class='button' href='$href' target='_blank' rel='noopener'>$label</a>";
		return $this;
	}
}
