<div class="cms cmslightbox" data-size="1">
    <section>
		<header>
			<h1>Backup Log</h1>
			<ul class="barmenu">
				<li>
					<a download target="_self" href="<?= http::$siteUrl . 'cms/backup/download?name=backup.log' ?>" title="Download log"><i class="fa-regular fa-arrow-down-to-line"></i></a>
				</li>
				<li>
					<a data-on_click="userConfirm" data-action="clear" data-confirm="Clear this log?" title="Clear log"><i class="fa-regular fa-trash"></i></a>
				</li>
				<li>
					<a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a>
				</li>
			</ul>
		</header>
		<main>
			<pre><?= file_get_contents($this->parent->logfile->dir->dirpath . 'backup.log') ?></pre>
		</main>
    </section>
</div>

