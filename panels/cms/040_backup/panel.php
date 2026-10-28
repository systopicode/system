<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Backup;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Settings\EnvironmentFile;
use Systopic\System\Settings\ProjectFile;

/**
 * Backup panel — manages database and media backups.
 *
 * Migrated from the legacy class backup_module (module.php).
 */
class Panel extends CmsPanel
{
	public bool $bequests = true;
	public $icon = 'fa-duotone fa-regular fa-box-archive';
	public ?int $naviOrder = 30;
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

	/** The panel is open to editors and the recovery user — its actions are not. */
	public function actionGranted($action = null)
	{
		if ($this->name !== 'backup') {
			return parent::actionGranted($action);
		}
		return match ($action) {
			'remove', 'clearTempdirs', 'upload' => \Systopic\System\Auth\Session::hasRole('admin'),
			'createDatabase'          => \Systopic\System\Auth\Session::hasRole('recovery') && \Systopic\System\Auth\Session::isRecovery(),
			default                   => parent::actionGranted($action),
		};
	}

	/**
	 * A backup's name as the table links it — a file name in var/backups/,
	 * never a path. null when it is not one.
	 */
	public static function backupName(mixed $name): ?string
	{
		$name = (string) $name;
		return preg_match('~^[\w.-]+$~', $name) && !str_contains($name, '..') ? $name : null;
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
				'backupNotes'        => '',
				'spanindex'          => 0,
				'backupFiles'        => false,
				'backupPageSettings' => true,
				'backupMediaSettings' => true,
				'backupEnvironment'  => true,
				'spanbasename'       => '',
				'restoreName'        => '',
				'restoreDatabase'    => true,
				'restoreMedia'       => false,
				'restorePageSettings' => false,
				'restoreMediaSettings' => false,
				'restoreEnvironment' => false,
				'restoreNotice'      => '',
				'restoreMediaFolder' => false,
				'date'               => '',
				'taskListNextIndex'  => 0,
				'taskList'           => [],
				'cancel'             => false,
			]
			: (object) [];
	}

	/** @var array<string, string> */
	public const CONTENTS = [
		'database' => 'Database',
		'media' => 'Media',
		'pageSettings' => 'Global Settings',
		'mediaSettings' => 'Media Settings',
		'environment' => 'Environment',
	];

	/** File settings, named like their panels. Template settings stay in the database. */
	public const SETTING_LABELS = [
		'page' => 'Global Settings',
		'media' => 'Media Settings',
	];

	public static function settingKey(string $which, bool $restore): string
	{
		return ($restore ? 'restore' : 'backup') . ucfirst($which) . 'Settings';
	}

	/** A switch left untouched counts as its default. 0 from the client is off. */
	public static function chosen(object $state, string $key, bool $default): bool
	{
		if (!property_exists($state, $key)) {
			return $default;
		}
		return $state->$key == 1;
	}

	/**
	 * The switches travel with the start request. A change that has not
	 * landed yet cannot leave the run on the previous choice.
	 */
	public function takePostedSelection(): void
	{
		$form = \http::form();
		if (!is_object($form)) {
			return;
		}
		$patch = new \stdClass();
		foreach ([
			'backupNotes', 'backupFiles', 'backupPageSettings', 'backupMediaSettings', 'backupEnvironment',
			'restoreDatabase', 'restoreMedia', 'restorePageSettings', 'restoreMediaSettings', 'restoreEnvironment',
		] as $key) {
			if (property_exists($form, $key)) {
				$patch->$key = $form->$key;
			}
		}
		if ((array) $patch !== []) {
			$this->updateState($patch);
		}
	}

	/**
	 * What the archive actually holds, from entry names. Nothing is stored
	 * beside the zip.
	 *
	 * @param list<string> $names
	 * @return array<string, bool>
	 */
	public static function contentsFromNames(array $names): array
	{
		$contents = array_fill_keys(array_keys(self::CONTENTS), false);
		foreach ($names as $name) {
			$name = str_replace('\\', '/', $name);
			if ($name === 'mysqldump_all.sql') {
				$contents['database'] = true;
			} elseif (!str_contains($name, '/') && str_ends_with(strtolower($name), '.zip')) {
				$contents['media'] = true;
			} elseif (($which = self::settingWhich($name)) !== '') {
				$contents[$which . 'Settings'] = true;
			} elseif (self::isEnvironmentEntry($name)) {
				$contents['environment'] = true;
			}
		}
		return $contents;
	}

	/** @param array<string, bool> $contents */
	public static function contentLabels(array $contents): string
	{
		$labels = [];
		foreach (self::CONTENTS as $key => $label) {
			if (!empty($contents[$key])) {
				$labels[] = $label;
			}
		}
		return implode(' · ', $labels);
	}

	/**
	 * Page and media settings files. JSON lives in config/; an older project
	 * still has the PHP files next to settings.php.
	 *
	 * @return array<string, string> archive path => source path
	 */
	public function settingsArchiveMap(): array
	{
		$map = [];
		foreach (array_keys(ProjectFile::FILES) as $which) {
			$path = ProjectFile::path($which);
			if ($path !== null) {
				$map['settings/' . basename($path)] = $path;
			}
		}
		return $map;
	}

	/**
	 * Settings files whose switch is on.
	 *
	 * @return array<string, string> archive path => source path
	 */
	public function chosenSettingsArchiveMap(object $state): array
	{
		$map = [];
		foreach ($this->settingsArchiveMap() as $archive => $path) {
			$which = self::settingWhich($archive);
			if ($which !== '' && self::chosen($state, self::settingKey($which, false), true)) {
				$map[$archive] = $path;
			}
		}
		return $map;
	}

	/**
	 * Instance files the environment panel writes. config.json is not one of them.
	 *
	 * @return array<string, string> archive path => source path
	 */
	public function environmentArchiveMap(): array
	{
		$map = [];
		foreach (EnvironmentFile::files() as $file) {
			$base = (string) ($file['file'] ?? '');
			$path = (string) ($file['path'] ?? '');
			if ($path === '' || !is_file($path) || !self::isEnvironmentEntry('config/' . $base)) {
				continue;
			}
			$map['config/' . $base] = $path;
		}
		return $map;
	}

	/**
	 * @return array<string, bool>|null null when that backup is not in the folder
	 */
	public function contentsOfBackup(string $basename): ?array
	{
		if (self::backupName($basename) === null) {
			return null;
		}
		foreach ($this->backupfilestable as $row) {
			if (($row['filenamebase'] ?? '') === $basename && isset($row['contents']) && is_array($row['contents'])) {
				return $row['contents'];
			}
		}
		$spans = \fs::glob($this->backupdirpath . $basename . '*.zip') ?: [];
		if ($spans === []) {
			return null;
		}
		return self::contentsFromNames(self::entryNames($spans));
	}

	/** @param array{action?: string} $task */
	public function restoreTaskKept(array $task): bool
	{
		return match ((string) ($task['action'] ?? '')) {
			'extractMysqldump' => self::chosen($this->state, 'restoreDatabase', true),
			'extractMediaDir' => self::chosen($this->state, 'restoreMedia', false),
			'extractSettings' => self::chosen($this->state, 'restorePageSettings', false)
				|| self::chosen($this->state, 'restoreMediaSettings', false),
			'extractEnvironment' => self::chosen($this->state, 'restoreEnvironment', false),
			default => true,
		};
	}

	/** Write one group from the open backup back to its files. */
	public function restoreGroup(string $prefix): bool
	{
		$basename = self::backupName($this->state->spanbasename ?? '');
		if ($basename === null) {
			return false;
		}
		$wrote = 0;
		foreach (\fs::glob($this->backupdirpath . $basename . '*.zip') ?: [] as $span) {
			$zip = new \ZipArchive();
			if ($zip->open(\fs::toNative($span)) !== true) {
				continue;
			}
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
				if (!str_starts_with($name, $prefix)) {
					continue;
				}
				if ($prefix === 'settings/' && !self::chosen($this->state, self::settingKey(self::settingWhich($name), true), false)) {
					continue;
				}
				$bytes = $zip->getFromIndex($i);
				if (!is_string($bytes)) {
					$zip->close();
					return false;
				}
				if ($prefix === 'settings/' && str_ends_with(strtolower($name), '.php')) {
					$which = self::settingWhich($name);
					if ($which === '' || !ProjectFile::importLegacy($which, $bytes)) {
						$zip->close();
						return false;
					}
					$wrote++;
					continue;
				}
				if ($prefix === 'settings/' && str_ends_with(strtolower($name), '.json')) {
					try {
						$decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
					} catch (\Throwable) {
						$zip->close();
						return false;
					}
					if (!is_array($decoded)) {
						$zip->close();
						return false;
					}
				}
				$target = self::restoredTarget($name);
				if ($target === null) {
					continue;
				}
				if (!$this->replaceFile($target, $bytes)) {
					$zip->close();
					return false;
				}
				$wrote++;
			}
			$zip->close();
		}
		return $wrote > 0;
	}

	public static function restoredTarget(string $entry): ?string
	{
		$entry = str_replace('\\', '/', $entry);
		if (self::isSettingsEntry($entry)) {
			if (str_ends_with(strtolower($entry), '.php')) {
				return null;
			}
			$which = self::settingWhich($entry);
			return $which === '' ? null : ProjectFile::jsonPath($which);
		}
		if (!self::isEnvironmentEntry($entry)) {
			return null;
		}
		$dir = EnvironmentFile::directory();
		if ($dir === null) {
			$root = rtrim(\fs::toNative((string) \fs::$projectRoot), '\\/');
			if ($root === '' || !is_dir($root)) {
				return null;
			}
			$dir = $root . DIRECTORY_SEPARATOR . 'config';
		}
		return rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . basename($entry);
	}

	/** @param list<string> $spans */
	private static function entryNames(array $spans): array
	{
		$names = [];
		foreach ($spans as $span) {
			$zip = new \ZipArchive();
			if ($zip->open(\fs::toNative($span)) !== true) {
				continue;
			}
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$name = $zip->getNameIndex($i);
				if (is_string($name)) {
					$names[] = str_replace('\\', '/', $name);
				}
			}
			$zip->close();
		}
		return $names;
	}

	private static function isSettingsEntry(string $name): bool
	{
		return self::settingWhich($name) !== '';
	}

	private static function settingWhich(string $name): string
	{
		foreach (array_keys(ProjectFile::FILES) as $which) {
			foreach (ProjectFile::archiveBasenames($which) as $basename) {
				if ($name === 'settings/' . $basename) {
					return $which;
				}
			}
		}
		return '';
	}

	private static function isEnvironmentEntry(string $name): bool
	{
		return preg_match('#^config/(?:[A-Za-z0-9][A-Za-z0-9._-]*\.config\.json|config\.local\.json)$#', $name) === 1;
	}

	private function replaceFile(string $path, string $bytes): bool
	{
		$dir = dirname($path);
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			return false;
		}
		$temp = $path . '.' . getmypid() . '.tmp';
		if (file_put_contents($temp, $bytes) === false) {
			return false;
		}
		if (is_file($path)) {
			$perms = fileperms($path);
			if ($perms !== false) {
				@chmod($temp, $perms & 0777);
			}
		}
		if (!rename($temp, $path)) {
			@unlink($temp);
			return false;
		}
		return true;
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
				$backupfile = \fs::toInternal($backupfile);
				if (!preg_match('/^(.*\/backups\/((.{10})--(.{8})-(.*)))-0\.zip$/', $backupfile, $m)) {
					continue;
				}
				$timefinished = str_replace('-', ':', $m[4]);
				$spanfiles    = \fs::glob($m[1] . '*.zip') ?: [];
				$totalsize    = array_sum(array_map('\fs::filesize', $spanfiles));
				$names        = [];
				$info_txt     = false;
				foreach ($spanfiles as $span) {
					$zip = new \ZipArchive();
					if ($zip->open(\fs::toNative($span)) !== true) {
						continue;
					}
					if (str_ends_with(\fs::toInternal($span), '-0.zip')) {
						$raw = $zip->getFromName($this->infoFilename);
						$info_txt = is_string($raw) ? @unserialize($raw) : false;
					}
					for ($i = 0; $i < $zip->numFiles; $i++) {
						$name = $zip->getNameIndex($i);
						if (is_string($name)) {
							$names[] = str_replace('\\', '/', $name);
						}
					}
					$zip->close();
				}
				if (!is_array($info_txt) || !isset($info_txt['created'])) {
					continue;
				}
				$contents = self::contentsFromNames($names);
				$durationSeconds = strtotime($m[3] . ' ' . $timefinished) - strtotime($info_txt['created']);
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
					'contents'       => $contents,
					'contentsLabel'  => self::contentLabels($contents),
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

	/**
	 * One slice of an upload. The browser sends the zip in parts, the way the
	 * media library does, and only the last part is checked and filed.
	 * Null means this slice is in, or the finished zip is filed. A string is
	 * why it was refused.
	 */
	public function acceptBackupSpan(string $original, int $spanIndex, bool $last, string $binary): ?string
	{
		$safe = \str::getSafeName($original);
		if ($safe === '' || $safe === '.') {
			$safe = 'backup.zip';
		}
		if (!str_ends_with(strtolower($safe), '.zip')) {
			$safe .= '.zip';
		}
		$dir = $this->backupdirpath . 'tmp/incoming/';
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			return 'The backup folder is missing.';
		}
		$tmp = $dir . $safe;
		if (file_put_contents($tmp, $binary, $spanIndex === 0 ? 0 : FILE_APPEND) === false) {
			return "$original could not be stored.";
		}
		if (!$last) {
			return null;
		}
		$problem = $this->installBackupFile($tmp, $original);
		if ($problem !== null) {
			unlink($tmp);
		}
		return $problem;
	}

	/** Move a finished zip into var/backups/. A string is why it was refused. */
	public function installBackupFile(string $tmp, string $original): ?string
	{
		$given = basename(str_replace('\\', '/', $original));
		$span = [];
		$named = preg_match('/^(\d{4}-\d{2}-\d{2}--\d{2}-\d{2}-\d{2}-[\w.-]+)-(\d+)\.zip$/', $given, $span) === 1;
		$part = $named ? (int) $span[2] : 0;

		$zip = new \ZipArchive();
		if ($zip->open($tmp) !== true) {
			return "$original is not a zip file.";
		}
		if ($part === 0) {
			$info = $zip->getFromName('info.txt');
			$dump = $zip->locateName('mysqldump_all.sql');
			$zip->close();
			if (!is_string($info) || !str_starts_with($info, 'a:') || $dump === false) {
				return "$original is not a backup.";
			}
		} else {
			$zip->close();
		}

		if ($named) {
			$destName = $given;
		} else {
			$database = preg_replace('/[^\w.-]+/', '', (string) DB_NAME) ?: 'database';
			$destName = date('Y-m-d--H-i-s-') . $database . '-0.zip';
		}
		if (self::backupName($destName) === null || !str_ends_with($destName, '.zip')) {
			return "$original has a name this backup folder does not accept.";
		}

		$dest = $this->backupdirpath . $destName;
		if (is_file($dest)) {
			return "A backup named $destName is already here.";
		}
		if (!is_dir($this->backupdirpath) && !mkdir($this->backupdirpath, 0775, true) && !is_dir($this->backupdirpath)) {
			return 'The backup folder is missing.';
		}
		if (!rename($tmp, $dest)) {
			return "$original could not be stored.";
		}
		return null;
	}
}
