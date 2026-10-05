<?php

class mediaServer_local extends mediaServer {

	function getHref(mediaFormat $format): string {
		return http::$root . $format->varpath . $format->media->folders . $format->filename;
	}

	function getFile(mediaFormat $format) {
		return fsFile::get($format->src);
	}

	function putFile($source, mediaFormat $format): bool {
		$destDir = $format->dirpath;
		if (!fs::isDir($destDir)) {
			fs::fsMkdir($destDir, 0777, true);
		}
		return fs::fsCopy($source, $format->src);
	}

}
