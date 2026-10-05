<?php

class date extends DateTime {

	public $string;

	static function create($string = NULL, $timezone = NULL) {
		return new date($string, $timezone);
	}

	static function now($string = "now", $timezone = NULL) {
		return new date($string, $timezone);
	}

	static function getUsecTimestamp() {
		list($usec, $sec) = explode(" ", microtime());
		return (float) $usec + (float) $sec;
	}

	// ***********************************************

	function __construct($string) {
		if ($string) { // empty string or false etc => null;
			$this->string = $string;
		} else {
			$string = '';
		}
		parent::__construct($string);
	}

	/** read-only alias of getTimestamp() */
	public int $timestamp {
		get => $this->getTimestamp();
	}

	function valid() {
		return !is_null($this->string);
	}

	function isset() {
		return !is_null($this->string);
	}

	function mySQL() {
		return $this->print('Y-m-d H:i:s');
	}

	function print($format = 'd.m.Y H:i:s') {
		if ($this->valid()) {
			return parent::format($format);
		} else {
			return 'unbekannt';
		}
	}

	/**
	 * Elapsed time since a timestamp, short form: '3 days 20 hrs',
	 * '23 hrs 43 min', '6 min 10 sec'. Two units at most, and the smaller one
	 * is dropped as soon as the bigger one carries the information - matches
	 * what the sync tool has printed for years.
	 *
	 * Returns '' when there is no timestamp; the caller decides its placeholder.
	 */
	static function elapsed($timestamp, $now = NULL) {
		if (!$timestamp) {
			return '';
		}
		$elapsed = max(0, (int) round(($now ?? time()) - (float) $timestamp));
		$seconds = $elapsed % 60;
		$minutes = intdiv($elapsed, 60) % 60;
		$hours   = intdiv($elapsed, 3600) % 24;
		$days    = intdiv($elapsed, 86400);
		$parts   = [];
		if ($days) {
			$parts[] = "$days days";
		}
		if ($hours) {
			$parts[] = "$hours hrs";
		}
		if (!$days && $minutes) {
			$parts[] = "$minutes min";
		}
		if (!$days && !$hours && $seconds) {
			$parts[] = "$seconds sec";
		}
		return $parts ? implode(' ', $parts) : '0 sec';
	}

	/** elapsed time since this date */
	function since($now = NULL) {
		return $this->valid() ? self::elapsed($this->getTimestamp(), $now) : '';
	}
	public function __toString() {
		return $this->print();
	}
}
