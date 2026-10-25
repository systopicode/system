<?php

/** @var \Systopic\System\Panels\Root\Cms\Panel $this */

use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Users\Operator as UserOperator;

switch ($this->action) {
	case 'logout':
		$user = UserAdmin::of($this->scope)->user((int) \Systopic\System\Auth\Session::id());
		if ($user) {
			$this->scope->save(UserOperator::stamped($user));
			\Systopic\System\Auth\LoginLog::write(\Systopic\System\Auth\Session::current()->name, 'logged out', \Systopic\System\Auth\LoginLog::LOGOUT_BUTTON);
		}
		\Systopic\System\Auth\Session::logout();
		// ?to= the page of the website the edit bar was on, only below the site root
		$to = (string) ($_GET['to'] ?? '');
		\http::redirect(str_starts_with($to, \http::$root) && !str_contains($to, '//') ? $to : './');
		exit;

	case 'editBarMenu':
		// the sections of the main menu for the edit bar on the website
		$items = [];
		$this->ensureChildrenBuilt();
		for ($child = $this->firstChild; $child !== null; $child = $child->next) {
			// the account is the avatar in the CMS menu, the name in the bar
			if ($child->inNavi && $child->name !== 'user') {
				$items[] = [
					'label' => (string) $child->label,
					'href' => $child->getHref(\http::$root),
					'icon' => trim(str_replace('fa-duotone', '', (string) $child->icon)),
				];
			}
		}
		header('Content-Type: application/json');
		echo json_encode($items, JSON_UNESCAPED_SLASHES);
		exit;

	case 'dashEdit':
		$this->state->dashEdit = empty($this->state->dashEdit);
		$this->updateView('home');
		break;

	case 'insertWidgetBefore':
	case 'insertWidgetAfter':
	case 'insertWidgetEnd':
	case 'hideWidget':
	case 'showWidget':
		// the order as the dashboard showed it last ('|' between the columns), so a widget new since then keeps its place
		['hidden' => $hidden] = $this->dashboardLayout();
		$order = array_values(array_filter((array) ($this->state->dashboard ?? []), 'is_string'));
		if (str_starts_with($this->action, 'insertWidget')) {
			$moved = (string) \http::dragdata('widget', '');
			$from = array_search($moved, $order, true);
			if ($from !== false) {
				array_splice($order, $from, 1);
				if ($this->action === 'insertWidgetEnd') {
					$ends = array_keys($order, '|', true);
					$to = $ends[(int) \http::dataset('column')] ?? count($order);
				} else {
					$to = array_search((string) \http::dataset('widget'), $order, true);
					$to = $to === false ? $from : $to + ($this->action === 'insertWidgetAfter' ? 1 : 0);
				}
				array_splice($order, $to, 0, [$moved]);
			}
		} elseif ($this->action === 'hideWidget') {
			$hidden[] = (string) \http::dataset('widget');
		} else {
			$hidden = array_diff($hidden, [(string) \http::dataset('widget')]);
		}
		$this->saveDashboardLayout($order, $hidden);
		$this->updateView('home');
		break;

	case 'dashTab':
		// "widget=tab" entries: the state keeps lists, not keys of its own
		$widget = (string) \http::dataset('widget');
		$tabs = array_filter((array) ($this->state->dashTabs ?? []), static fn($entry): bool => !str_starts_with((string) $entry, $widget . '='));
		$this->state->dashTabs = [...array_values($tabs), $widget . '=' . (string) \http::dataset('tab')];
		$this->updateView('home');
		break;

	case 'notificationPushed':
		break;
}
