<?php
/**
 * cms/tools/maintenance/media — format cache, crop parameters, file
 * permissions, mime types and sizes.
 *
 * `removeCropCache` called `clearImageCrops()`, which exists nowhere: the
 * button ended in a fatal error. It now empties var/formats/<format>/
 * (`Files::clearFormat()`, 'total' = all formats).
 *
 * @var \Systopic\System\Panels\PanelNode $this
 */

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Queries\MediaMaintenance\MediaMaintenance;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\System\Tables\Medias\Files as MediaFiles;

// cms/tools extends PanelNode directly and declares no scope (orm-migration.md §1)
$scope = Scope::default();
$html = '';
switch ($this->action) {
	case 'removeCropCache':
		$format = (string) http::params('value');
		$count = MediaFiles::clearFormat($format);
		message::confirm("$format: $count format folder(s) removed.");
		if (http::params('total')) {
			client::callFunction('renderProgress', [
				'percentage' => http::params('index') / http::params('total'),
				'label' => 'Image Crops Cache cleared',
			]);
		}
		break;

	case 'removeCropParams':
		// past the unit of work: nobody holds these rows — invalidated by hand
		$format = (string) http::params('value');
		$table = Schema::tableNameOf(Crop::class);
		$count = $scope->connection->run(
			"DELETE FROM $table WHERE media_crop_format = ? OR ? = 'total'", [$format, $format],
		)->rowCount();
		$scope->results->invalidate([$table]);
		message::confirm("$format: $count Entries removed");
		break;

	case 'showMediaWithInsufficientPermissions':
	case 'fixMediaPermissions':
		$fix = $this->action === 'fixMediaPermissions';
		$siteRoot = rtrim((string) fs::$siteRoot, '/');
		$roots = [
			$siteRoot . '/var/original',
			$siteRoot . '/var/formats',
		];
		$rows = [];
		$fixed = 0;
		foreach ($roots as $root) {
			if (!fs::isDir($root)) {
				continue;
			}
			$found = fs::globrec($root . '/*') ?: [];
			foreach ($found as $filepath) {
				$native = fs::toNative($filepath);
				if (!is_file($native)) {
					continue;
				}
				$mode = fileperms($native) & 0777;
				// Apache (psacln) needs group- or other-read. 0600 fails with 403.
				if (($mode & 0044) !== 0) {
					continue;
				}
				$rel = ltrim(str_replace('\\', '/', substr($filepath, strlen($siteRoot))), '/');
				$row = [
					'file' => $rel,
					'permissions' => sprintf('%04o', $mode),
				];
				if ($fix) {
					fs::chmod($filepath, 0664);
					clearstatcache(true, $native);
					$newMode = fileperms($native) & 0777;
					$row['permissions'] = sprintf('%04o', $mode) . ' → ' . sprintf('%04o', $newMode);
					$fixed++;
				}
				$rows[] = $row;
			}
		}
		if ($fix) {
			message::confirm($fixed . ' files set to 0664');
		}
		$html = $rows
			? (string) htmlTable::create('keycolumn')->rows($rows)
			: '<p>No files with insufficient permissions.</p>';
		client::replaceInner('.showPermissionsContainer', "<br/>$html");
		break;

	case 'showMediaWithoutMimetype':
	case 'updateMediaMimetype':
		$show = $this->action === 'showMediaWithoutMimetype';
		$rows = [];
		$changed = [];
		foreach (MediaMaintenance::lacking($scope, 'mimetype', $show ? 100 : 2000) as $media) {
			$result = (string) $media->mimetype;
			if (is_file(MediaFiles::originalPath($media))) {
				$before = $media->mimetype;
				MediaFiles::describe($media);
				$result = ($show ? 'new: ' : 'upd: ') . $media->mimetype;
				if ($show) {
					$media->mimetype = $before;   // only looked
				} else {
					$changed[] = $media;
				}
			}
			$rows[] = ['media_id' => $media->id, 'media_filename' => $media->filename, 'media_mimetype' => $result];
		}
		if ($changed !== []) {
			$scope->save(...$changed);
		}
		client::replaceInner('.showMimeContainer', '<br/>' . htmlTable::create('keycolumn')->rows($rows));
		break;

	case 'showMediaWithoutSize':
	case 'updateMediaSize':
		$show = $this->action === 'showMediaWithoutSize';
		$rows = [];
		$changed = [];
		foreach (MediaMaintenance::lacking($scope, 'size', $show ? 100 : 2000) as $media) {
			$resultW = $media->width ?? '';
			$resultH = $media->height ?? '';
			$path = MediaFiles::originalPath($media);
			$size = is_file($path) ? @getimagesize($path) : FALSE;
			if ($size) {
				$prefix = $show ? 'new: ' : 'upd: ';
				[$resultW, $resultH] = [$prefix . $size[0], $prefix . $size[1]];
				if (!$show) {
					[$media->width, $media->height] = [(int) $size[0], (int) $size[1]];
					$changed[] = $media;
				}
			}
			$rows[] = ['media_id' => $media->id, 'media_filename' => $media->filename, 'media_width' => $resultW, 'media_height' => $resultH];
		}
		if ($changed !== []) {
			$scope->save(...$changed);
		}
		client::replaceInner('.showMediaContainer', '<br/>' . htmlTable::create('keycolumn')->rows($rows));
		break;
}
