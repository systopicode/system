<?php

$dbg = FALSE;
//$dbg = TRUE;
//print_r($_SERVER);
//die();

$pathinfo = pathinfo($_SERVER['SCRIPT_NAME']);
$dirname = preg_quote($pathinfo['dirname'],'~');
$css = preg_replace("~^$dirname~i", "", $_SERVER["REQUEST_URI"]);
$css = trim($css, '/');
$css = preg_replace('~^css_parsed/~i', '/css/', $css);
$css = preg_replace('~\?.*$~', '', $css); // remove query string
$css = trim($css, '/');
$css = file_get_contents(fs::$siteRoot . $css);

header("Content-type: text/css");
preg_match_all('/^[^\{]*\{[^\}]*\}/ms', $css, $matches);

$d = array();
$out = '';
foreach ($matches[0] as $match) {
	preg_match_all('/(^[^\{]*)(\{[^\}]*\})/ms', $match, $itemmatches);
	$selectorLines = explode("\n", $itemmatches[1][0]);
	foreach ($selectorLines as $selectorLine) {
		if (!preg_match('/(\/\*[^\/]*\/|^[\s]*$|^@)/', $selectorLine)) { // ignore comments, font declarations ...
			$declarations = explode(",", trim($selectorLine));
			$end = end($declarations);
			if (empty($end)){ // komma am zeilenende
				array_pop($declarations);
				$declarations[key($declarations)] .= ',';
			}
			$declarationString = "\narticle " . implode(", article", $declarations);
			$d[] = $declarationString;
			$declarationStringParsed = preg_replace(
					'~body~', '', $declarationString
			);
			$out .= $declarationStringParsed;
		} else {
			$out .= "\n$selectorLine";
		}
	}
	$out .= ($itemmatches[2][0]);
}
if (!$dbg) {
	p()->erase();
	die($out);
} else {
	print_r($d);
}