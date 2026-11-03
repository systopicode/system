<?php

#[AllowDynamicProperties]
class mediaEditor {

	static function getImageEditor($media_id, $format_name, $display_width) {
		global $image_formats;
		$image_format = & $image_formats[$format_name];
		mediaImage::parseAspectRatios($image_format);
		// medium and crop through the ORM; a missing or empty crop reads as the
		// centred whole image (the legacy query found no row then and failed)
		$mediacrop = \Systopic\System\Queries\MediaCrop\MediaCrop::record(\Systopic\Db\Scope::default(), (int) $media_id, (string) $format_name);
		if ($mediacrop === NULL) {
			return ['display_scale' => 1, 'html' => ''];
		}

		// the alias (the working copy every format is cut from); the original
		// while there is none yet. Native path: Imagick does not read '/C/…'.
		$idPath = \Systopic\System\Tables\Medias\Files::idPath((int) $media_id);
		$src_filename = "var/formats/alias/$idPath" . $mediacrop->media_filename;
		if (!is_file(fs::toNative(fs::$siteRoot . $src_filename))) {
			$src_filename = "var/original/$idPath" . $mediacrop->media_filename;
		}
		$src_public_filename = http::$root . $src_filename;
		$src_image = new Imagick();
		$src_image->readImage(fs::toNative(fs::$siteRoot . $src_filename));
        $src_image->autoOrient();
		$src_width = $src_image->getImageWidth();
		$src_height = $src_image->getImageHeight();
		$crop_width = $mediacrop->media_crop_width;
		$crop_height = $mediacrop->media_crop_height;
		[$format_width] = mediaImage::getCanvasSize($src_width * $crop_width, $src_height * $crop_height, $format_name, 1);
        $display_scale = $display_width / max(1, $format_width);

		list($cnv_width, $cnv_height) = mediaImage::getCanvasSize($src_width * $mediacrop->media_crop_width, $src_height * $mediacrop->media_crop_height, $format_name, $display_scale);
		$scale_axis = (($src_width * $mediacrop->media_livearea_width) / ($src_height * $mediacrop->media_livearea_height) > $cnv_width / $cnv_height) ? 'y' : 'x';
// fill in (standardoption) -  Die Bezugsachse wird unabhängig vom manuellen Ausschnitt definiert;
		if ($mediacrop->media_background_type != 'image' && $mediacrop->media_crop_auto == 1) {
// fit in - Achsen tauschen bei Freistellern und einfarbigem Hintergrund
			$scale_axis = ($scale_axis == 'x') ? 'y' : 'x';
		}
		$format_scale = ($scale_axis == 'y') ? $cnv_height / $src_height : $cnv_width / $src_width;
		$crop_scale = ($scale_axis == 'y') ? 1 / $crop_height : 1 / $crop_width;

		$alias_width = $src_width * $format_scale * $crop_scale;
		$alias_height = $src_height * $format_scale * $crop_scale;

		$margin_left = round($cnv_width / 2 - $alias_width * $mediacrop->media_crop_left, 1);
		$margin_top = round($cnv_height / 2 - $alias_height * $mediacrop->media_crop_top, 1);

        $savedTune = \Systopic\System\Tables\Medias\Files::tunes((int) $media_id, (string) $mediacrop->media_filename)[$format_name] ?? [];
        $tuneJson = htmlspecialchars(json_encode($savedTune), ENT_QUOTES, 'UTF-8');
        $fixedRatio = mediaFormats::fixedRatio($image_format) ?? '';
        $originalRatio = $src_width / max(1, $src_height);
        $hint = 'Move image · Pinch or scroll to zoom';
        $handles = "<div class='cropHandles'>";
        foreach (['nw','n','ne','e','se','s','sw','w'] as $edge) {
            $handles .= "<button type='button' data-crop-handle='$edge' aria-label='Resize crop $edge'></button>";
        }
        $handles .= '</div>';

		$aspectRatiosSelector = "<div class='aspectRatioSelector'>";
		foreach ($image_format['aspect_ratios'] AS $label => $ratio) {
			$aspectRatiosSelector .= "<a data-aspectratio='$ratio'>$label</a>";
		}
		$aspectRatiosSelector .= '</div>';
		$html = "
		<div style='position:absolute;' class='imageEditor' data-tune='$tuneJson' data-crop-ratio='$fixedRatio' data-original-ratio='{$originalRatio}'>
			<div class='cropFrame' style='width:{$cnv_width}px;height:{$cnv_height}px;position:absolute;overflow:hidden;'>
				$aspectRatiosSelector
				<img class='cropimage' style='margin-left:{$margin_left}px;margin-top:{$margin_top}px;width:{$alias_width}px;height:{$alias_height}px;position:absolute;' src='$src_public_filename' />
				<div class='cropGrid' hidden aria-hidden='true'></div>
			</div>

			<div class='dragimage' style='margin-left:{$margin_left}px;margin-top:{$margin_top}px;width:{$alias_width}px;height:{$alias_height}px;position:absolute'>
				<img style='width:100%;height:100%' src='$src_public_filename' />
			</div>
            $handles

			<div class='cropBar' style='width:{$cnv_width}px;'>
				<span>$hint</span>
				<button type='button' class='button' data-crop-grid aria-label='Grid' title='Grid' aria-pressed='false'><i class='cmsIcon cmsIcon-table' aria-hidden='true'></i></button>
				<button type='button' class='button' data-crop-original aria-label='Original ratio' title='Original ratio'><i class='cmsIcon cmsIcon-aspect-ratio' aria-hidden='true'></i></button>
				<button type='button' class='button' data-crop-reset aria-label='Reset crop' title='Reset crop' disabled><i class='cmsIcon cmsIcon-arrow-rotate-left' aria-hidden='true'></i></button>
				<button type='button' class='button' data-crop_cancel aria-label='Cancel' title='Cancel'><i class='cmsIcon cmsIcon-xmark' aria-hidden='true'></i></button>
				<button type='button' class='button submit' aria-label='Apply' title='Apply'><i class='cmsIcon cmsIcon-check' aria-hidden='true'></i></button>
			</div>
		</div>
	";
		return array(
			'display_scale' => $display_scale,
			'html' => $html,
		);
	}

}
