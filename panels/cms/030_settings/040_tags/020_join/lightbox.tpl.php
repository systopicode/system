<?php
/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Join\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$pages = static fn(int $n): string => ($n ?: 'No') . ' Page' . ($n === 1 ? '' : 's');
[$source, $target] = [$this->source, $this->target];
$swatch = static function ($tag) use ($e): string {
	$color = $tag->colorStyle !== '' ? $tag->colorStyle : '#000';
	return '--tag-color:' . $e($color) . ';background-color:' . $e($color) . ';border-color:' . $e($color);
};
?>
<div class="cmslightbox cms">
	<section>
		<header>
			<h1>Join tags</h1>
			<ul class="barmenu">
				<li><a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a></li>
			</ul>
		</header>
		<?php if ($source && $target) { ?>
			<main>
				<div class="tagJoin">
					<div>
						<div class="tag" style="<?= $swatch($source) ?>"><?= $e($source->name) ?></div>
						<p><?= $pages($this->sourcePages) ?></p>
					</div>
					<i class="fa-regular fa-arrow-right"></i>
					<div>
						<div class="tag" style="<?= $swatch($target) ?>"><?= $e($target->name) ?></div>
						<p><?= $pages($this->targetPages) ?></p>
					</div>
				</div>
				<p class="tagsHint"><?= $e($source->name) ?> is removed. Its pages and files keep <?= $e($target->name) ?>.</p>
			</main>
			<footer>
				<button type="button" class="button submit" data-on_click="join" data-id="<?= (int) $source->id ?>" data-into="<?= (int) $target->id ?>">Join</button>
			</footer>
		<?php } else { ?>
			<main><p class="tagsHint">These two tags are not here.</p></main>
		<?php } ?>
	</section>
</div>
