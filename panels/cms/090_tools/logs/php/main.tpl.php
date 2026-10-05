<?php
/** @var \Systopic\System\Panels\PanelNode $this */

// PHPLOGFILE where an installation defines it, else PHP's own error_log.
$path = defined('PHPLOGFILE') ? PHPLOGFILE : (string) ini_get('error_log');
$logfile = $path !== '' && is_file($path) ? (string) file_get_contents($path, false, null, max(0, filesize($path) - 512 * 1024)) : '';
?>
<div class="tabContainer">
	<p><?= htmlspecialchars($path !== '' ? $path : 'no log file configured') ?></p>
	<?php if ($logfile !== '') { ?>
		<textarea style='height: 100%;'><?= htmlspecialchars($logfile) ?></textarea>
	<?php } else { ?>
		<h2>LOGFILE IS EMPTY</h2>
	<?php } ?>
</div>
