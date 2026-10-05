<h2>File Permissions</h2>
<a class="button put" href="showMediaWithInsufficientPermissions">Show Media Files with insufficient Permissions</a>
<a class="button put submit" href="fixMediaPermissions">Fix Permissions</a>
<div class="showPermissionsContainer"></div>
<hr>
<h2>Mime Type Functions</h2>
<a class="button put" href="showMediaWithoutMimetype">Show Media without Mimetype (100)</a>
<a class="button put submit" href="updateMediaMimetype">Update Mimetype (2000)</a>
<div class="showMimeContainer"></div>
<hr>
<h2>File Size Type Functions</h2>
<a class="put button" href="showMediaWithoutSize">Show Media without Size (100)</a>
<a class="put button submit" href="updateMediaSize">Update Size (2000)</a>
<div class="showMediaContainer"></div>
<hr>
<h2>Clear Crops</h2>
<form method="get">
    <select name="value" required="">
	<option selected disabled ></option>
	<option value="all">All</option>
	<?php
	$i = 0;
	foreach (mediaFormats::get() as $key => $image_format) {
	    if ($image_format['cms_display'] != 'none') {
		$i++;
		if ($i == 1 && empty($selectedFormat)) {
		    $selectedFormat = $key;
		}
		if (isset($image_format['width'])) {
		    $format_info = $image_format['width'] . ' x ' . $image_format['height'] . 'px';
		} elseif (isset($image_format['autocrop'])) {
		    $autocrop = $image_format["autocrop_limit"];
		    $format_info = 'Orientation: ' . $image_format['autocrop'] . '&nbsp&nbsp;&mdash;&nbsp;&nbsp;Aspect Ratio: ' . $autocrop;
		} else {
		    $format_info = 'Megapixels: ' . settype($image_format['megapixels'], "string");
		}
		?>
		<option value="<?= $key ?>"><?= $key ?> (<?= $format_info ?>)</option>
		<?php
	    }
	}
	?>
    </select>
    <br><br>
    <button name="action" value="removeCropCache" class="submit" type="submit">Clear image Crops Cache</button>
    <button name="action" value="removeCropParams" class="submit" type="submit">Remove image Crop Params</button>
</form>



