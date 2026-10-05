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
		$src_width = $src_image->getImageWidth();
		$src_height = $src_image->getImageHeight();
		$crop_width = $mediacrop->media_crop_width;
		$crop_height = $mediacrop->media_crop_height;
		if (isset($image_format['megapixels'])) {
			$format_width = sqrt($mediacrop->media_width * $image_format['megapixels'] * 1000000 / $mediacrop->media_height);
			$display_scale = $display_width / $format_width;
		} else {
			$display_scale = $display_width / $image_format['width'];
		}

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

		$flexHandle = isset($image_format['height']) ? '' : "<div class='flexHandle' style='top:{$cnv_height}px;'></div>";

		$aspectRatiosSelector = "<div class='aspectRatioSelector'>";
		foreach ($image_format['aspect_ratios'] AS $label => $ratio) {
			$aspectRatiosSelector .= "<a data-aspectratio='$ratio'>$label</a>";
		}
		$aspectRatiosSelector .= '</div>';
		$html = "
		<div style='position:absolute;' class='imageEditor'>
			<div style='width:{$cnv_width}px;height:{$cnv_height}px;position:absolute;overflow:hidden;'>
				$aspectRatiosSelector
				$flexHandle
				<img class='cropimage' style='margin-left:{$margin_left}px;margin-top:{$margin_top}px;width:{$alias_width}px;height:{$alias_height}px;position:absolute;' src='$src_public_filename' />
			</div>

			<div class='dragimage' style='margin-left:{$margin_left}px;margin-top:{$margin_top}px;width:{$alias_width}px;height:{$alias_height}px;position:absolute'>
				<img style='width:100%;height:100%' src='$src_public_filename' />
				<div style='right:-2px;top:-2px;'></div>
				<div style='left:-2px;top:-2px;'></div>
				<div style='left:-2px;bottom:-2px;'></div>
				<div style='right:-2px;bottom:-2px;'></div>
			</div>
		</div>
	";
		return array(
			'display_scale' => $display_scale,
			'html' => $html,
		);
	}

}
