<?php

/** @var \Systopic\System\Panels\Root\Cms\Backup\Create\Panel $this */

// Aliases for parent panel properties (formerly in include.php).
$tmpdirpath   = $this->parent->tmpdirpath;
$mediadirpath = $this->parent->mediadirpath;

// ZipArchive needs native OS paths on Windows; all fs::$root-based
// paths are stored in internal (forward-slash) form and must be
// converted before passing to ZipArchive or shell commands.
$tmpdirpathNative = fs::toNative($tmpdirpath);

$tmpdir = fsDir::get($tmpdirpath);

/**
 * Closes a zip and says so when it did not work.
 *
 * ZipArchive::close() writes a temp file next to the archive and renames it
 * into place. When that rename fails - a locked file, a folder the web server
 * may write but not replace in - php emits a WARNING and close() returns
 * false. The warning goes straight into the ajax response, in front of the
 * debug marker, where it used to take the whole response down with it. And
 * the code kept going as if the archive had been written.
 */
$closeZip = function (ZipArchive $zip, string $path): bool {
	if (@$zip->close()) {
		return TRUE;
	}
	// One retry: on windows the rename hits ERROR_ACCESS_DENIED while anything
	// else still holds the file open for a moment - a virus scanner reading
	// the freshly written archive is the usual candidate. A failed close()
	// leaves the archive open, so a second attempt really does write again.
	$firstReason = $zip->getStatusString() ?: 'unknown';
	$this->parent->log("zip close failed, retrying: $path ($firstReason)");
	usleep(250000);
	if (@$zip->close()) {
		return TRUE;
	}
	$reason = $zip->getStatusString() ?: $firstReason;
	message::error("Zip could not be written: $path ($reason)");
	$this->parent->log("ERROR zip close failed twice: $path ($reason)");
	return FALSE;
};

switch ($this->action) {
	case 'updateBackupSettings':
		$this->parent->updateState(http::put()->values);
		break;
	case 'cancel':
		clearstatcache();
		$tmpdir->unlink(TRUE);
		$this->parent->state->cancel = TRUE;
		message::info('Backup Stopped');
		http::refresh('./');
		break;
	case 'createTaskList':
		$this->parent->state->cancel = FALSE;
		clearstatcache();
		$tmpdir->unlink(TRUE);
		clearstatcache();
		if (fs::fileExists($tmpdirpath) || fs::isDir($tmpdirpath)) {
			message::error("temp File exists and could not be removed.");
			http::redirect('./cancel');
			\Systopic\System\Auth\Session::current()->storeState();
			exit;
		}
		$tmpdir->make();
		$this->parent->state->taskList = array(
			array(
				'action' => 'addMysqldump',
				'value' => 'all'
			)
		);
		$this->parent->state->taskListNextIndex = 0;
		if ($this->parent->state->backupFiles == 1) {
			$mediadirs = fs::glob($mediadirpath . '*', GLOB_ONLYDIR);
			foreach ($mediadirs as $mediadir) {
				$mediadir = basename($mediadir);
				$this->parent->state->taskList[] = array(
					'action' => 'addMediaDir',
					'value' => $mediadir
				);
			}
		}
		$this->parent->state->taskList[] = array('action' => 'finishBackup');
		message::confirm("directory created");

		$zip = new ZipArchive();
		$this->parent->state->spanindex = 0;
		$zipname = $tmpdirpathNative . 'backup-' . $this->parent->state->spanindex . '.zip';
		if (($zipErr = $zip->open($zipname, ZIPARCHIVE::CREATE)) !== true) {
			message::error("Cannot create zip (error $zipErr): $zipname");
			$this->parent->log("ERROR createTaskList: cannot open $zipname (err $zipErr)");
			break;
		}
		$info_txt = serialize(array(
			'user' => \Systopic\System\Auth\Session::current()->name,
			'created' => date("Y-m-d H:i:s"),
			'stage' => STAGE,
			'taskList' => $this->parent->state->taskList,
			'settings' => $this->parent->state,
		));
		$zip->addFromString("info.txt", $info_txt);
		if (!$closeZip($zip, $zipname)) {
			break;
		}
		message::confirm("task List created.");
		$this->parent->log("task List created");
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'addMysqldump':
		if ($this->parent->state->cancel) {
			exit;
		}
		$zip = new ZipArchive();
		$zipname = $tmpdirpathNative . 'backup-' . $this->parent->state->spanindex . '.zip';
		if (($zipErr = $zip->open($zipname)) !== true) {
			message::error("Cannot open zip (error $zipErr): $zipname");
			$this->parent->log("ERROR addMysqldump: cannot open $zipname (err $zipErr)");
			break;
		}
		$passwordArg = (OS === 'windows')
			? '--password=' . escapeshellarg(DB_PASS)
			: '--password=' . escapeshellarg(DB_PASS);
		$mysqldumpBin = 'mysqldump';
		if (OS === 'windows') {
			$candidates = array_filter(array_merge(
				glob('C:/laragon/bin/mysql/mysql-*/bin/mysqldump.exe') ?: [],
				glob('C:/xampp/mysql/bin/mysqldump.exe') ?: [],
				glob('C:/wamp64/bin/mysql/mysql*/bin/mysqldump.exe') ?: [],
			));
			if (!$candidates) {
				// fallback: ask the OS (works when Apache inherits a proper PATH)
				$resolved = trim((string) shell_exec('where mysqldump 2>NUL'));
				if ($resolved) {
					$candidates = [strtok($resolved, "\n")];
				}
			}
			if ($candidates) {
				$mysqldumpBin = '"' . trim(reset($candidates)) . '"';
			}
		}
		$command = "$mysqldumpBin -v -r \"{$tmpdirpathNative}mysqldump_all.sql\" --host=" . DB_HOST . " --user=" . DB_USER . " $passwordArg " . DB_NAME;
		passthru($command, $status);
		if (fs::isFile($tmpdirpath . "mysqldump_all.sql")) {
			$zip->addFile($tmpdirpathNative . "mysqldump_all.sql", "mysqldump_all.sql");
			$zip->setCompressionName("mysqldump_all.sql", ZipArchive::CM_STORE);
			if (!$closeZip($zip, $zipname)) {
				break;
			}
			fs::unlinkFile($tmpdirpath . "mysqldump_all.sql");
			$this->parent->state->taskListNextIndex++;
			message::confirm("MySQL Dump added.");
			if (!isset($this->parent->state->taskList[$this->parent->state->taskListNextIndex])) {
				if (!isset($this->parent->state->taskList[$this->parent->state->taskListNextIndex - 1])) {
					p($this->parent->state->taskList);
					p($this->parent->state->taskListNextIndex);
					break;
				} else {
					$this->parent->state->taskListNextIndex = $this->parent->state->taskListNextIndex - 1;
				}
			}
			$this->updateView('lightbox');
			http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		} else {
			message::error("No Dump created (exit=$status) - command: '$command'");
		}
		break;
	case 'addMediaDir':
		if ($this->parent->state->cancel) {
			exit;
		}
		$this->parent->log("*************** addMediaDir " . $this->parent->state->taskListNextIndex);
		$mediaDir = $this->parent->state->taskList[$this->parent->state->taskListNextIndex];
		$mediaDir = $mediaDir['value'];

		$zip = new ZipArchive();
		$zipname = $tmpdirpathNative . 'backup-' . $this->parent->state->spanindex . '.zip';
		if (fs::filesize($tmpdirpath . 'backup-' . $this->parent->state->spanindex . '.zip') > 1.5 * 1024 * 1024 * 1024) { // start next span if larger then 1.5GB
			$this->parent->state->spanindex++;
			$zipname = $tmpdirpathNative . 'backup-' . $this->parent->state->spanindex . '.zip';
			$zipErr = $zip->open($zipname, ZIPARCHIVE::CREATE);
		} else {
			$zipErr = $zip->open($zipname);
		}
		if ($zipErr !== true) {
			message::error("Cannot open span zip (error $zipErr): $zipname");
			$this->parent->log("ERROR addMediaDir: cannot open span zip $zipname (err $zipErr)");
			break;
		}

		$mzipPath = $tmpdirpathNative . $mediaDir . '.zip';
		$mzip = new ZipArchive();
		if (($mzipErr = $mzip->open($mzipPath, ZIPARCHIVE::CREATE)) !== true) {
			$zip->close();
			message::error("Cannot create media zip (error $mzipErr): $mzipPath");
			$this->parent->log("ERROR addMediaDir: cannot create media zip $mzipPath (err $mzipErr) | tmpdirpath=$tmpdirpath mediaDir=$mediaDir");
			break;
		}
		$subdirs = fs::glob($mediadirpath . $mediaDir . '/*', GLOB_ONLYDIR);
		foreach ($subdirs as $subdir) {
			$files = fs::glob($subdir . '/*');
			foreach ($files as $file) {
				$fileInternal = fs::toInternal($file);
				$target = preg_replace('/(^.*\/var.*?\/original\/[^\/]*\/)(.*$)/', '\2', $fileInternal);
				$this->parent->log("addFile " . $target . " " . round(fs::filesize($file) / 1024 / 1024, 1) . "MB");
				$mzip->addFile(fs::toNative($fileInternal), $target);
				$mzip->setCompressionName($target, ZipArchive::CM_STORE);
			}
		}
		if (!$closeZip($mzip, $mzipPath)) {
			$zip->close();
			break;
		}
		$zip->addFile($tmpdirpathNative . $mediaDir . '.zip', $mediaDir . '.zip');
		$zip->setCompressionName($mediaDir . '.zip', ZipArchive::CM_STORE);
		if (!$closeZip($zip, $zipname)) {
			break;
		}
		fs::unlinkFile($tmpdirpath . $mediaDir . ".zip");
		clearstatcache();
		message::confirm("Media Dir [{$this->parent->state->taskListNextIndex}] added.");
		$this->parent->state->taskListNextIndex++;
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'finishBackup':
		if ($this->parent->state->cancel) {
			exit;
		}
		$backupfiles = fs::glob($tmpdirpath . 'backup*');
		$backupfiletarget = $this->parent->backupdirpath . date('Y-m-d--H-i-s-') . DB_NAME . '-';
		foreach ($backupfiles as $backupfile) {
			$backupfileInternal = fs::toInternal($backupfile);
			$index = preg_replace('/^.*\/backups\/tmp\/backup-([0-9]+).*$/', '\1', $backupfileInternal);
			clearstatcache();
			/* prompt(array(
			  'readable' => is_readable($backupfile),
			  'writable' => is_writable($backupfile),
			  'size' => filesize($backupfile),
			  )); */
			fs::fsRename($backupfile, $backupfiletarget . $index . ".zip");
		}
		clearstatcache();
		$this->parent->backupfilestable = $this->parent->getBackupfilestable($this->parent->backupdirpath);
		$this->parent->updateView('backups');
		$this->updateView('lightbox');
		$tmpdir->unlink(TRUE);
		if (!$tmpdir->exists) {
			message::confirm("Temp Dir removed");
		} else {
			message::error("failed to remove Temp Dir");
		}

		$this->parent->log("Backup Created");
		$this->parent->state->taskListNextIndex++;
		message::confirm("Backup complete - good Luck!");
		break;
}

