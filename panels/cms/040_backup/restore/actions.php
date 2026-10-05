<?php

/** @var \Systopic\System\Panels\Root\Cms\Backup\Restore\Panel $this */

use Systopic\Db\Schema\Restore;

// Aliases for parent panel properties (formerly in include.php).
$tmpdirpath           = $this->parent->tmpdirpath;
$mysqlfile            = $this->parent->mysqlfile;
$backupdirpath        = $this->parent->backupdirpath;
$mediadirpath         = $this->parent->mediadirpath;
$replacedmediadirpath = $this->parent->replacedmediadirpath;
$spanindex            = $this->parent->state->spanindex;

$tmpdirpathNative    = fs::toNative($tmpdirpath);
$backupdirpathNative = fs::toNative($backupdirpath);

switch ($this->action) {
	case 'createTaskList':
		clearstatcache();
		fsDir::get($tmpdirpath)->unlink(TRUE);
		clearstatcache();
		if (fs::fileExists($tmpdirpath)) {
			message::error("temp File could not be removed - Restore cancelled. pls check fs permissions");
			break;
		}
		fsDir::get($tmpdirpath . 'media/')->make();
		$this->parent->state->spanindex = 0;
		$this->parent->state->spanbasename = http::get('name');
		$this->parent->state->varFolder = http::get('folder', 'var');
		$zip = new ZipArchive();
		$backupfile = $backupdirpathNative . http::get('name') . '-0.zip';
		$zip->open($backupfile);
		$info_txt = unserialize($zip->getFromName('info.txt'));
		$zip->close();
		$this->parent->state->taskList = $info_txt['taskList'];
		foreach ($this->parent->state->taskList as &$taskListItem) {
			$taskListItem = str_replace(['add', 'Backup'], ['extract', 'Restore'], $taskListItem);
		}
		$this->parent->state->taskListNextIndex = 0;
		$this->parent->state->restoreMediaFolder = 0; // dont swap media folders on finishBackup
		message::confirm("Task list created.");
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'extractMysqldump':
		if ($this->parent->state->cancel) {
			exit;
		}
		$zip = new ZipArchive();
		$zip->open($backupdirpathNative . $this->parent->state->spanbasename . '-0.zip');
		$mysqldump = $zip->getFromName('mysqldump_all.sql');
		$mysqldumpPatched = preg_replace('~((,\n\s*)CONSTRAINT[^\n]*)|(CONSTRAINT[^\n]*)~', '', $mysqldump); // remove CONSTRAINTS
		fs::file_put_contents($tmpdirpath . 'mysqldump_all.sql', $mysqldumpPatched);
		$zip->close();
		$this->parent->state->taskListNextIndex++;
		message::confirm("MySQL Dump extracted.");
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'extractMediaDir':
		if ($this->parent->state->cancel) {
			exit;
		}
		$this->parent->state->restoreMediaFolder = 1; // swap media folders on finishBackup
		$mediaDir = $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['value'];
		$zip = new ZipArchive();
		$zipFileName = $backupdirpathNative . $this->parent->state->spanbasename . '-' . $spanindex . '.zip';
		$zip->open($zipFileName);
		$zipname = $mediaDir . '.zip';
		fs::fsMkdir($tmpdirpath . 'media/' . $mediaDir);
		fs::file_put_contents($tmpdirpath . $zipname, $zip->getFromName($zipname));
		$mzip = new ZipArchive();
		$mzip->open($tmpdirpathNative . $zipname);
		$folder = '';
		for ($index = 0; $index < $mzip->numFiles; $index++) {
			$mentryname = $mzip->getNameIndex($index);
			list($mfolder, $mfile) = explode('/', $mentryname);
			if ($folder != $mfolder) {
				fs::fsMkdir($tmpdirpath . 'media/' . $mediaDir . '/' . $mfolder);
				$folder = $mfolder;
			}
			$mediafile = $mzip->getFromIndex($index);
			fs::file_put_contents($tmpdirpath . 'media/' . $mediaDir . '/' . $folder . '/' . $mfile, $mediafile);
		}
		$mzip->close();
		fs::unlinkFile($tmpdirpath . $zipname);
		$zip->close();
		$this->parent->state->taskListNextIndex++;
		message::confirm("extracted Media Directory $mediaDir.");
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'finishRestore':
		if ($this->parent->state->cancel) {
			exit;
		}
		$restore = new Restore($this->scope->connection);
		$restore->dropForeignKeys();
		foreach ($restore->run((string) fs::file_get_contents($mysqlfile)) as $error) {
			message::error("SQL Error during Restore: $error");
		}

		if ($this->parent->state->restoreMediaFolder && fs::isDir($tmpdirpath . '/media/')) {
			if (fs::isDir($mediadirpath)) {
				try {
					fs::fsRename($mediadirpath, $replacedmediadirpath);
				} catch (Exception $exc) {
					d("rename($mediadirpath, $replacedmediadirpath)")->error($exc);
				}
			}
			if(!fs::fsRename($tmpdirpath . 'media/', $mediadirpath)) {
				message::error("Media Folder could not be renamed.");
				$this->parent->log("rename('$tmpdirpath' . 'media/', '$mediadirpath') failed");
				break;
			}
			message::confirm("Database restored, Media Folder renamed.");
		}
		$this->parent->log("Backup Restored");
		$this->parent->state->taskListNextIndex++;

		$this->updateView('lightbox');
		if (\Systopic\System\Auth\Session::isRecovery()) {
			message::confirm("Restore Complete - please type your credentials to log in!");
			http::redirect(http::$root . 'cms/logout');
		} else {
			message::confirm("Restore Complete - Good Luck! - removing Tempfiles ...");
			http::refresh('./', 'clearTempfiles');
		}
		break;
	case 'clearTempfiles':
		if ($this->parent->state->cancel) {
			exit;
		}
		fsFile::get($mysqlfile)->unlink();
		fsDir::get($replacedmediadirpath)->unlink(TRUE);
		fsDir::get($tmpdirpath)->unlink(TRUE);
		clearstatcache();
		if (fs::fileExists($tmpdirpath)) {
			message::error("temp File could not be removed");
		} elseif(fs::fileExists($replacedmediadirpath)) {
			message::error("old Media Dir could not be removed");
		}else{
			message::confirm("mysql dump file removed.");
			message::confirm("old Media Dir removed.");
			message::confirm("temp Files cleared.");
		}
		$this->updateView('lightbox');
		break;
}
