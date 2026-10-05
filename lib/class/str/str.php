<?php

#[AllowDynamicProperties]
class str implements clientData {

	static function getSingular($plural) {
		switch (substr($plural, -1)) {
			case 'n': // tassen -> tasse
			case 's': // planets -> planet
				if (substr($plural, -3) === 'xes') {
					return(substr($plural, 0, -2));
				} else {
					return(substr($plural, 0, -1));
				}
				break;
			default: // 
				return $plural;
				break;
		};
	}

	static function parseYAML($yaml) { // decode imagick identify
		if (!is_string($yaml) || $yaml === '') {
			return [];
		}
		$lines = explode("\n", str_replace("\r\n", "\n", $yaml));
		$lastLevel = 0;
		$lastKey = 'root';
		$return = [];
		$active = &$return;
		$parents = []; // parent stack
		foreach ($lines AS $line) {
			if (preg_match('~(\ *)([^:]*):(.*)~', $line, $info)) {
				list($match, $indent, $key, $value) = $info;
			} else {
				continue;
			}
			$key = trim($key);
			if ($key === '') {
				continue;
			}
			$level = strlen($indent);
			if ($level > $lastLevel) {
				$parents[$lastLevel] = &$active;
				$active = &$active[$lastKey];
				$lastLevel = $level;
			}
			if ($level < $lastLevel) {
				$lastLevel = $level;
				if (isset($parents[$level])) {
					$active = &$parents[$level];
				} else {
					$active = &$return;
				}
			}
			if (!isset($active[$key])) {
				$active[$key] = [];
			}
			$active[$key]['value'] = trim($value);
			$lastKey = $key;
		}
		return $return;
	}

	static function br2nl($str) {
		return str_replace(['<br>', '<br/>', "\r\n", "\r"], "\n", $str);
	}

	static function formatbytes($filenameOrSize, $precision = 3) { // precision anzahl stellen insgesamt
		if (fs::isFile($filenameOrSize)) {
			$filesize = fs::filesize($filenameOrSize);
		} elseif (is_int($filenameOrSize)) {
			$filesize = $filenameOrSize;
		} else {
			return '--';
		}
		$base = log($filesize, 1024);
		$suffixes = array('Byte', 'KB', 'MB', 'GB', 'TB');
		$size = pow(1024, $base - floor($base));
		$decimals = $precision - strlen(floor($size));
		$filesize = round($size, $decimals) . '&nbsp;' . $suffixes[floor($base)];

		if ($filesize <= 0) {
			return $filesize = 'unknown file size';
		} else {
			return $filesize;
		}
	}

	static function megapixels($width, $height, $digits = 1) {
		return round($width * $height / 1E+6, $digits);
	}

	static function getPlural($singular, $lang = 'en') {
		if (in_array($singular, ['media'])) {
			return $singular;
		}
		if (preg_match('~\d$~', $singular)) { // 
			return $singular;
		}
		return $singular . 's';
	}

	static function randomAppendix($length = 0) {
		$chars = 'acemnorsuvwxz';
		$appx = '';
		while ($length-- > 0) {
			$appx .= $chars[rand(0, strlen($chars) - 1)];
		}
		return $appx;
	}

	static function randomChar($string = 'abcdefghijklmnopqrstuvwxyz') {
		$pos = rand(0, (strlen($string) - 1));
		return $string[$pos];
	}

	static function randomHex($bytelength = 30) {
		return bin2hex(openssl_random_pseudo_bytes($bytelength));
	}

	static function removeDecendantsFromClassname($classnameWithDescendants) {
		// patchworkSequence_item => patchwork_item (main class and table name)
		// patchworkWall => patchwork (main class and table name)
		$classname = preg_replace('~[A-Z][a-z]+(_?)~', '$1', $classnameWithDescendants);
		return $classname;
	}

	/** a technical name becomes a headline: 'general' → 'General', 'user_roles' →
	 * 'User Roles' - panels, settings and tag groups are all named that way */
	static function name2label($name) {
		return implode(' ', array_map('ucfirst', explode('_', (string) $name)));
	}

	static function ucSepToUnderscores($ucSep) {
		$usSep = '';
		foreach (str_split($ucSep) AS $char) {
			if (ctype_upper($char)) {
				$usSep .= '_' . strtolower($char);
			} else {
				$usSep .= $char;
			}
		}
		return $usSep;
	}

	static function ucExplode($ucSep, $delimiter = '~###~') {
		$repl = preg_replace('~[A-Z]~', "$delimiter$0", $ucSep);
		return $ucSep !== $repl ? explode($delimiter, strtolower($repl)) : [$ucSep];
	}

	private static function shorten($string, $max, $appendix = '...', $tolerance = 0.5) {
		$length = strlen($string);
		if ($length > $max * (1 + $tolerance)) {
			$shortened = substr($string, 0, $max);
			return preg_replace('~\s[^\s]*$~', '', $shortened) . $appendix;
		}
		return $string;
	}

	static function getSafeName($name, $allowedChars = NULL) {
		$safeNameSettings = self::getSafeNameSettings($allowedChars);
		$safeNameSettings->search = array_map(function ($val) {
			return "~$val~"; // add delimiter
		}, $safeNameSettings->search);
		$trimregex = "~^[$safeNameSettings->trim]+|[$safeNameSettings->trim]+$~";
		$safeNameSettings->search [] = $trimregex;
		$safeNameSettings->replace [] = '';
		$safeName = preg_replace($safeNameSettings->search, $safeNameSettings->replace, $name);
		return $safeName; // js replace
	}

	static function getSafeNameSettings($allowedChars = NULL) {
		$allowedChars = $allowedChars ?: 'A-Za-z0-9_\.\-';
		return (object) [
					'allowedChars' => $allowedChars,
					'search' => ["ä", "ö", "ü", "Ä", "Ö", "Ü", "ß", "'", "[^$allowedChars]+", "_+"],
					'replace' => ["ae", "oe", "ue", "Ae", "Oe", "Ue", "ss", "", "_", "_"],
					'trim' => "\s\t\n\r\x0B_",
		];
	}

	/*	 * *********** INTERFACES *********** */

	static function clientData_export() {
		return (object) [
					'route' => 'singletons.str',
					'data' => (object) [
						'safeNameSettings' => self::getSafeNameSettings()
					]
		];
	}

	/*	 * *********** OVERLOADING *********** */

	static function __callStatic($name, $arguments) {
		if (is_callable([str::class, $name])) { // ?? no effect
			return forward_static_call_array(['str', $name], array_merge($arguments));
		}
		t('not callable: str::' . $name);
	}

	function __construct($string) {
		$this->string = $string;
	}

	function __call($name, $arguments) {
		if (is_callable([$this, $name])) {
			return forward_static_call_array(['str', $name], array_merge([$this->string], $arguments)); // first argument of static must be string value
		}
		t('not callable: str::' . $name);
	}

}
