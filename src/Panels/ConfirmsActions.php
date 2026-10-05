<?php

declare(strict_types=1);

namespace Systopic\System\Panels;

use Systopic\System\Panels\Confirm\PendingConfirm;

/**
 * Server-side confirmation for destructive actions.
 *
 * A destructive action guards itself with confirmRequired() as its first
 * statement:
 *
 *   case 'removeAllItems':
 *       if ($this->confirmRequired('Remove all items from the queue?')) {
 *           break;
 *       }
 *       db::query(...);
 *
 * The first click stores the question and the action aborts; the 'confirmbox'
 * view renders the dialog. Its confirm button repeats the same action carrying
 * the stored token, confirmRequired() returns false and the action body runs.
 *
 * Unlike the browser's confirm(), the question is rendered server-side, so it
 * can name and list the records it is about to touch — multi-selection
 * included. Use userConfirm (panelNode.js) instead when a one-line yes/no is
 * all that is needed; it saves a round trip.
 *
 * Visibility is derived, never stored: PanelNode::viewVisible() reports the
 * dialog visible exactly while a question is open. See PendingConfirm for the
 * one-round-trip lifetime that keeps stale dialogs from reappearing.
 */
trait ConfirmsActions
{
	/** View name of the dialog. Rendered via withRoot() from the body template. */
	public const CONFIRM_VIEW = 'confirmbox';

	/**
	 * Action the dismiss button sends. It needs no handler: any action other
	 * than the one under question makes PendingConfirm::sweep() drop the
	 * question, which is exactly what dismissing means.
	 */
	public const CONFIRM_CANCEL_ACTION = 'confirmCancel';

	/** The question the current action answered — carries ->payload. */
	public ?object $confirmAnswered = null;

	/**
	 * Guard for destructive actions.
	 *
	 * @param array{title?:string, label?:string, dismiss?:string, items?:array, payload?:mixed, href?:string} $options
	 * @return bool True while the question is unanswered — abort the action.
	 */
	public function confirmRequired(string $message, array $options = []): bool
	{
		$action  = (string) \app::request()->action;
		$pending = PendingConfirm::get();
		$token   = (string) (\http::dataset('confirm_token') ?? \http::put('confirm_token') ?? '');

		if ($pending !== null
			&& $token !== ''
			&& ($pending->action ?? null) === $action
			&& hash_equals((string) $pending->token, $token)
		) {
			$this->confirmAnswered = $pending;
			PendingConfirm::clear();
			$this->markConfirmboxUpdated();
			return false;
		}

		PendingConfirm::arm((object) [
			'action'  => $action,
			// The href that produced this request is already relative to the
			// browser location (which does not move on PUT), so echoing it back
			// on the confirm button reaches the same panel — no URL arithmetic.
			'href'    => (string) ($options['href'] ?? \http::dataset('on_click') ?? $action),
			'token'   => \str::randomHex(16),
			'title'   => (string) ($options['title'] ?? 'Bestätigung'),
			'message' => $message,
			'label'   => (string) ($options['label'] ?? 'Ja, ausführen'),
			'dismiss' => (string) ($options['dismiss'] ?? 'Abbrechen'),
			'items'   => array_values($options['items'] ?? []),
			'payload' => $options['payload'] ?? null,
		]);
		$this->markConfirmboxUpdated();
		return true;
	}

	/** The open question, or null. Read by viewVisible() and the dialog template. */
	public function confirmPending(): ?object
	{
		return PendingConfirm::get();
	}

	/**
	 * Generic dialog markup. A panel that wants thumbnails or its own chrome
	 * ships its own confirmbox.tpl.php instead of calling this.
	 */
	public function renderConfirmbox(): string
	{
		$q = $this->confirmPending();
		if ($q === null) {
			return '';
		}

		$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);

		$items = '';
		foreach ($q->items as $item) {
			$items .= '<li>' . $esc($item) . '</li>';
		}
		if ($items !== '') {
			$items = '<ul class="confirmItems">' . $items . '</ul>';
		}

		return '<div class="lightbox confirmbox" data-doc="confirmbox">'
			. '<section data-doc="box" data-confirm_token="' . $esc($q->token) . '">'
			. '<header><h1>' . $esc($q->title) . '</h1></header>'
			. '<main><div class="layout"><div class="fullColumn">'
			. '<p>' . $esc($q->message) . '</p>' . $items
			. '</div></div></main>'
			. '<footer>'
			. '<button data-on_click="' . self::CONFIRM_CANCEL_ACTION . '">' . $esc($q->dismiss) . '</button>'
			. '<button class="submit" data-on_click="' . $esc($q->href) . '">' . $esc($q->label) . '</button>'
			. '</footer>'
			. '</section></div>';
	}

	/**
	 * Marks the dialog for re-render on this panel and all its ancestors.
	 *
	 * The asking panel does not know which ancestor hosts confirmbox.tpl.php —
	 * withRoot() resolves that at render time. Marking the whole chain costs
	 * one array entry per panel and guarantees the host is covered; without it
	 * a dialog replacing an already open one would stay an alias and the client
	 * would keep showing the previous question (and its dead token).
	 */
	private function markConfirmboxUpdated(): void
	{
		for ($panel = $this; $panel !== null; $panel = $panel->parent) {
			$panel->updateView(self::CONFIRM_VIEW, true);
		}
	}
}
