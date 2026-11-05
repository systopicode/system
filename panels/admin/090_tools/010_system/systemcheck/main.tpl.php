<?php
include 'checks.inc.php';

$card = static function (string $title, array $rows): void {
    $root = \http::$root;
    $remedy = match ($title) {
        'Folders' => ['Check filesystem ownership and grant the PHP user write access only to the listed project folders.', $root . 'admin/settings/environment/', 'Environment'],
        'Mail & login' => ['Review the active environment and use Send test email to verify SMTP acceptance.', $root . 'admin/settings/environment/', 'Mail & login settings'],
        'Optional media tools', 'Functions', 'Extensions' => ['Review PHP extensions, disabled functions and project-local binaries with your host. Optional tools can be installed under var/bin/.', $root . 'admin/settings/mediasettings/', 'Media settings'],
        'Background tasks (optional CLI)' => ['Open cleanup for execution status, a manual run and optional scheduling instructions.', $root . 'admin/tools/cleanup/automatic/', 'Cleanup'],
        'Server' => ['Live-update configuration belongs to this installation; review the environment connection and allowed addresses.', $root . 'admin/settings/environment/', 'Environment'],
        default => null,
    };

	echo '<section class="widget"><header><h2>' . htmlspecialchars($title) . '</h2></header><main><ul class="toolCheck">';
	foreach ($rows as $row) {

        $status = (string) $row['status'];
        $icon = match ($status) { 'confirm' => 'circle-check is-success', 'error' => 'circle-xmark is-error', 'warning' => 'triangle-exclamation is-warning', default => 'circle-info' };
        echo '<li class="' . htmlspecialchars($status) . '"><i class="cmsIcon cmsIcon-' . $icon . '" aria-hidden="true"></i><span>' . $row['label'] . '</span></li>';
	}
	echo '</ul>';
    if ($remedy && array_filter($rows, static fn($row) => ($row['status'] ?? '') !== 'confirm')) {
        echo '<p class="tagsHint">' . htmlspecialchars($remedy[0], ENT_QUOTES) . '</p><a class="button ' . ($remedy[2] === 'Environment' ? 'outline' : 'transparent') . '" href="' . htmlspecialchars($remedy[1], ENT_QUOTES) . '"><i class="cmsIcon cmsIcon-arrow-right"></i><span>' . htmlspecialchars($remedy[2], ENT_QUOTES) . '</span></a>';
    }
    echo '</main></section>';
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
<p class="tagsHint">What this server is running, and what the System still needs. The download is one PHP file: open it on a server before the System is installed, then delete it.</p>
<p><button type="button" class="button" data-action-feedback data-loading data-on_click="recheck"><i class="cmsIcon cmsIcon-arrows-rotate"></i>Recheck</button> <a class="button submit" target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'download') ?>"><i class="cmsIcon cmsIcon-file-arrow-down"></i><span>Download</span></a></p>
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

    ?>
    <?php $this->withView('media'); ?>
    <?php
	$card('Server', array_values($var_server));
	?>
</div>
