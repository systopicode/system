<?php

/**
 * The published copies, as the table inside the Delivery card. main.tpl.php
 * shows it, actions.php sends it again after Publish and Clear.
 */

use Systopic\System\Http\Assets;

$e = static fn(mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES);
$status = Assets::status();
$newest = $status['newest'] === null ? '—' : date('j M Y, H:i', $status['newest']);
$rows = [
	'Mode' => $status['mode'],
	'Files' => (string) $status['files'],
	'Size' => $status['bytes'] > 0 ? str_replace('&nbsp;', ' ', (string) str::formatbytes($status['bytes'])) : '0',
	'Newest' => $newest,
];
$html = '<div class="tableScroll"><table><tbody>';
foreach ($rows as $label => $value) {
	$html .= '<tr><td class="muted">' . $e($label) . '</td><td>' . $e($value) . '</td></tr>';
}
return $html . '</tbody></table></div>';
