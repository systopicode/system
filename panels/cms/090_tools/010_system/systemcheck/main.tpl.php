<?php
include_once('actions.php');

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
<p><a class="button submit" target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'download') ?>"><i class="fa-regular fa-file-arrow-down"></i><span>Download</span></a></p>
<div class="widgets">
	<?php
	$card('PHP', $php);
	$card('Folders', array_values($var_writeableFolders));
	$card('Mail & login', array_values($var_access));
	$card('Extensions', array_values($var_extensions));
	$card('Composer' . ($stage !== '' ? ' (' . $stage . ')' : ''), array_values($var_packages));
	if ($var_thirdParty !== []) {
		$card('3rd party', array_values($var_thirdParty));
	}
	$card('Functions', array_values($var_functions));
	$card('Binaries', array_values($var_binaries));
	$card('Server', array_values($var_server));
	?>
</div>
