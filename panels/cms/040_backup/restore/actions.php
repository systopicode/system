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
	case 'updateRestoreSelection':
		$this->parent->updateState(http::put()->values);
		break;
	case 'createTaskList':
		if ($this->backupName(http::get('name')) === null) {
			message::error('This backup was not found.');
			break;
		}
		$this->parent->takePostedSelection();
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
		unset($taskListItem);
		$this->parent->state->taskList = array_values(array_filter(
			$this->parent->state->taskList,
			fn(array $task): bool => $this->parent->restoreTaskKept($task)
		));
		$this->parent->state->taskListNextIndex = 0;
		$this->parent->state->restoreMediaFolder = 0; // dont swap media folders on finishBackup
		$next = (string) ($this->parent->state->taskList[0]['action'] ?? '');
		if ($next === '' || $next === 'finishRestore') {
			$this->parent->state->restoreNotice = 'nothing';
			message::info('Nothing selected.');
			$this->updateView('lightbox');
			break;
		}
		message::confirm('Preparing restore.');
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'extractMysqldump':
		if ($this->parent->state->cancel) {
			break;
		}
		$zip = new ZipArchive();
		$zip->open($backupdirpathNative . $this->parent->state->spanbasename . '-0.zip');
		$mysqldump = $zip->getFromName('mysqldump_all.sql');
		$mysqldumpPatched = preg_replace('~((,\n\s*)CONSTRAINT[^\n]*)|(CONSTRAINT[^\n]*)~', '', $mysqldump); // remove CONSTRAINTS
		fs::file_put_contents($tmpdirpath . 'mysqldump_all.sql', $mysqldumpPatched);
		$zip->close();
		$this->parent->state->taskListNextIndex++;
		message::confirm('Database extracted.');
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'extractSettings':
	case 'extractEnvironment':
		if ($this->parent->state->cancel) {
			break;
		}
		$prefix = $this->action === 'extractSettings' ? 'settings/' : 'config/';
		$label = $this->action === 'extractSettings' ? 'Settings' : 'Environment';
		if (!$this->parent->restoreGroup($prefix)) {
			message::error($label . ' could not be restored.');
			$this->parent->log('ERROR ' . $label . ' restore failed');
			break;
		}
		$this->parent->state->taskListNextIndex++;
		message::confirm($label . ' restored.');
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'extractMediaDir':
		if ($this->parent->state->cancel) {
			break;
		}
		$this->parent->state->restoreMediaFolder = 1; // swap media folders on finishBackup
		$mediaDir = $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['value'];
		$mediaZipName = $mediaDir . '.zip';
		$zipFileName = null;
		for ($part = 0; ; $part++) {
			$candidate = $backupdirpathNative . $this->parent->state->spanbasename . '-' . $part . '.zip';
			if (!is_file($candidate)) {
				break;
			}
			$probe = new ZipArchive();
			if ($probe->open($candidate) !== true) {
				continue;
			}
			$located = $probe->locateName($mediaZipName);
			$probe->close();
			if ($located !== false) {
				$zipFileName = $candidate;
				break;
			}
		}
		if ($zipFileName === null) {
			message::error('Media files were not found in the backup.');
			$this->parent->log('ERROR media zip missing: ' . $mediaZipName);
			break;
		}
		$zip = new ZipArchive();
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
		message::confirm('Media files extracted.');
		$this->updateView('lightbox');
		http::refresh('./', $this->parent->state->taskList[$this->parent->state->taskListNextIndex]['action']);
		break;
	case 'finishRestore':
		if ($this->parent->state->cancel) {
			break;
		}
		if (\Systopic\System\Panels\Root\Cms\Backup\Panel::chosen($this->parent->state, 'restoreDatabase', true)) {
			if (!fs::isFile($mysqlfile)) {
				message::error('The database dump is missing.');
				break;
			}
			$restore = new Restore($this->scope->connection);
			$restore->dropForeignKeys();
			foreach ($restore->run((string) fs::file_get_contents($mysqlfile)) as $error) {
				message::error('Database error during restore: ' . $error);
			}
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
				message::error('The media folder could not be replaced.');
				$this->parent->log("rename('$tmpdirpath' . 'media/', '$mediadirpath') failed");
				break;
			}
			message::confirm('Media files restored.');
		}
		$this->parent->log("Backup Restored");
		$this->parent->state->taskListNextIndex++;

		$this->updateView('lightbox');
		if (\Systopic\System\Auth\Session::isRecovery()) {
			// the installation has its accounts now: the setup link is spent
			\Systopic\System\Setup\Setup::spendToken(fs::toNative((string) fs::$projectRoot));
			message::confirm('Restore complete. Sign in again.');
			http::redirect(http::$root . 'cms/logout');
		} else {
			message::confirm('Restore complete.');
			http::refresh('./', 'clearTempfiles');
		}
		break;
	case 'clearTempfiles':
		if ($this->parent->state->cancel) {
			break;
		}
		fsFile::get($mysqlfile)->unlink();
		fsDir::get($replacedmediadirpath)->unlink(TRUE);
		fsDir::get($tmpdirpath)->unlink(TRUE);
		clearstatcache();
		if (fs::fileExists($tmpdirpath) || fs::fileExists($replacedmediadirpath)) {
			message::error('Temporary files could not be removed.');
		} else {
			message::confirm('Temporary files cleared.');
		}
		$this->updateView('lightbox');
		break;
}
