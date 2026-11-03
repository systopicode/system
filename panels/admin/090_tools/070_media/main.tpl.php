<h1>Media repair</h1>
<p class="tagsHint">Files and the sizes made from them.</p>
<?= \message::flush() ?>
<div class="widgets">
	<section class="widget">
		<header><h2>Permissions</h2></header>
		<main>
			<p class="tagsHint">Missing read permissions can cause broken media on the website. Repair sets the affected original and generated files to permissions 0664.</p>
			<div class="mediaResult showPermissionsContainer"></div>
		</main>
		<footer>
			<a class="button put" href="showMediaWithInsufficientPermissions"><i class="cmsIcon cmsIcon-eye"></i><span>Show</span></a>
			<button type="button" data-cms-action class="button submit" data-on_click="userConfirm" data-action="fixMediaPermissions" data-confirm="Make these files readable?"><i class="cmsIcon cmsIcon-wrench"></i><span>Fix</span></button>
		</footer>
	</section>

	<section class="widget">
		<header><h2>Type</h2></header>
		<main>
			<p class="tagsHint">Missing file types can prevent correct previews and handling. Types are detected from the originals. Preview: up to 100 files; update: up to 2000 per run.</p>
			<div class="mediaResult showMimeContainer"></div>
		</main>
		<footer>
			<a class="button put" href="showMediaWithoutMimetype"><i class="cmsIcon cmsIcon-eye"></i><span>Show</span></a>
			<button type="button" data-cms-action class="button submit" data-on_click="userConfirm" data-action="updateMediaMimetype" data-confirm="Write the type for up to 2000 files?"><i class="cmsIcon cmsIcon-arrows-rotate"></i><span>Update</span></button>
		</footer>
	</section>

	<section class="widget">
		<header><h2>Size</h2></header>
		<main>
			<p class="tagsHint">Missing dimensions can affect image previews and generated formats. Dimensions are read from the originals. Preview: up to 100 images; update: up to 2000 per run.</p>
			<div class="mediaResult showMediaContainer"></div>
		</main>
		<footer>
			<a class="button put" href="showMediaWithoutSize"><i class="cmsIcon cmsIcon-eye"></i><span>Show</span></a>
			<button type="button" data-cms-action class="button submit" data-on_click="userConfirm" data-action="updateMediaSize" data-confirm="Write the size for up to 2000 images?"><i class="cmsIcon cmsIcon-arrows-rotate"></i><span>Update</span></button>
		</footer>
	</section>

	<section class="widget">
		<header><h2>Crops</h2></header>
		<main>
			<p class="tagsHint">Generated formats are rebuilt when needed after cache clearing. Removing crop frames discards saved crops and returns to automatic framing. Originals are kept.</p>
			<label class="field" for="cropFormat"><span>Format</span>
			<select id="cropFormat" name="value">
				<option value="" selected>Choose a format</option>
				<option value="total">All formats</option>
				<optgroup label="Images">
					<?php foreach (mediaFormats::get() as $key => $image_format) {
						if (($image_format['cms_display'] ?? '') === 'none') {
							continue;
						}
						$format_info = mediaFormats::sizeLabel($image_format);
						?>
						<option value="<?= htmlspecialchars((string) $key) ?>"><?= htmlspecialchars(mediaFormats::label((string) $key, $image_format)) ?> (<?= htmlspecialchars((string) $format_info) ?>)</option>
					<?php } ?>
				</optgroup>
				<?php if (defined('VIDEO_FORMATS') && is_array(VIDEO_FORMATS) && VIDEO_FORMATS !== []) { ?>
					<optgroup label="Video">
						<?php foreach (VIDEO_FORMATS as $key => $video_format) {
							$width = (int) ($video_format['max_width'] ?? 0);
							$format_info = $width > 0 ? 'max ' . $width . ' px' : '';
							?>
							<option value="<?= htmlspecialchars((string) $key) ?>"><?= htmlspecialchars(mediaFormats::label((string) $key, $video_format)) ?><?= $format_info !== '' ? ' (' . htmlspecialchars($format_info) . ')' : '' ?></option>
						<?php } ?>
					</optgroup>
				<?php } ?>
			</select>
			</label>
		</main>
		<footer>
			<button type="button" data-cms-action class="button" data-on_click="cropAction" data-action="removeCropCache" data-confirm="Clear the generated files for this format?"><i class="cmsIcon cmsIcon-broom"></i><span>Clear cache</span></button>
			<button type="button" data-cms-action class="button submit" data-on_click="cropAction" data-action="removeCropParams" data-confirm="Remove the saved crop frames for this format?"><i class="cmsIcon cmsIcon-frame"></i><span>Remove frames</span></button>
		</footer>
	</section>
</div>
