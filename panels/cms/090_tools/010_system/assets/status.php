<?php

/**
 * The state of the published assets, as HTML - shown by main.tpl.php and
 * sent again after every action (actions.php). See Http\Assets.
 */

use Systopic\System\Http\Assets;

$e = static fn(mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES);
$status = Assets::status();
$modes = [
	'source' => 'source - straight from the original folders (the web reaches them); copies are not used',
	'serve' => 'serve - through /assets/ and asset.php, from the originals every time; copies are not used',
	'publish' => 'publish - through /assets/, copies in public/assets/ (made on first use, renewed when older)',
];
$rows = [
	'Mode' => $modes[$status['mode']] ?? $status['mode'],
	'Copies in' => $status['dir'] ?? '-',
	'Files' => $status['files'],
	'Size' => $status['bytes'] > 0 ? str::formatbytes($status['bytes']) : '0', // formatbytes(0) takes log(0)
	'Newest copy' => $status['newest'] === null ? '-' : date('Y-m-d H:i:s', $status['newest']),
];
$roots = '';
foreach (Assets::roots() as $name => $dir) {
	$roots .= '<li><b>' . $e($name) . '</b> ' . $e($dir) . '</li>';
}
$rows['Roots'] = "<ul>$roots</ul>";

$html = "<table class='hover assetsStatusTable'>";
foreach ($rows as $label => $value) {
	$html .= '<tr><th>' . $e($label) . '</th><td>' . ($label === 'Roots' ? $value : $e($value)) . '</td></tr>';
}
return $html . '</table>';
