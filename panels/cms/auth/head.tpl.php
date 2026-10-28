<?php
include '../head.tpl.php';
?>
<script>
if (matchMedia('(max-width: 1024px), (orientation: portrait) and (max-width: 1400px)').matches && 'scrollRestoration' in history) {
	history.scrollRestoration = 'manual';
}
</script>
<style>
/* Outside the cms layer, so the lift is not cancelled by the body reset. */
@media (max-width: 1024px), (orientation: portrait) and (max-width: 1400px) {
	html:has(.authCard) {
		background: var(--cmsMainColor);
		overflow-x: clip;
		overflow-y: auto;
	}
	body.cms:has(.authCard) {
		margin-top: 0 !important;
		padding-top: max(1.25rem, env(safe-area-inset-top, 0px)) !important;
		min-height: 100dvh;
		background:
			radial-gradient(42rem 26rem at 18% -7rem, color-mix(in srgb, var(--cmsHighlightColor) 32%, transparent), transparent 62%),
			var(--cmsMainColor) !important;
	}
}
</style>
