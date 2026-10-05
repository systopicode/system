<?php

#[AllowDynamicProperties]
class debug {

	public static function getCaller() {
		$stack = debug_backtrace();
		$callerFile = reset($stack)['file'];
		$callerFolder = substr($callerFile, 0, strrpos($callerFile, '/') + 1);
		while (next($stack)) {
			$frame = current($stack);
			if (!isset($frame['file']) || strpos($frame['file'], $callerFolder) !== 0) {
				break;
			}
		}
		$caller = current($stack);
		if ($caller === false) {
			$caller = end($stack);
		}
		$remove = [
			$_SERVER['DOCUMENT_ROOT'],
			'_rutancms/'
		];
		$file = str_replace($remove, '', $caller['file'] ?? '');
		$line = $caller['line'] ?? 0;
		return "$file:$line";
	}

	static function flush() {
		if (DEBUG()) {
			scripttime('render ready');
			fs::includeFile((string) (fs::$sysRoot . "lib/debug/debug.tpl.php"));
			scripttime('debug tpl ready');
		}
	}

	static function getCallWithinClasses($classes) {
		foreach (debug_backtrace() AS $next) {
			if (!isset($next['class']) || !in_array($next['class'], $classes)) { // pre enter
				continue;
			}
			if (!isset($next['class']) || !in_array($next['class'], $classes)) { // exit
				break;
			}
			$trce = $next; // record
		}
		if (!isset($trce)) {
			d(debug_backtrace());
		}
		switch ($trce['function']) {
			case 'openChild': // reverse arg order
				$name = isset($trce['args'][0]) ? $trce['args'][0] : '';
				$file = isset($trce['args'][1]) ? $trce['args'][1] : '';
				break;
			default:
				$file = isset($trce['args'][0]) ? $trce['args'][0] : '';
				$name = isset($trce['args'][1]) ? $trce['args'][1] : '';
				break;
		}

		return (object) [
					'class' => $trce['class'],
					'method' => $trce['function'],
					'viewName' => str_replace('.tpl.php', '', $file),
					'file' => $file,
					'name' => $name,
					'srcRoute' => implode('/', \Systopic\System\Panels\PanelNode::getNavigator()?->getActive()->path),
					'srcFile' => preg_replace('~^.*/~', '', $trce['file']),
		];
	}

}
