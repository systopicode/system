<?php

/** @var \Systopic\System\Panels\Root\Cms\Backup\Panel $this */

switch ($this->action) {
	case "remove":
		// the first part, <base>-0.zip: removed with every part after it
		$filename = (string) $this->backupName(http::get('name'));
		if (str_ends_with($filename, '-0.zip')) {
			$basefilname = substr($filename, 0, -5);
			$removed = 0;
			for ($fileindex = 0; fs::isFile($spanfilename = fs::$siteRoot . 'var/backups/' . $basefilname . $fileindex . '.zip'); $fileindex++) {
				if (fs::unlinkFile($spanfilename)) {
					$removed++;
				}
			}
			if ($removed > 0) {
				message::confirm('Backup removed.');
			}
		}
		$this->backupfilestable = $this->getBackupfilestable($this->backupdirpath);
		$this->updateView();
		break;
	case 'createDatabase':
		try {
			$dbh = new PDO("mysql:host=" . DB_HOST . "", DB_USER, DB_PASS);
			if ($dbh->exec("CREATE DATABASE `" . DB_NAME . "`;")) {
				//                GRANT ALL ON `" . DB_NAME . "`.* TO '" . DB_USER . "'@'" . DB_HOST . "';
				//                FLUSH PRIVILEGES;")) {
				message::confirm('Database created - you may recover your backup now.');
			} else {
				message::error(print_r($dbh->errorInfo(), true));
			}
		} catch (PDOException $e) {
			message::error("DB ERROR: " . $e->getMessage());
		}
		http::redirect('./');
		//$this->updateView();
		break;
	case 'upload':
		$name = (string) http::put('name');
		$problem = $this->acceptBackupSpan(
			$name,
			(int) http::put('spanIndex'),
			(bool) http::put('lastSpan'),
			(string) http::binary(),
		);
		if ($problem !== null) {
			message::error($problem);
			client::done(['error' => $problem]);
			http::put('away') ? $renderStyle = 'none' : $this->updateView('backups');
			break;
		}
		// away: the user went on to another panel meanwhile, a render would land there
		if (!http::put('lastSpan') || http::put('away')) {
			$renderStyle = 'none';
			break;
		}
		message::confirm('Backup uploaded.');
		$this->backupfilestable = $this->getBackupfilestable($this->backupdirpath);
		// the list and the message: the buttons above stay as they are
		$this->updateView('backups');
		break;
	case 'clearTempdirs':
		$tmpdirpaths =  ['var/backups/tmp/', 'var/original_replaced/'];
		$cleared = 0;
		foreach ($tmpdirpaths as $tmpdirpath) {
			if (fs::isDir(fs::$siteRoot . $tmpdirpath)) {
				$tmpDir = fsDir::get(fs::$siteRoot . $tmpdirpath);
				$tmpDir->unlink(TRUE);
				$cleared++;
			}
		}
		message::confirm($cleared > 0 ? 'Temporary files cleared.' : 'Nothing to clear.');
		$this->updateView();
		break;
}
