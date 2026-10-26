<?php
/**
 * "Vorschau" in the header: the open record in the project's app.
 *
 * A project that shows its records in an app names the page of the open one
 * (appCmsPreviewUrl(), spaceticker: a project, a contact); '' where there is
 * nothing to show - then no button. A view of its own, re-rendered on every
 * request (panel.php onLoad): the header is part of body.tpl.php, which an
 * ajax navigation does not render again.
 *
 * @var \Systopic\System\Panels\Root\Cms\Panel $this
 */
$previewUrl = function_exists('appCmsPreviewUrl') ? (string) appCmsPreviewUrl() : '';
if ($previewUrl === '') {
	return;
}
?>
<a class="button cmsPreviewBtn" target="_blank" rel="noopener noreferrer"
	href="<?= htmlspecialchars($previewUrl) ?>" title="Vorschau in der App">
	<i class="fa-regular fa-eye"></i><span>Vorschau</span>
</a>
