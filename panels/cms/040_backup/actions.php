<?php

/** @var \Systopic\System\Panels\Root\Cms\Backup\Panel $this */

switch ($this->action) {
	case "remove":
		$filename = http::get('name');
		if (preg_match('~[a-z0-9\-_]~i', $filename)) {
			$fileindex = 0;
			$basefilname = substr($filename, 0, -5);
			$spanfilename = fs::$siteRoot . 'var/backups/' . $basefilname . $fileindex . '.zip';
			while (fs::isFile($spanfilename)) {
				if (fs::unlinkFile($spanfilename)) {
					message::info("file '$basefilname$fileindex' removed.\n");
				};
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
	case 'clearTempdirs':
		$tmpdirpaths =  ['var/backups/tmp/', 'var/original_replaced/'];
		foreach ($tmpdirpaths as $tmpdirpath) {
			if (fs::isDir(fs::$siteRoot . $tmpdirpath)) {
				$tmpDir = fsDir::get(fs::$siteRoot . $tmpdirpath);
				$tmpDir->unlink(TRUE);
				message::confirm("$tmpdirpath cleared.");
			} else {
				message::info("$tmpdirpath not found.");
			}
		}
		$this->updateView();
		break;
}
