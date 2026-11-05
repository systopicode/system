<?php use Systopic\System\Auth\Portrait; ?>
		<div class="profilePortrait">
			<div class="portraitFrame">
				<form class="ajax portraitDrop" method="post" enctype="multipart/form-data" action="<?= $e($this->href . 'uploadPortrait') ?>" data-drop-files data-upload="onsubmit">
					<div class="signet">
						<span><?= $e($this->initials) ?></span>
						<?php if ($portrait !== '') { ?>
							<img referrerpolicy="no-referrer" src="<?= $e($portrait) ?>" alt="">
						<?php } ?>
					</div>
					<input type="file" aria-label="Upload profile picture" title="Upload profile picture" name="portrait" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="this.form.requestSubmit()">
				</form>
				<?php if ($me && Portrait::hasFile((int) $me->id)) { ?>
					<button type="button" class="portraitRemove" data-on_click="removePortrait" title="Remove photo"><i class="cmsIcon cmsIcon-trash"></i></button>
				<?php } ?>
			</div>
			<span>Drop a photo here or click to upload</span>
			<?= \message::flush('portrait') ?>
		</div>
