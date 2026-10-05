<?php

/**
 * Single frames out of video files (poster / thumbnail rendering).
 *
 * The work happens in fsFileOperator_ffmpeg - this is the flat entry point the
 * pwk renderings call, and it keeps their contract: bool back, reason in
 * $lastError. New code can go to the operator directly and gets the whole
 * result api (store / output / getContents) with it.
 */
class videoFrame {

	public static string $lastError = '';

	/**
	 * @param fsFile      $source video file to grab the frame from
	 * @param string      $targetPath internal path of the image to write
	 * @param float|int   $seconds position of the frame
	 * @param int|null    $width  scale target, omit to keep source size
	 * @param int|null    $height scale target, omit to keep source size
	 */
	static function extract(fsFile $source, string $targetPath, $seconds = 0, ?int $width = NULL, ?int $height = NULL): bool {
		self::$lastError = '';
		$format = strtolower((string) pathinfo($targetPath, PATHINFO_EXTENSION));
		/** @var fsFileOperator_ffmpeg $operator */
		$operator = $source->getOperator('ffmpeg');
		// Asking for a format the operator does not write would land in
		// fsFileOperator::render(), and that ends the request via d().
		if (!$operator->canRender($format)) {
			self::$lastError = "cannot write '$format' frames - target: $targetPath";
			$operator->destroy();
			return FALSE;
		}
		$operator->setSeconds($seconds)->setSize($width, $height)->render($format);
		if (!$operator->hasResult()) {
			self::$lastError = $operator->lastError ?: 'ffmpeg produced no frame';
			$operator->destroy();
			return FALSE;
		}
		$operator->store($targetPath); // creates the folder, moves, chmods
		$operator->destroy();
		return TRUE;
	}
}
