<div class="cms cmslightbox" data-size="1">
    <section>
		<header>
			<h1>Backup Log</h1>
			<ul class="barmenu">
				<li>
					<a download target="self" href="<?= http::$siteUrl . 'var/backups/backup.log' ?>"  title="Download Logfile"><i class="fa-light fa-arrow-down-to-line"></i></a>
				</li>
				<li>
					<a data-on_click='userConfirm' href="clear" data-confirm='Do you really want to clear this Log?' title="Clear Logfile"><i class="fa-light fa-trash"></i></a>
				</li>
				<li>
					<a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
				</li>
			</ul>
		</header>
		<main>
			<pre><?= file_get_contents($this->parent->logfile->dir->dirpath . 'backup.log') ?></pre>
		</main>
    </section>
</div>

