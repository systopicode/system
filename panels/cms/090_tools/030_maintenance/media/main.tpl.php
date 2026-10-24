<h1>Media</h1>
<p class="tagsHint">Files and the sizes made from them.</p>
<?= \message::flush() ?>
<div class="widgets">
	<section class="widget">
		<header><h2>Permissions</h2></header>
		<main>
			<p class="tagsHint">Files the website cannot read, so they answer with an error. Show lists them. Fix makes them readable.</p>
			<div class="mediaResult showPermissionsContainer"></div>
		</main>
		<footer>
			<a class="button put" href="showMediaWithInsufficientPermissions">Show</a>
			<a class="button submit" data-on_click="userConfirm" data-action="fixMediaPermissions" data-confirm="Make these files readable?">Fix</a>
		</footer>
	</section>

	<section class="widget">
		<header><h2>Type</h2></header>
		<main>
			<p class="tagsHint">Files stored without a type. Show lists up to 100 and the type it would write. Update writes it for up to 2000 files.</p>
			<div class="mediaResult showMimeContainer"></div>
		</main>
		<footer>
			<a class="button put" href="showMediaWithoutMimetype">Show</a>
			<a class="button submit" data-on_click="userConfirm" data-action="updateMediaMimetype" data-confirm="Write the type for up to 2000 files?">Update</a>
		</footer>
	</section>

	<section class="widget">
		<header><h2>Size</h2></header>
		<main>
			<p class="tagsHint">Images stored without a width and height. Show lists up to 100. Update writes the size for up to 2000.</p>
			<div class="mediaResult showMediaContainer"></div>
		</main>
		<footer>
			<a class="button put" href="showMediaWithoutSize">Show</a>
			<a class="button submit" data-on_click="userConfirm" data-action="updateMediaSize" data-confirm="Write the size for up to 2000 images?">Update</a>
		</footer>
	</section>

	<section class="widget is-wide">
		<header><h2>Crops</h2></header>
		<main>
			<p class="tagsHint">A generated size of an image or a video. Clear cache deletes those files; they are rebuilt when a page needs them. Remove frames deletes the saved crop of an image, so it uses the automatic frame again.</p>
			<label class="field" for="cropFormat"><span>Format</span>
			<select id="cropFormat" name="value">
				<option value="" selected>Choose a format</option>
				<option value="total">All formats</option>
				<optgroup label="Images">
					<?php foreach (mediaFormats::get() as $key => $image_format) {
						if (($image_format['cms_display'] ?? '') === 'none') {
							continue;
						}
						if (isset($image_format['width'])) {
							$format_info = $image_format['width'] . ' × ' . $image_format['height'] . ' px';
						} elseif (isset($image_format['autocrop'])) {
							$format_info = $image_format['autocrop'] . ', ' . mediaFormats::ratioLabel($image_format['autocrop_limit'] ?? 0);
						} else {
							$format_info = ($image_format['megapixels'] ?? '') . ' megapixels';
						}
						?>
						<option value="<?= htmlspecialchars((string) $key) ?>"><?= htmlspecialchars((string) $key) ?> (<?= htmlspecialchars((string) $format_info) ?>)</option>
					<?php } ?>
				</optgroup>
				<?php if (defined('VIDEO_FORMATS') && is_array(VIDEO_FORMATS) && VIDEO_FORMATS !== []) { ?>
					<optgroup label="Video">
						<?php foreach (VIDEO_FORMATS as $key => $video_format) {
							$width = (int) ($video_format['max_width'] ?? 0);
							$format_info = $width > 0 ? 'max ' . $width . ' px' : '';
							?>
							<option value="<?= htmlspecialchars((string) $key) ?>"><?= htmlspecialchars((string) $key) ?><?= $format_info !== '' ? ' (' . htmlspecialchars($format_info) . ')' : '' ?></option>
						<?php } ?>
					</optgroup>
				<?php } ?>
			</select>
			</label>
		</main>
		<footer>
			<a class="button" data-on_click="cropAction" data-action="removeCropCache" data-confirm="Clear the generated files for this format?">Clear cache</a>
			<a class="button submit" data-on_click="cropAction" data-action="removeCropParams" data-confirm="Remove the saved crop frames for this format?">Remove frames</a>
		</footer>
	</section>
</div>
