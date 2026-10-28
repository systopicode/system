<?php
/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Edit\Panel $this */

$tag = $this->tag;
$pages = $tag ? $this->assignments() : [];
$this->loadJS();
?>
<div class="cmslightbox cms" data-doc=box>
	<section>
		<?php if ($tag) { ?>
			<header class="tagEditTitle">
				<h1 data-doc=tagname><?= htmlspecialchars((string) $tag->name) ?></h1>
				<button type="button" class="tagGroupRename" data-on_click="rename" title="Rename">
					<i class="fa-regular fa-pen"></i>
				</button>
				<ul class="barmenu">
					<li><a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a></li>
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
										<a class="ajax" href="<?= htmlspecialchars($page['href']) ?>" title="Open">
											<i class="fa-regular fa-arrow-up-right-from-square"></i>
										</a>
										<button type="button" title="Remove from this page"
											data-on_click="detach"
											data-node_id="<?= (int) $page['nodeId'] ?>"
											data-tag_id="<?= (int) $tag->id ?>">
											<i class="fa-regular fa-trash"></i>
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
