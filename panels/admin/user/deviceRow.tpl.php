<?php
/** Shared existing device row; caller provides the display helpers and current user. */
use Systopic\System\Auth\DeviceName;
use Systopic\System\Auth\SignInLog;
[$presence, $presenceTitle] = \Systopic\System\Auth\Presence::deviceMark((int) $device->id);
	?>
	<div class="userDevice">
		<i class="<?= $e(DeviceName::icon((string) $device->userAgent)) ?>" aria-hidden="true"></i>
		<span class="presence<?= $presence !== '' ? ' ' . $presence : '' ?>" title="<?= $e($presenceTitle) ?>" role="img" aria-label="<?= $e($presenceTitle) ?>"></span>
		<span>
			<b><?= $e($device->name) ?></b>
			<?= $device->client === 'app' ? 'App' : 'Browser' ?>
			<small><?php if ($screen) { ?><?= $e($screen['label']) ?> · <?php } ?><?php if ($here && (int) $here->id === (int) $device->id) { ?>This device · <?php } ?>Last seen <?= $e($when($device->lastSeen)) ?><?php
				$account = $screen ? $screen['name'] : ($me ? trim((string) ($me->name ?: $me->email)) : '');
				$paired = $account !== '' ? SignInLog::paired($account, $device->createdAt) : '';
				if ($paired !== '') { ?><br><?= $e($paired) ?><?php } ?></small>
		</span>
		<?php if ($screen || !$here || (int) $here->id !== (int) $device->id) { ?>
			<button type="button" class="button transparent icononly" data-on_click="userConfirm" data-action="<?= $screen ? 'revokeScreenDevice' : 'revokeOwnDevice' ?>" data-device_id="<?= (int) $device->id ?>" data-confirm="Sign this device out?" aria-label="Sign out device: <?= $e((string) $device->name) ?>; last seen <?= $e($when($device->lastSeen)) ?>" title="Sign out device"><i class="<?= $e(DeviceName::slash((string) $device->userAgent)) ?>" aria-hidden="true"></i></button>
		<?php } ?>
	</div>
