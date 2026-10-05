<?php
/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Join\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$pages = static fn(int $n): string => ($n ?: 'No') . ' Page' . ($n === 1 ? '' : 's');
[$source, $target] = [$this->source, $this->target];
?>
<div class="lightbox">
	<section>
		<header>
			<h1>Tags Zusammenführen</h1>
			<ul class="barmenu">
				<li><a class=ajax href=".././"><i class="far fa-times"></i></a></li>
			</ul>
		</header>
		<?php if ($source && $target) { ?>
			<main>
				<div class="merge layout">
					<div class="twoFifth">
						<div class="tag active" style="background:#1F8A70;"><?= $e($source->name) ?></div>
						<p><?= $pages($this->sourcePages) ?></p>
					</div>
					<div class="oneFifth">
						<i class="far fa-arrow-right"></i>
					</div>
					<div class="twoFifth">
						<div class="tag active" style="background:#1F8A70;"><?= $e($target->name) ?></div>
						<p><?= $pages($this->targetPages) ?></p>
					</div>
				</div>
				<p><?= $e($source->name) ?> wird gelöscht; seine Seiten bekommen <?= $e($target->name) ?>.</p>
			</main>
			<footer>
				<button data-on_click=join>Zusammenführen</button>
			</footer>
		<?php } ?>
	</section>
	<style>
		.merge {
			font-size: 1.5em;
			text-align: center;
			margin-bottom: 10px;
		}

		.merge > i {
			margin: 0 20px;
		}
	</style>
</div>
