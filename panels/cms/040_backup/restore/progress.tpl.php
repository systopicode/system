<?php
$index = $this->parent->state->taskListNextIndex;
$total = count($this->parent->state->taskList);
$progress = $total > 0 ? round($index / $total * 100) : 0;
$info = "$index/$total";

$angle = $progress * 3.6;
$angle_1 = min(0, $angle - 90);
$angle_2 = min(0, calculate::minmax(90, $angle, 180) - 180);
$angle_3 = min(0, calculate::minmax(180, $angle, 270) - 270);
$angle_4 = min(0, calculate::minmax(270, $angle, 360) - 360);
?>
<main>
    <center>
		<div class="loader">
			<div class="loader-bg">
				<div class="text"><b><?= $progress ?>%</b><br><?= $info ?><span></span></div>
			</div>
			<div class="spiner-holder-one animate-0-25-a">
				<div class="spiner-holder-two animate-0-25-b" style="transform: rotate(<?= $angle_1 ?>deg);">
					<div class="loader-spiner" style=""></div>
				</div>
			</div>
			<div class="spiner-holder-one animate-25-50-a">
				<div class="spiner-holder-two animate-25-50-b" style="transform: rotate(<?= $angle_2 ?>deg);">
					<div class="loader-spiner"></div>
				</div>
			</div>
			<div class="spiner-holder-one animate-50-75-a">
				<div class="spiner-holder-two animate-50-75-b" style="transform: rotate(<?= $angle_3 ?>deg);">
					<div class="loader-spiner"></div>
				</div>
			</div>
			<div class="spiner-holder-one animate-75-100-a">
				<div class="spiner-holder-two animate-75-100-b" style="transform: rotate(<?= $angle_4 ?>deg);">
					<div class="loader-spiner"></div>
				</div>
			</div>
		</div>
		<?php
		echo message::flush();
		?>
		<?php /*
		<style>
			div.loader + div.cms.message div{
				background: none;
				color:var(--cmsMainColor);
			}
		</style>
		*/ ?>
    </center>
</main>