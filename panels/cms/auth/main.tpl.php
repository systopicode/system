<?php /** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */ ?>
<div class="layout center cms">
	<div class="oneFifth">
		<?php if (fs::isFile(fs::$siteRoot . 'overrides/img/logo.svg')) { ?>
			<a class='logo override' href="<?= $this->href ?>">
				<?= fs::file_get_contents(fs::$siteRoot . 'overrides/img/logo.svg'); ?>
			</a>
		<?php } else { ?>
			<a class='logo' href="<?= $this->href ?>">
				<?= fs::file_get_contents(fs::$sysRoot . 'public/img/system/logo.svg'); ?>
			</a>
		<?php } ?>
	</div>
	<?php $this->withView($this->form); ?>
	<?php include('footer.tpl.php'); ?>
</div>

<style>
	body {
		display: flex;
		flex-direction: column;
		justify-content: center;
		background: var(--cmsMainColor) url(<?= http::$sysUrl ?>/img/system/login_bg_<?= rand(1, 5) ?>.svg) no-repeat 98% 98% / 48% auto;
		background-origin: content-box;
	}

	.cms h1 {
		text-transform: uppercase;
		margin-bottom: 0;
	}

	.cms h1,
	.cms h3,
	.cms input,
	.cms p {
		color: #fff;
	}

	.cms input {
		background: rgba(0, 0, 0, .2);
	}

	.cms input::placeholder {
		color: #fff;
		opacity: .5;
	}

	div.layout.center {
		width: 100%;
		min-width: 500px;
		background: none;
		display: flex;
		flex-direction: column;
		background: rgba(125, 171, 189, .2);
		justify-content: center;
		backdrop-filter: blur(10px);
		-webkit-backdrop-filter: blur(10px);
		max-width: 50%;
		height: 100%;
		box-sizing: border-box;
		padding: 2rem 6rem;
	}

	div.layout.center footer,
	div.layout.center div.oneFifth {
		margin-top: auto;
	}

	.cms footer a {
		font-size: 14px;
		color: rgba(255, 255, 255, .3);
		margin: 5px 10px;
	}

	div.oneFifth .headerlogo {
		width: 90%;
		height: auto;
		margin-bottom: 50px;
		padding-bottom: 22%;
		margin-left: 10%;
	}

	.cms button {
		margin-bottom: 3rem;
	}

	a.logo svg {
		width: 100%;
		max-width: 200px;
		margin-bottom: 3rem;
		filter: brightness(1.5);
	}

	a.logo:not(.override) {
		& .st1 {
			fill: var(--cmsSecondaryColor);
		}

		& .st0 {
			fill: var(--cmsMainColor);
		}
	}

	div.cms.message {
		margin: 0 0 20px 0;
	}
</style>