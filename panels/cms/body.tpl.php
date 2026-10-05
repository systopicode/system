<?php
$this->loadJS();
?>

<body class="<?= 'CMS_VERSIONDATE' ?> <?= implode(' ', $this->path) ?> <?= 'system' ?>">
	<main <?= DEBUG() ? ' style="border: 22px solid lightblue;border-bottom:none; height:calc(100% - 22px);"' : '' ?>>
		<div class="main cms">
			<header style="height:40px">
				<?php $this->withChild('user', 'signetmenu'); ?>
			</header>
			<nav>
				<ul>
					<?php $this->withView('menu'); ?>
				</ul>
				<div class="bottomMenu">
					<?php
					if (isset($modules['bottomMenu'])) {
						foreach ($modules['bottomMenu']['submodules'] as $key => $values) {
					?>
							<div class="<?= $key ?>">
								<?php if (isset($values['href'])) { ?>
									<a title="<?= $values['label'] ?>" href="<?= $values['href'] ?>" class="<?= $values['ajax'] ?> progressBar"><img src="<?= http::$root ?>panels/cms/bottomMenu/<?= $key ?>/icon.svg"></a>
								<?php
								} else {
									fs::includeFile(fs::$root . 'panels/cms/bottomMenu/' . $key . '/menu.tül.php');
								}
								?>
							</div>
					<?php
						}
					}
					?>
					<div class="serverVersion">
						<b class="<?= STAGE ?>">
							<?= STAGE ?>
						</b>
					</div>
				</div>
			</nav>
		</div>
		<?php $this->withChildSelected('pylon'); ?>
		<div class="content">
			<header class="cms">
				<?php $this->withChildSelected('controls'); ?>
			</header>
			<?php
			echo message::flush(['template']);
			echo '<div class=flexRow>';
			echo '	<div class=flexColumn>';

			$this->withChildSelected();

			if ($this->withChildSelected('footer')) { ?>
				<script>
					footerResize();
				</script>
			<?php } ?>

			<?php
			echo '</div>';
			$this->withChildSelected('sidebar');
			echo '</div>';
			?>
		</div>
	</main>
	<?php
	$this->withSelected('lightbox');
	echo message::flush();
	?>
</body>