<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Tools\System\Systemcheck;

use Systopic\System\Panels\PanelNode;

class Panel extends PanelNode
{
	public function onLoad(): void
	{
		if ($this->action !== 'download' || !$this->inPath()) {
			return;
		}
		$file = __DIR__ . '/systemcheck.php';
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="systemcheck.php"');
		header('Content-Length: ' . (string) filesize($file));
		readfile($file);
		exit;
	}
}
