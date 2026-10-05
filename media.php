<?php

/**
 * Serve / rebuild media under public/var/.
 *
 * 1) A project's own collections first: `<project>/media.php`, when there is
 *    one, returns true for a request it handled (pwk: brands, files, persons,
 *    patchworks). The system knows no project's collections.
 * 2) CMS media (var/original + cms_medias): a format that is missing, or one
 *    asked for with ?rebuild, is made and stored.
 */

ob_start();

$projectMedia = fs::$projectRoot !== null ? fs::toNative((string) fs::$projectRoot . 'media.php') : '';
if ($projectMedia !== '' && is_file($projectMedia) && (include $projectMedia) === true) {
	logroute('media.php: served by the project');
	exit;
}

/* * **************************** LEGACY CMS MEDIA ************************** */
$functions = [];
global $image_formats;
$image_formats = mediaFormats::get();

$parts = explode('/', app::request()->rawpath);
$parts = array_reverse($parts);

$filename = $parts[0];
$filetype = pathinfo($filename, PATHINFO_EXTENSION);
$media_id = (int) ($parts[2] . $parts[1]);
$format = $parts[3];
$original_filename = fs::$siteRoot . 'var/original/' . \Systopic\System\Tables\Medias\Files::idPath($media_id) . $filename;
$fileexists = fs::isFile($original_filename);

if (!$fileexists) {
	$original_filename = substr($original_filename, 0, strrpos($original_filename, '.')); // cutoff extention if converted svg -> png e.g.
	$fileexists = fs::isFile($original_filename);
}

if (!isset($_GET['noheader'])) {
	header("Content-type: image/$filetype");
}

ob_flush();
// Saving a crop is the CMS's business (cms/pages/updateMediaCrop); this file
// only renders — a format that is missing, or one asked for with ?rebuild.
$msg = "filename:$filename\n";
$msg .= "fileexists:" . ($fileexists ? 'TRUE' : 'FALSE') . "\n";
$msg .= "media_id:$media_id\n";
$msg .= "format:$format\n";
$msg .= "filetype:$filetype\n";

if (!isset($image_formats[$format])) {
	$msg .= "!ERROR unknown format '$format'!\n";
	if (isset($image_formats['thumb'])) {
		$format = 'thumb';
	} elseif (!empty($image_formats) && is_array($image_formats)) {
		$format = array_key_first($image_formats);
		$msg .= "fallback format:$format\n";
	} else {
		$image_formats = is_array($image_formats) ? $image_formats : [];
		$image_formats['thumb'] = ['megapixels' => 1];
		$format = 'thumb';
		$msg .= "fallback format:auto-thumb\n";
	}
}
try {
	$cnv_image = mediaImage::createImageFormat($media_id, $format, $original_filename, $filename, $fileexists, $msg);
} catch (\RuntimeException $e) {
	ob_clean();
	header('HTTP/1.0 404 Not Found');
	header('Content-Type: text/html; charset=utf-8');
	p($e->getMessage());
	exit;
}
echo $cnv_image;
if ($fileexists) {
	\Systopic\System\Tables\Medias\Files::writeFormat($cnv_image, $media_id, $format, $filename);
}

$cnv_image->clear();
unset($cnv_image);
