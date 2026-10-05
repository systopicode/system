<?php

abstract class mediaServer {

	static $instance;

	abstract function getHref(mediaFormat $format): string;
	abstract function getFile(mediaFormat $format);
	abstract function putFile($source, mediaFormat $format): bool;

	static function get(): mediaServer {
		return self::$instance ?? self::$instance = new mediaServer_local();
	}

	static function set(mediaServer $server): void {
		self::$instance = $server;
	}

}
