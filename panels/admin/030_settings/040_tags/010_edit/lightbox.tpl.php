<?php
/** @var \Systopic\System\Panels\Root\Admin\Settings\Tags\Edit\Panel $this */

$tag = $this->tag;
if (!$tag) {
	return;
}
$pages = $this->assignments();
$this->loadJS();
?>
<div class="cmslightbox cms" data-doc=box>
	<section>
		<?php if ($tag) { ?>
			<header class="tagEditTitle">
				<h1 data-doc=tagname><?= htmlspecialchars((string) $tag->name) ?></h1>
				<button type="button" class="tagGroupRename" data-on_click="rename" title="Rename or confirm">
					<i class="cmsIcon cmsIcon-pen"></i><i class="cmsIcon cmsIcon-check pencilConfirm"></i>
				</button>
				<ul class="barmenu">
					<li>
						<button type="button" data-on_click="userConfirm" data-action="removeTag" data-tag_id="<?= (int) $tag->id ?>" class="button icononly" data-confirm="Remove this tag? Pages and files lose it." aria-label="Remove">
							<i class="cmsIcon cmsIcon-trash"></i>
						</button>
					</li>
					<li><a class="ajax" href="../" aria-label="Close" data-focus-id="tag-edit-close"><i class="cmsIcon cmsIcon-xmark"></i></a></li>
				</ul>
			</header>
			<main>
				<div class="tagAssignmentsWrap">
					<?php if ($pages === []) { ?>
						<p class="tagAssignmentsEmpty">Nothing is assigned to this tag.</p>
					<?php } else { ?>
						<ul class="tagAssignments">
							<?php foreach ($pages as $page) { ?>
								<li>
									<span class="tagAssignmentLabel"><?= htmlspecialchars($page['label']) ?></span>
									<span class="tagAssignmentActions">
										<a class="ajax button transparent icononly" data-focus-id="tag-<?= (int) $tag->id ?>-open-<?= (int) $page['nodeId'] ?>" href="<?= htmlspecialchars($page['href']) ?>" <?= empty($page['media']) ? 'target="_blank" rel="noopener"' : '' ?> title="<?= !empty($page['media']) ? 'View' : 'Open in new window' ?>" aria-label="<?= !empty($page['media']) ? 'View' : 'Open in new window' ?>">
											<i class="cmsIcon <?= !empty($page['media']) ? 'cmsIcon-eye' : 'cmsIcon-arrow-up-right-from-square' ?>"></i>
										</a>
										<button type="button" data-cms-action class="button transparent icononly" title="Remove from this page" aria-label="Remove from this page"
											data-on_click="userConfirm" data-action="detach" data-confirm="Remove this tag from this item?"
                                            data-focus-id="tag-<?= (int) $tag->id ?>-unlink-<?= (int) $page['nodeId'] ?>"
											data-node_id="<?= (int) $page['nodeId'] ?>"
											data-tag_id="<?= (int) $tag->id ?>">
											<i class="cmsIcon cmsIcon-link-slash"></i>
										</button>
									</span>
								</li>
							<?php } ?>
						</ul>
					<?php } ?>
				</div>
			</main>
		<?php } ?>
	</section>
</div>
