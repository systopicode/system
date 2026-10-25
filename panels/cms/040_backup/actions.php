<?php

/** @var \Systopic\System\Panels\Root\Cms\Backup\Panel $this */

switch ($this->action) {
	case 'download':
		// one part of a backup - var/backups/ itself is not served
		// (public/.htaccess): a whole database and the media, admins only
		$filename = (string) $this->backupName(http::get('name'));
		$file = fs::toNative(fs::$siteRoot . 'var/backups/' . $filename);
		$isZip = str_ends_with($filename, '.zip');
		$isLog = $filename === 'backup.log';
		if ($filename === '' || (!$isZip && !$isLog) || !\Systopic\System\Auth\Session::hasRole('admin') || !is_file($file)) {
			http_response_code(404);
			exit;
		}
		// nothing of the debug tool or a buffer in front of the zip
		foreach (['systopic\debug\sysdebug', 'sysdebug'] as $class) {
			if (class_exists($class, false) && $class::$debug !== null) {
				$class::$debug->erase(true);
			}
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		// a backup can be gigabytes: no time limit of PHP (on windows it is wall
		// time), sent in parts - and resumable (Range), for the limits outside
		// PHP that cut a long request off (FcgidBusyTimeout, proxy timeouts)
		set_time_limit(0);
		$size = (int) filesize($file);
		$from = 0;
		$to = $size - 1;
		if (preg_match('~^bytes=(\d*)-(\d*)$~', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $range) && ($range[1] !== '' || $range[2] !== '')) {
			$from = $range[1] === '' ? max(0, $size - (int) $range[2]) : (int) $range[1];
			$to = $range[1] !== '' && $range[2] !== '' ? min((int) $range[2], $size - 1) : $size - 1;
			if ($from > $to) {
				http_response_code(416);
				header("Content-Range: bytes */$size");
				exit;
			}
			http_response_code(206);
			header("Content-Range: bytes $from-$to/$size");
		}
		header('Content-Type: ' . ($isLog ? 'text/plain; charset=UTF-8' : 'application/zip'));
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . ($to - $from + 1));
		header('Accept-Ranges: bytes');
		header('Cache-Control: no-store');
		$handle = fopen($file, 'rb');
		fseek($handle, $from);
		for ($left = $to - $from + 1; $left > 0 && !connection_aborted(); $left -= strlen($part)) {
			$part = (string) fread($handle, min(8 * 1024 * 1024, $left));
			if ($part === '') {
				break;
			}
			echo $part;
			flush();
		}
		fclose($handle);
		exit;
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
