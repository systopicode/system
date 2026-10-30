<?php
include 'checks.inc.php';

$card = static function (string $title, array $rows): void {
	echo '<section class="widget"><header><h2>' . htmlspecialchars($title) . '</h2></header><main><ul class="toolCheck">';
	foreach ($rows as $row) {
		echo '<li class="' . htmlspecialchars((string) $row['status']) . '">' . $row['label'] . '</li>';
	}
	echo '</ul></main></section>';
};

$php = [
	$php_version,
	$post_max_size,
	$upload_max_size,
	$max_execution_time,
	$memory_limit,
	$display_errors,
	$log_errors,
];
?>
<h1>System check</h1>
<p class="tagsHint">What this server is running, and what the CMS still needs. The download is one PHP file: open it on a server before the CMS is installed, then delete it.</p>
<p><a class="button submit" target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'download') ?>"><i class="cmsIcon cmsIcon-file-arrow-down"></i><span>Download</span></a></p>
<div class="widgets">
	<?php
	$card('PHP', $php);
    $maintenanceState = \Systopic\System\Maintenance\Overview::state();
    $maintenanceRows = [['status' => \Systopic\System\Maintenance\Overview::needsAttention() ? 'warning' : 'confirm', 'label' => htmlspecialchars(\Systopic\System\Maintenance\Overview::summary(), ENT_QUOTES)]];
    $maintenanceDir = \Systopic\System\Maintenance\Runner::directory();
    $maintenanceRows[] = ['status' => \Systopic\System\Maintenance\Overview::storageWritable() ? 'confirm' : 'warning', 'label' => 'Project maintenance storage: ' . (\Systopic\System\Maintenance\Overview::storageWritable() ? (is_dir($maintenanceDir) ? 'writable' : 'created on the first run') : 'not writable; cleanup cannot run')];
    $maintenanceRows[] = ['status' => 'confirm', 'label' => 'Request fallback and manual execution do not require CLI, shell access or a running hub.'];
    $cliRun = (int) ($maintenanceState['runs']['cli']['time'] ?? 0);
    $maintenanceRows[] = ['status' => $cliRun > 0 && $cliRun < time() - 86400 ? 'warning' : 'confirm', 'label' => 'Optional CLI — last observed run: ' . htmlspecialchars(\Systopic\System\Maintenance\Overview::time($cliRun), ENT_QUOTES) . '. Web PHP cannot reliably establish which CLI PHP or cron job exists; verify the scheduled command with an actual run.'];
    if ($cliRun > 0) $maintenanceRows[] = ['status' => 'confirm', 'label' => 'Last CLI runtime: PHP ' . htmlspecialchars((string) ($maintenanceState['runs']['cli']['php'] ?? 'unknown'), ENT_QUOTES) . '. Web runtime: PHP ' . PHP_VERSION . '.'];
    $card('Background tasks (optional CLI)', $maintenanceRows);

	$card('Folders', array_values($var_writeableFolders));
	$card('Mail & login', array_values($var_access));
	$card('Extensions', array_values($var_extensions));
	$card('Composer' . ($stage !== '' ? ' (' . $stage . ')' : ''), array_values($var_packages));
	if ($var_thirdParty !== []) {
		$card('3rd party', array_values($var_thirdParty));
	}
	$card('Functions', array_values($var_functions));
    $card('Optional media tools', array_values($var_binaries));
    ?>
    <section class="widget"><header><h2>Media support</h2><button type="button" class="button" data-on_click="probeMediaTools"><i class="cmsIcon cmsIcon-flask"></i>Test media tools</button></header><main>
    <p>Check whether this host can start the optional video and image tools. No media files are changed.</p>
    <dl class="cleanupActivity"><dt>Image editing</dt><dd><?= class_exists('Imagick') ? 'Imagick is installed' : 'Requires the Imagick PHP extension' ?></dd><dt>Backup archives</dt><dd><?= class_exists('ZipArchive') ? 'ZipArchive is installed' : 'Requires the ZipArchive PHP extension' ?></dd></dl>
    <p class="tagsHint">The test checks startup only. Supported formats, PDF permissions and large conversions still depend on the host.</p>
    <details class="cleanupDetails"><summary><i class="cmsIcon cmsIcon-circle-info"></i>About this check<i class="cmsIcon cmsIcon-chevron-down cleanupChevron"></i></summary><main><p>Project-local tools in <code>var/bin/</code>, <code>bin/</code> and platform subfolders are supported. The version test has a short timeout and never downloads tools or changes permissions.</p><p>Missing optional tools limit the related media feature. Cron and Centrifugo are not required for ordinary editing. Backups also need sufficient disk space and runtime.</p></main></details>
    </main></section>
    <?php
	$card('Server', array_values($var_server));
	?>
</div>
