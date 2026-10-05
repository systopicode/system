<?php

class appCms {

	/** absolute path of the system root, simplified */
	public ?string $root {
		get => $this->root ??= fs::simplifyPath(fs::$sysRoot);
	}

}
