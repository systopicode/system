<?php

class fsFileMediainfo_ffprobe extends fsFileMediainfo {

	#[\Override]
			function readMediainfo(): array {
		$filepath = (string) ($this->file->filepath ?? '');
		if ($filepath === '' || !\fs::isFile($filepath)) {
			return self::$defaultMediainfo + ['error' => 'video file not found'];
		}

		$binary = \shellBinary::find('ffprobe');
		if (!$binary) {
			return self::$defaultMediainfo + ['error' => 'ffprobe not installed - video mediainfo unavailable'];
		}

		$command = escapeshellarg($binary)
				. ' -v quiet -print_format json -show_format -show_streams '
				. escapeshellarg(\fs::toNative($filepath));
		$output = shell_exec($command);
		$ffprobeInfo = is_string($output) ? json_decode($output) : null;
		$streams = $ffprobeInfo->streams ?? null;
		if (!is_array($streams) || !count($streams)) {
			return self::$defaultMediainfo + ['error' => 'no streams found'];
		}

		foreach ($streams as $stream) {
			if (($stream->codec_name ?? NULL) === 'h264') {
				$videostream = $stream;
			}
			if (($stream->codec_name ?? NULL) === 'aac') {
				$audiostream = $stream;
			}
		}
		if (!isset($videostream)) {
			return self::$defaultMediainfo + ['error' => 'no h264 video streams found - please check video source.'];
		}
		return [
			'frames' => $videostream->nb_frames ?? NULL,
			'duration' => $videostream->duration ?? NULL,
			'width' => $videostream->width ?? NULL,
			'height' => $videostream->height ?? NULL,
			'sizeUnit' => 'px',
			'has_audio' => isset($audiostream),
			'rotate' => isset($videostream->tags->rotate),
		];
	}

	#[\Override]
			function getDimensionsInfo(): string {
		return 'not Available';
	}

	#[\Override]
			function getResolutionInfo(): string {
		return 'not Available';
	}
}
