<?php

#[AllowDynamicProperties]
class mediaImage {

	static function parseAspectRatios(&$image_format) {
		if (!isset($image_format['aspect_ratios'])) {
			$image_format['aspect_ratios'] = array();
		} elseif (is_string($image_format['aspect_ratios'])) {
			$ars = explode(';', $image_format['aspect_ratios']);
			$image_format['aspect_ratios'] = array();
			$image_format['default_aspect_ratio'] = $ars[0];
			foreach ($ars as $ar) {
				$image_format['aspect_ratios'][$ar] = substr($ar, 0, strpos($ar, '/')) / substr($ar, 1 + strpos($ar, '/'));
			}
		}
	}

	static function getCanvasSize($src_width, $src_height, $format, $display_scale = 1) {
		global $image_formats;
		if (empty($image_formats) || !is_array($image_formats)) {
			$image_formats = mediaFormats::get();
		}
		$src_width = max(1, (float) $src_width);
		$src_height = max(1, (float) $src_height);
		$formatConfig = $image_formats[$format] ?? [];
		if (isset($formatConfig['megapixels']) && (float) $formatConfig['megapixels'] > 0) {
			$megapixels = 1E+6 * (float) $formatConfig['megapixels'];
			$cnv_width = sqrt($src_width * $megapixels / $src_height);
			$cnv_height = $cnv_width * $src_height / $src_width;
		} else {
			$targetWidth = isset($formatConfig['width']) ? (float) $formatConfig['width'] : 0;
			$targetHeight = isset($formatConfig['height']) ? (float) $formatConfig['height'] : 0;
			if ($targetWidth <= 0 && $targetHeight <= 0) {
				$cnv_width = $src_width;
				$cnv_height = $src_height;
			} elseif ($targetHeight <= 0) { // flexible height
				$cnv_width = $targetWidth;
				$cnv_height = $targetWidth * $src_height / $src_width;
			} elseif ($targetWidth <= 0) { // flexible width
				$cnv_height = $targetHeight;
				$cnv_width = $targetHeight * $src_width / $src_height;
			} else {
				$cnv_width = $targetWidth;
				$cnv_height = $targetHeight;
			}
		}
		$cnv_width = max(1, (float) $cnv_width);
		$cnv_height = max(1, (float) $cnv_height);
		$return = array(
			round($display_scale * $cnv_width, 1),
			round($display_scale * $cnv_height, 1)
		);
		return $return;
	}

	/**
	 * A still from a video, as jpeg bytes.
	 *
	 * fsFileOperator_ffmpeg pipes the frame straight out of ffmpeg, so nothing
	 * is written on the way - Imagick reads the blob below. The operator is
	 * destroyed right after: the frame is needed once, for the alias.
	 */
	protected static function extractVideoFrame($videoPath, &$msg): ?string {
		/** @var fsFileOperator_ffmpeg $operator */
		$operator = fsFile::get($videoPath)->getOperator('ffmpeg');
		$operator->render('jpg');
		$blob = $operator->hasResult() ? $operator->getContents() : null;
		if ($blob === null) {
			$msg .= 'no video frame: ' . $operator->lastError;
		}
		$operator->cleanup();
		$operator->destroy();
		return $blob;
	}

	static function createImageFormat($media_id, $format, $original_filename, $filename, $fileexists = TRUE, $msg = '') {
		global $image_formats;
		$orig_width = 0;
		$orig_height = 0;
		if (empty($image_formats) || !is_array($image_formats)) {
			$image_formats = mediaFormats::get();
		}
		if (!isset($image_formats[$format]) || !is_array($image_formats[$format])) {
			$image_formats[$format] = ['megapixels' => 1];
		}
		$image_format = &$image_formats[$format];
		self::parseAspectRatios($image_format);
		$defaultAspectRatio = isset($image_format['default_aspect_ratio']) ? $image_format['aspect_ratios'][$image_format['default_aspect_ratio']] : FALSE;
		//$filename = pathinfo($original_filename, PATHINFO_BASENAME);
		//$filetype = pathinfo($original_filename, PATHINFO_EXTENSION); // replaced 2019 01
		$filetype = pathinfo($filename, PATHINFO_EXTENSION);
		//echo $filetype;
		/*		 * **************************** GET CROP ENTRY ************************** */
		// medium and crop through the ORM (Queries\MediaCrop), in the flat shape this code computes with
		$scope = \Systopic\Db\Scope::default();
		$mediacrop = \Systopic\System\Queries\MediaCrop\MediaCrop::record($scope, (int) $media_id, (string) $format);

		if (!$mediacrop) {
			throw new \RuntimeException($msg . " no media record for id $media_id");
		}

		if (empty($mediacrop->media_crop_id) || $mediacrop->media_crop_width == 0 || $mediacrop->media_crop_height == 0) { // checking for invalid params and reset
			$mediacrop->media_crop_top = 0.5;
			$mediacrop->media_crop_left = 0.5;
			$mediacrop->media_crop_width = 1;
			$mediacrop->media_crop_height = 1;
			$mediacrop->media_crop_auto = 1;
		}
		//pX($mediacrop);
		$alias_filename = fs::$siteRoot . 'var/formats/alias/' . \Systopic\System\Tables\Medias\Files::idPath((int) $media_id) . $filename;

		$type = preg_replace([
			'~^.*/~', // remove e.g. image/
			'~\+.*~'  // remove +XML from SVG
				], '', strtolower($mediacrop->media_mimetype));
		$typeSupported = in_array($type, [
			'jpeg',
			'jpg',
			'webp',
			'png',
			'pdf'
		]);

		// A video has no page to read. It gets a still instead - extracted
		// further down, where the alias is built, so an existing alias costs
		// no ffmpeg run. From here on the source behaves like the jpg it will
		// be. Without this every uploaded video died on 'Support mp4:FALSE'.
		$isVideo = str_starts_with((string) $mediacrop->media_mimetype, 'video/');
		$videoFrameBlob = null;
		if ($fileexists && $isVideo) {
			$type = 'jpg';
			$typeSupported = TRUE;
		}

		if (!$fileexists || !$typeSupported) {
			$msg .= "Support $type:" . ($typeSupported ? 'TRUE' : 'FALSE');
			throw new \RuntimeException($msg);
		}

		$lay_image = new Imagick();

		if ($fileexists && $typeSupported) {
			/*			 * **************************** GENERATE OR LOAD ALIAS ************************** */
			if (!fs::fileExists($alias_filename) || isset($_GET['rebuild'])) {
				if ($isVideo) {
					$videoFrameBlob = self::extractVideoFrame($original_filename, $msg);
					if ($videoFrameBlob === null) {
						throw new \RuntimeException($msg);
					}
				}
				$aliasMegapixels = (float) ($image_formats['alias']['megapixels'] ?? 0);
				if ($aliasMegapixels <= 0) {
					$aliasMegapixels = (float) ($image_format['megapixels'] ?? 2);
				}
				$megapixels = 1E+6 * max(0.1, $aliasMegapixels);

			if (in_array($type, ['pdf', 'jpg', 'jpeg'])) {
				$lay_image->setBackgroundColor(new ImagickPixel('white'));
			} else {
				$lay_image->setBackgroundColor(new ImagickPixel('transparent'));
			}

				if ($type === 'pdf') {
					// $lay_image->readImage($original_filename); // very slow - generates images of all pages
				$tempfile = fs::$siteRoot . 'var/pdf.tpm.webp';
				$tempfileNative = fs::toNative($tempfile);
				$originalNative = fs::toNative($original_filename);
				$info = str::parseYAML(shell_exec("identify -verbose \"{$originalNative}[0]\""));
				list($wInch, $hInch) = explode('x', $info['Image']['Print size']['value']);
				$inch2px = sqrt($megapixels / $wInch / $hInch);
				shell_exec("convert -density $inch2px \"{$originalNative}[0]\" \"{$tempfileNative}\"");
				$lay_image->readImage($tempfileNative);
					fs::unlinkFile($tempfile);
			} elseif ($videoFrameBlob !== null) {
				$lay_image->readImageBlob($videoFrameBlob); // the still never hits the disk
				$videoFrameBlob = null;
			} else {
				$lay_image->readImage(fs::toNative($original_filename));
			}
				if ($filetype === 'png') {
					$lay_image->setImageFormat("png24"); // ?? bei logos versucht imagick umzuwandeln ??
					$lay_image->setImageCompressionQuality(0);
					$lay_image->setImageDepth(8);
				} else {
					$lay_image->setformat($filetype);
				}
				$lay_image->setIteratorIndex(0);
				$orig_width = max(1, (int) $lay_image->getImageWidth());
				$orig_height = max(1, (int) $lay_image->getImageHeight());
//		if ( $orig_width * $orig_height > $megapixels * 1.5 ){ // für später: alias nur erzeugen, wenn original zu gross
				$src_width = sqrt($orig_width * $megapixels / $orig_height);
				$src_height = $src_width * $orig_height / $orig_width;
				$lay_image->setResourceLimit(Imagick::RESOURCETYPE_TIME, (int) 30E+6); // microseconds
				if ($type !== 'pdf') {
					$aliasWidth = max(1, (int) round($src_width));
					$aliasHeight = max(1, (int) round($src_height));
					$lay_image->resizeImage($aliasWidth, $aliasHeight, Imagick::FILTER_SINC, 1);
				}
				\Systopic\System\Tables\Medias\Files::writeFormat($lay_image, (int) $media_id, 'alias', $filename);
//		}
		} else {
			$lay_image->readImage(fs::toNative($alias_filename));
		}
			$originalCompose = $lay_image->getImageCompose();

			$src_width = $lay_image->getImageWidth();
			$src_height = $lay_image->getImageHeight();
			/*			 * **************************** AUTO DEFINE LIVE AREA ************************** */

			if ($mediacrop->media_background_type == '') { // Objekte auf dem Bild noch nicht markiert?
				$trim_image = clone $lay_image;
				//		@ $trim_image = $lay_image->clone();
				$trim_image->trimImage(1); // value 0-65535 //1000
				$imagePage = $trim_image->getImagePage();
				list($trim_x, $trim_y) = array($imagePage['x'], $imagePage['y']);
				$trim_image->setImagePage(0, 0, 0, 0);
				list($trim_width, $trim_height) = array($trim_image->width, $trim_image->height);
				$trim_image->clear();
				unset($trim_image);

				$autotrim = (($trim_width * $trim_height) / ($src_width * $src_height)) < 0.98 && $trim_width != 0 && $trim_height != 0;
				$pixel = $lay_image->getImagePixelColor(1, 1);
				$color = $pixel->getColor();
				$colordistancetowhite = calculate::getColorDistance($color);

				$media_bg_type = $autotrim ? sprintf("#%02X%02X%02X", $color['r'], $color['g'], $color['b']) : 'image';

				if ($autotrim && $colordistancetowhite < 100) {
					$media_bg_type = 'transparent';
				if (in_array($type, ['jpg', 'jpeg', 'pdf'])) {
					$media_bg_type = 'white';
				}
				}
				$mediacrop->media_livearea_width = $trim_width / $src_width;
				$mediacrop->media_livearea_height = $trim_height / $src_height;
				$mediacrop->media_livearea_left = $trim_x / $src_width + $mediacrop->media_livearea_width / 2;
				$mediacrop->media_livearea_top = $trim_y / $src_height + $mediacrop->media_livearea_height / 2;
				$mediacrop->media_livearea_width = 1;
				$mediacrop->media_livearea_height = 1;
				$mediacrop->media_livearea_left = 1 / 2;
				$mediacrop->media_livearea_top = 1 / 2;
				$mediacrop->media_background_type = $media_bg_type;
				[$mediaRow] = \Systopic\System\Queries\MediaCrop\MediaCrop::of($scope, (int) $media_id, (string) $format);
				if ($mediaRow !== NULL) {
					$dec = [\Systopic\System\Tables\MediaCrops\Operator::class, 'decimal'];
					$mediaRow->liveareaLeft = $dec((float) $mediacrop->media_livearea_left);
					$mediaRow->liveareaTop = $dec((float) $mediacrop->media_livearea_top);
					$mediaRow->liveareaWidth = $dec((float) $mediacrop->media_livearea_width);
					$mediaRow->liveareaHeight = $dec((float) $mediacrop->media_livearea_height);
					$mediaRow->backgroundType = $media_bg_type;
					// the size is only known when the alias was made just now; the
					// legacy wrote 0 x 0 into the row otherwise
					if ($orig_width > 0 && $orig_height > 0) {
						$mediaRow->width = $orig_width;
						$mediaRow->height = $orig_height;
					}
					$scope->save($mediaRow);
				}
			}
			/*			 * **************************** INSERT FORMATS ENTRY IF NEW ************************** */

			if (empty($mediacrop->media_crop_id)) { // leeren eintrag erstellen
				$mediacrop->media_crop_auto = 1;
				if ($defaultAspectRatio) {
					$mediacrop->media_crop_height = $mediacrop->media_width / $mediacrop->media_height / $defaultAspectRatio;
					$mediacrop->media_livearea_height = $mediacrop->media_crop_height;
					$mediacrop->media_crop_auto = 0;
				}
				[$mediaRow] = \Systopic\System\Queries\MediaCrop\MediaCrop::of($scope, (int) $media_id, (string) $format);
				if ($mediaRow !== NULL) {
					$crop = \Systopic\System\Tables\MediaCrops\Operator::blank(
						$mediaRow, (string) $format, (bool) $mediacrop->media_crop_auto,
						(float) $mediacrop->media_crop_width, (float) $mediacrop->media_crop_height,
					);
					$scope->save($crop);
					$mediacrop->media_crop_id = $crop->id;
				}
			}
			/*			 * **************************** SET OUTPUT CANVAS SIZE ************************** */
			list($cnv_width, $cnv_height) = self::getCanvasSize($src_width * $mediacrop->media_crop_width, $src_height * $mediacrop->media_crop_height, $format);
			if ($mediacrop->media_crop_auto == 1) { // use livearea as preset for crop
				$crop_left = $mediacrop->media_livearea_left;
				$crop_top = $mediacrop->media_livearea_top;
				$crop_width = $mediacrop->media_livearea_width;
				$crop_height = $mediacrop->media_livearea_height;
			} else { // use crop data from media crop entry
				$crop_left = $mediacrop->media_crop_left;
				$crop_top = $mediacrop->media_crop_top;
				$crop_width = $mediacrop->media_crop_width;
				$crop_height = $mediacrop->media_crop_height;
			}
			$safeSrcWidth = max(1, (float) $src_width);
			$safeSrcHeight = max(1, (float) $src_height);
			$safeCnvWidth = max(1, (float) $cnv_width);
			$safeCnvHeight = max(1, (float) $cnv_height);
			$liveareaWidth = max(0.0001, (float) $mediacrop->media_livearea_width);
			$liveareaHeight = max(0.0001, (float) $mediacrop->media_livearea_height);
			$crop_width = max(0.0001, (float) $crop_width);
			$crop_height = max(0.0001, (float) $crop_height);

			$srcRatio = ($safeSrcWidth * $liveareaWidth) / ($safeSrcHeight * $liveareaHeight);
			$cnvRatio = $safeCnvWidth / $safeCnvHeight;
			$scale_axis = ($srcRatio > $cnvRatio) ? 'y' : 'x';
			// fill in (standardoption) -  Die Bezugsachse wird unabhängig vom manuellen Ausschnitt definiert;
			if ($mediacrop->media_background_type != 'image' && $mediacrop->media_crop_auto == 1) {
				// fit in - Achsen tauschen bei Freistellern und einfarbigem Hintergrund
				$scale_axis = ($scale_axis == 'x') ? 'y' : 'x'; // fit in achsen tauschen
			}
			$format_scale = ($scale_axis == 'y') ? $safeCnvHeight / $safeSrcHeight : $safeCnvWidth / $safeSrcWidth;
			$crop_scale = ($scale_axis == 'y') ? 1 / $crop_height : 1 / $crop_width;

			/*			 * **************************** UPDATE FORMATS ENTRY ************************** */

			if ($mediacrop->media_crop_auto == 1) {
				[, $crop] = \Systopic\System\Queries\MediaCrop\MediaCrop::of($scope, (int) $media_id, (string) $format);
				if ($crop !== NULL) {
					$scope->save(\Systopic\System\Tables\MediaCrops\Operator::computed(
						$crop, (float) $crop_left, (float) $crop_top, (float) $crop_width, (float) $crop_height,
					));
				}
			}

			$lay_width = $src_width * $format_scale * $crop_scale;
			$lay_height = $src_height * $format_scale * $crop_scale;

			$targetLayWidth = max(1, (int) round($lay_width));
			$targetLayHeight = max(1, (int) round($lay_height));
			$lay_image->resizeImage($targetLayWidth, $targetLayHeight, Imagick::FILTER_SINC, 1);
		} else {
			//list($cnv_width, $cnv_height) = self::getCanvasSize(800, 600, $format); // durch db-werte ersetzen
			list($cnv_width, $cnv_height) = self::getCanvasSize(max(400, $mediacrop->media_width), max(300, $mediacrop->media_height), $format);
		}

		$cnv_image = new Imagick();
		if ($fileexists && $typeSupported) { //place layer on canvas
			$pixel = new ImagickPixel("rgba(255,255,255,0)");
			$cnv_image->newImage((int) round($cnv_width), (int) round($cnv_height), $pixel);
			if (isset($lay_image->getImageProperties()['icc:description']) && $lay_image->getImageProperties()['icc:description'] == 'Adobe RGB (1998)') {
				$icc_rgb = fs::file_get_contents(fs::$sysRoot . 'class/media/image/profiles/AdobeRGB1998.icc');
				$cnv_image->profileImage('icc', $icc_rgb);
				$cnv_image->setImageColorSpace(Imagick::COLORSPACE_SRGB);
			}
			$compoSiteWidth = ($cnv_width / 2) - ($lay_width / 2) + (0.5 - $crop_left) * $lay_width;
			$cnv_image->compositeImage($lay_image, $originalCompose, (int) round($compoSiteWidth), (int) round(($cnv_height / 2) - ($lay_height / 2) + ((0.5 - $crop_top) * $lay_height)));
		}
		$cnv_image->setImageFormat($filetype);
		if ($filetype == 'png') {
			$cnv_image->setImageFormat("png24"); // ?? bei logos versucht imagick umzuwandeln ??
			$cnv_image->setImageCompressionQuality(0);
			$cnv_image->setImageDepth(8);
		} else if ($filetype == 'jpg') {
			$cnv_image->setImageCompressionQuality(100);
		}
		return ($cnv_image);
	}
}
