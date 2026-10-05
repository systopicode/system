<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Confirm;

/**
 * Single-slot store for an open server-side action confirmation.
 *
 * There is at most one open question per session — a confirmation only exists
 * between the click that triggered a destructive action and the answer to it.
 * One slot instead of per-panel state means no panel has to declare a state
 * key (attachState() would strip an undeclared one), and the dialog can be
 * hosted by a different panel than the one that asked: a widget asks, the
 * module renders.
 *
 * The lifetime is one request round trip. sweep() discards the question unless
 * the incoming action is the one it was created for, so a dialog cannot
 * survive an unrelated click, a reload or a panel switch. That is the whole
 * point of this class: a stored show/hide flag (state->hiddenViews) has to be
 * reset by whoever set it, and any action that forgets leaves the dialog
 * lying around.
 *
 * No updateView() is needed when a question is discarded: openRoot() cancels
 * for an invisible view, the layer diff sees a panelName mismatch and the
 * dialog collapses back into a placeholder on the client — see
 * PanelNavigator::cancel().
 */
final class PendingConfirm
{
	private const SESSION_KEY = 'panelPendingConfirm';

	private static bool $swept = false;

	/** The open question, or null when none is pending for this request. */
	public static function get(): ?object
	{
		self::sweep();
		$pending = $_SESSION[self::SESSION_KEY] ?? null;
		return is_object($pending) ? $pending : null;
	}

	public static function arm(object $question): void
	{
		self::$swept = true; // the question created now must not be swept away
		$_SESSION[self::SESSION_KEY] = $question;
	}

	public static function clear(): void
	{
		self::$swept = true;
		unset($_SESSION[self::SESSION_KEY]);
	}

	/** Discards a stored question unless this request answers it. Runs once. */
	private static function sweep(): void
	{
		if (self::$swept) {
			return;
		}
		self::$swept = true;

		$pending = $_SESSION[self::SESSION_KEY] ?? null;
		if (!is_object($pending)) {
			return;
		}
		if (($pending->action ?? null) !== \app::request()->action) {
			unset($_SESSION[self::SESSION_KEY]);
		}
	}
}
