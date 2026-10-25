<?php
/** @var \Systopic\System\Panels\Root\Cms\Search\Panel $this */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$search = $this->results;
?>
<div class="cmsFindResults">
	<?php if (mb_strlen($search->query) < 2) { ?>
		<p class="hint">Type at least two letters.<span class="cmsKeys"> <kbd>↑</kbd> <kbd>↓</kbd> choose, <kbd>Enter</kbd> opens, <kbd>Esc</kbd> closes.</span></p>
	<?php } elseif ($search->groups === []) { ?>
		<p class="hint">Nothing found for “<?= $e($search->query) ?>”.</p>
	<?php } else {
		foreach ($search->groups as $group => ['icon' => $icon, 'hits' => $hits]) { ?>
			<h3><?= $e($group) ?></h3>
			<ul>
				<?php foreach ($hits as $hit) { ?>
					<li>
						<a class="ajax" href="<?= $e($hit['href']) ?>">
							<i class="fa-regular <?= $e($icon) ?>"></i>
							<span><b><?= $e($hit['title']) ?></b><?php if ($hit['context'] !== '') { ?><small><?= $e($hit['context']) ?></small><?php } ?></span>
						</a>
					</li>
				<?php } ?>
			</ul>
		<?php }
	} ?>
</div>
