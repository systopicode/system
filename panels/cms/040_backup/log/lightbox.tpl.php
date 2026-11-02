<?php
/**
 * The backup log. Download and clear as the controlmap lets this user
 * (../panel.config.json: download; panel.config.json: clear).
 *
 * @var \Systopic\System\Panels\PanelNode $this
 */
$logFile = $this->parent->logfile->dir->dirpath . 'backup.log';
$log = is_file(fs::toNative($logFile)) ? (string) file_get_contents(fs::toNative($logFile)) : '';
?>
<div class="cms cmslightbox" data-size="1">
    <section>
		<header>
			<h1>Backup Log</h1>
			<ul class="barmenu">
				<?php if ($log !== '' && \Systopic\System\Controlmap\Access::action($this->parent, 'download')) { ?>
					<li>
						<a download target="_self" href="<?= http::$siteUrl . 'cms/backup/download?name=backup.log' ?>" title="Download log"><i class="fa-regular fa-arrow-down-to-line"></i></a>
					</li>
				<?php } ?>
				<?php if ($log !== '' && \Systopic\System\Controlmap\Access::action($this, 'clear')) { ?>
					<li>
						<a data-on_click="userConfirm" data-action="clear" data-confirm="Clear this log?" title="Clear log"><i class="fa-regular fa-trash"></i></a>
					</li>
				<?php } ?>
				<li>
					<a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a>
				</li>
			</ul>
		</header>
		<main>
			<?php if ($log === '') { ?>
				<p class="tagsHint">No backup has written to the log yet.</p>
			<?php } else { ?>
				<pre><?= htmlspecialchars($log, ENT_QUOTES) ?></pre>
			<?php } ?>
		</main>
    </section>
</div>

