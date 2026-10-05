<?php
/**
 * cms/tools/assets - the copies of the system, package and project assets
 * under public/assets/ (mode publish, see Http\Assets and
 * SYS/.cursor/plans/asset-delivery.plan.md).
 *
 *   publishAll  every asset of every root copied, where missing or older
 *   clearAll    public/assets/ removed - it fills up again on first use
 *
 * @var \Systopic\System\Panels\PanelNode $this
 */

use Systopic\System\Http\Assets;

switch ($this->action) {
	case 'publishAll':
		$count = Assets::publishAll();
		message::confirm("Assets published: {$count['copied']} copied, {$count['fresh']} already fresh"
			. ($count['failed'] ? ", <b>{$count['failed']} failed</b>" : '') . '.');
		client::replaceInner('.assetsStatus', include __DIR__ . '/status.php');
		break;

	case 'clearAll':
		$removed = Assets::clearAll();
		message::confirm("Assets cleared: $removed copies removed - they are made again on first use.");
		client::replaceInner('.assetsStatus', include __DIR__ . '/status.php');
		break;
}
