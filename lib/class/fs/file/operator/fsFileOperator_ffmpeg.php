<?php

/**
 * ffmpeg: a single frame out of a video file (poster / thumbnail).
 *
 * The frame comes back through the pipe as a blob - ffmpeg writes to `pipe:1`,
 * nothing touches the disk on the way. setStream(FALSE) switches to a temp file
 * for the cases the pipe cannot serve: a muxer that needs to seek in its own
 * output, or a consumer that insists on a path.
 *
 * Either way the result is finished like every operator's: store(), output(),
 * getContents().
 *
 * Where to grab: setSeconds() when the caller knows, otherwise the operator
 * asks ffprobe (via fsFileMediainfo_ffprobe) for the duration and takes a frame
 * shortly after the start - frame zero is black more often than not.
 *
 * Usage:
 *   $file->getOperator('ffmpeg')->setSeconds(2)->setSize(200, 200)
 *        ->render('webp')->store($targetPath);
 */
#[AllowDynamicProperties]
class fsFileOperator_ffmpeg extends fsFileOperator {

	static array $capabilities = [
		'read' => ['mp4', 'mov', 'webm', 'm4v'],
		'write' => ['jpg', 'jpeg', 'png', 'webp'],
	];

	/**
	 * How to get a format out through the pipe. Without a filename to read the
	 * format off, ffmpeg needs the muxer named - and image2pipe is the one that
	 * writes a single still instead of starting a sequence.
	 */
	const MUXERS = [
		// -q:v 2 is the high quality end of the mjpeg scale. A still is usually
		// the master everything else is derived from, and the extra bytes stay
		// in memory - they never reach the disk.
		'jpg' => ['-f', 'image2pipe', '-c:v', 'mjpeg', '-q:v', '2'],
		'jpeg' => ['-f', 'image2pipe', '-c:v', 'mjpeg', '-q:v', '2'],
		'png' => ['-f', 'image2pipe', '-c:v', 'png'],
		'webp' => ['-f', 'webp'],
	];

	/** why the last render produced nothing - the base class has no error channel */
	public string $lastError = '';

	protected bool $stream = TRUE;
	protected ?float $seconds = null;
	protected ?int $width = null;
	protected ?int $height = null;

	static function isAvailable(): bool {
		return shellBinary::isAvailable('ffmpeg');
	}

	function setSeconds($seconds): static {
		$this->seconds = $seconds === null ? null : (float) $seconds;
		return $this;
	}

	/** Scale target. Only applied when both are given - ffmpeg needs WxH. */
	function setSize(?int $width, ?int $height): static {
		$this->width = $width;
		$this->height = $height;
		return $this;
	}

	/** FALSE renders through a temp file instead of the pipe. */
	function setStream(bool $stream = TRUE): static {
		$this->stream = $stream;
		return $this;
	}

	function canRender($formatName): bool {
		return in_array(strtolower((string) $formatName), static::$capabilities['write'], true);
	}

	function render($formatName): static {
		if (!$this->canRender($formatName)) {
			return parent::render($formatName);
		}
		$format = strtolower((string) $formatName);
		$this->lastError = '';
		$binary = shellBinary::find('ffmpeg');
		if (!$binary) {
			return $this->fail('ffmpeg not found - define FFMPEG_BIN or add it to the PATH');
		}
		if (!$this->file->exists) {
			return $this->fail("video source not found: {$this->file->filepath}");
		}

		// No shell: proc_open takes the arguments as an array and starts the
		// process directly. That also means no escapeshellarg and no cmd.exe
		// quoting rules - only the path separators still have to be native.
		$command = [
			fs::toNative($binary),
			// -ss BEFORE -i seeks before decoding. For a thumbnail that is the
			// difference between a moment and reading the file up to that point.
			'-ss', calculate::secondsToFfmpegtimecode($this->getSeconds()),
			'-i', fs::toNative($this->file->filepath),
			'-frames:v', '1',
			'-an',
			// Keeps stderr to actual errors - the banner and the progress line
			// would otherwise be the bulk of what run() collects.
			'-v', 'error', '-nostats',
		];
		if ($this->width && $this->height) {
			$command[] = '-s';
			$command[] = ((int) $this->width) . 'x' . ((int) $this->height);
		}

		$streamable = $this->stream && isset(self::MUXERS[$format]);
		if ($streamable) {
			$command = array_merge($command, self::MUXERS[$format], ['pipe:1']);
		} else {
			// The temp file carries the extension - that is what ffmpeg reads
			// the output format off when nothing is piped.
			$this->resultTempPath = $this->getTempPath($format);
			$command[] = '-y';
			$command[] = fs::toNative($this->resultTempPath);
		}

		$result = $this->run($command);
		if ($result === null) {
			return $this;  // run() already recorded the reason
		}
		if ($streamable) {
			if ($result === '') {
				return $this->fail('ffmpeg wrote no frame to the pipe');
			}
			$this->resultBlob = $result;
		} elseif (!$this->hasResult()) {
			return $this->fail('ffmpeg wrote no frame');
		}
		return $this;
	}

	/**
	 * Runs ffmpeg and returns stdout, or NULL when it failed.
	 *
	 * stdout is the only pipe; stderr goes into a tmpfile() handle that php
	 * deletes when it closes. Two pipes would deadlock: whoever is not being
	 * read fills up, ffmpeg blocks on it, and the pipe we are waiting on never
	 * ends. Draining both in turn is the usual answer, but it needs
	 * non-blocking pipes - and under Windows neither stream_set_blocking() nor
	 * stream_select() works on them.
	 */
	protected function run(array $command): ?string {
		$errorHandle = tmpfile();
		$descriptors = [
			1 => ['pipe', 'w'],
			2 => $errorHandle ?: ['file', PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null', 'w'],
		];
		$process = proc_open($command, $descriptors, $pipes);
		if (!is_resource($process)) {
			$errorHandle && fclose($errorHandle);
			$this->fail('ffmpeg could not be started');
			return null;
		}
		$out = (string) stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$code = proc_close($process);
		$error = '';
		if ($errorHandle) {
			rewind($errorHandle);
			$error = (string) stream_get_contents($errorHandle);
			fclose($errorHandle); // tmpfile: gone with the handle
		}
		if ($code !== 0) {
			$lines = array_slice(preg_split('~\R~', trim($error)) ?: [], -5);
			$this->fail("ffmpeg returned code $code: " . implode("\n", $lines));
			return null;
		}
		return $out;
	}

	/** Position of the frame: what the caller asked for, else just past the start. */
	protected function getSeconds(): float {
		if ($this->seconds !== null) {
			return max(0.0, $this->seconds);
		}
		$duration = (float) ($this->file->mediainfo->duration ?: 0);
		return $duration > 2 ? 1.0 : 0.0;
	}

	protected function fail(string $message): static {
		$this->lastError = $message;
		p("ffmpeg: $message");
		$this->cleanup(); // drops the half written temp file, if there is one
		return $this;
	}
}
