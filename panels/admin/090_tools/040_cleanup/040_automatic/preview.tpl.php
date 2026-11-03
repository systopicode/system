<?php
use Systopic\System\Maintenance\Runner;
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<?php if (isset($this) && ($this->cleanupPreview ?? null) !== null) { $preview = $this->cleanupPreview; ?>
<dialog class="cms cmslightbox" id="cleanupPreview" data-size="2" data-auto-open><section><header><h1>Preview results</h1><button type="button" class="button transparent icononly" aria-label="Close" onclick="this.closest('dialog').close();this.closest('dialog').remove()"><i class="cmsIcon cmsIcon-xmark"></i></button></header><main>
<p>No data was deleted. These are the expired records eligible for the next cleanup step.</p>
<?php if ($preview['busy']) { ?><p>Another cleanup is running. Please try again shortly.</p><?php } else { ?>
<ul class="cleanupPreviewResults"><?php foreach ($preview['results'] as $id => $entry) { ?><li><strong><?= $e(Runner::jobs()[$id]['label']) ?></strong><span><?= !$entry['ok'] ? 'Could not check this task' : ($entry['count'] === 0 ? 'No expired records found in this step' : $entry['count'] . ' expired records can be removed') ?><?= !empty($entry['more']) ? ' · Further steps may be needed' : '' ?></span></li><?php } ?></ul>
<?php if (!empty($preview['remaining'])) { ?><p>Some tasks were deferred. Preview them individually to check the remaining work.</p><?php } ?>
<?php } ?></main></section></dialog>
<?php } ?>
