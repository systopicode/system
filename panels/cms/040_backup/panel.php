<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Backup;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

/**
 * Backup panel — manages database and media backups.
 *
 * Migrated from the legacy class backup_module (module.php).
 */
class Panel extends CmsPanel
{
	public bool $bequests = true;
	public $icon = 'fa-regular fa-arrow-rotate-left';
	public $infoFilename = 'info.txt';
	public $dumpFilename = 'mysqldump_all.sql';

	/**
	 * Cached list of backup files for the table view.
	 * Populated in onLoadSelected() so templates can read $this->backupfilestable
	 * without re-globbing the backup directory on every render.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $backupfilestable = [];

	public function buildGranted(): bool
	{
		if ($this->name === 'backup') {
			return \Systopic\System\Auth\Session::hasOneRole('editor', 'textEditor') || \Systopic\System\Auth\Session::isRecovery();
		}
		return \Systopic\System\Auth\Session::hasOneRole('editor');
	}

	function onLoadThisClass(): void
	{
		$this->backupdirpath        = \fs::$siteRoot . 'var/backups/';
		$this->tmpdirpath           = $this->backupdirpath . 'tmp/';
		$this->mysqlfile            = $this->tmpdirpath . 'mysqldump_all.sql';
		$this->mediadirpath         = \fs::$siteRoot . 'var/original/';
		$this->replacedmediadirpath = \fs::$siteRoot . 'var/original_replaced/';
		$this->logfile              = \fsFile::get($this->backupdirpath . 'backup.log');
		if (!$this->logfile->exists) {
			$this->logfile->touch();
			$this->log('file created');
		}
		if ($this->name === 'backup') {
			$this->backupfilestable = $this->getBackupfilestable($this->backupdirpath);
		}
	}

	function getDefaultState(): object
	{
		return $this->name === 'backup'
			? (object) [
				'backupNotes'       => '',
				'spanindex'         => 0,
				'backupFiles'       => false,
				'spanbasename'      => '',
				'restoreMediaFolder' => false,
				'date'              => '',
				'taskListNextIndex' => 0,
				'taskList'          => [],
				'cancel'            => false,
			]
			: (object) [];
	}

	function log(string $info): void
	{
		global $scripttimeStart;
		[$usec, $sec] = explode(' ', microtime());
		$time   = round(((float) $usec + (float) $sec) - $scripttimeStart, 3);
		$memory = round(memory_get_usage() / 1024 / 1024, 2);
		$this->logfile->append("$info - {$time}s {$memory}MB\n");
	}

	function getBackupfilestable(string $backupdirpath): array
	{
		$backupfilestable = [];
		$files = \fs::glob($backupdirpath . '*-0.zip');
		if (!empty($files)) {
			foreach (array_reverse($files) as $backupfile) {
				$backupfileNative = $backupfile;
				$backupfile       = \fs::toInternal($backupfile);
				preg_match('/^(.*\/backups\/((.{10})--(.{8})-(.*)))-0\.zip$/', $backupfile, $m);
				$timefinished = str_replace('-', ':', $m[4]);
				$spanfiles    = \fs::glob($m[1] . '*.zip');
				$totalsize    = array_sum(array_map('\fs::filesize', $spanfiles));
				$zip          = new \ZipArchive();
				$zip->open($backupfileNative);
				$info_txt = unserialize($zip->getFromName($this->infoFilename));
				$zip->close();
				$durationSeconds = strtotime($m[3] . ' ' . $timefinished) - strtotime($info_txt['created'] ?? '');
				if (!is_int($durationSeconds) || $durationSeconds < 0) {
					continue;
				}
				$dur = new \DateInterval('PT' . $durationSeconds . 'S');
				$backupfilestable[] = [
					'filenamebase'   => $m[2],
					'date'           => $info_txt['created'],
					'timefinished'   => $timefinished,
					'duration'       => $dur->format('%H:%I:%S'),
					'filecount'      => count($spanfiles),
					'totalsize'      => \str::formatbytes($totalsize),
					'stage'          => $info_txt['stage'] ?? '',
					'databasename'   => $m[5],
					'user'           => $info_txt['user'],
					'note'           => $info_txt['settings']->backupNotes ?? '',
					'filesincluded'  => $info_txt['settings']->backupFiles,
					'download'       => $spanfiles,
				];
			}
		}
		return $backupfilestable;
	}

	function removeOldMediaDir(): void
	{
		\fsDir::get($this->backupdirpath . 'tmp/original_old/')->unlink(true);
	}
}
