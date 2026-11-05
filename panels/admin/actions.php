<?php

/** @var \Systopic\System\Panels\Root\Admin\Panel $this */

use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Users\Operator as UserOperator;

switch ($this->action) {
	case 'logout':
		$user = UserAdmin::of($this->scope)->user((int) \Systopic\System\Auth\Session::id());
		if ($user) {
			$this->scope->save(UserOperator::stamped($user));
			\Systopic\System\Auth\SignInLog::write(\Systopic\System\Auth\Session::current()->name, 'signed out', \Systopic\System\Auth\SignInLog::SIGN_OUT_BUTTON);
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
					'icon' => str_replace(' cmsIcon-duotone', '', \Systopic\System\Ui\Icons::classes((string) $child->icon)),
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
	case 'hideDroppedWidget':
	case 'showWidget':
        $layout = $this->dashboardLayout();
        $hidden = $layout['hidden'];
        $count = max(1, min(3, (int) \http::dataset('columns', 3)));
        $keys = array_keys($this->dashboardWidgets());
        $posted = \http::dataset('order', []);
        if (is_string($posted)) $posted = json_decode($posted, true);
        $order = \Systopic\System\Dashboard\Layout::order(\Systopic\System\Dashboard\Layout::columns($keys, is_array($posted) ? $posted : ($layout['layouts'][$count] ?? $layout['order']), $count));
        $reordered = str_starts_with($this->action, 'insertWidget');
        if ($reordered) {
            $moved = (string) \http::dragdata('widget', '');
            $target = (string) \http::dataset('widget', '');
            $from = array_search($moved, $order, true);
            if ($from === false || $moved === $target) break;
            array_splice($order, $from, 1);
            if ($this->action === 'insertWidgetEnd') {
                $column = max(0, min($count - 1, (int) \http::dataset('column', 0)));
                $ends = array_keys($order, '|', true);
                $to = $ends[$column] ?? count($order);
            } else {
                $to = array_search($target, $order, true);
                if ($to === false) break;
                $to += $this->action === 'insertWidgetAfter' ? 1 : 0;
            }
            array_splice($order, $to, 0, [$moved]);
            $hidden = array_diff($hidden, [$moved]);
        } elseif (in_array($this->action, ['hideWidget', 'hideDroppedWidget'], true)) {
            $key = $this->action === 'hideDroppedWidget' ? (string) \http::dragdata('widget', '') : (string) \http::dataset('widget');
            if (in_array($key, $keys, true)) $hidden[] = $key;
        } else {
            $hidden = array_diff($hidden, [(string) \http::dataset('widget')]);
        }
        $this->saveDashboardLayout($order, $hidden, $count, $reordered);
		$this->updateView('home');
		break;

	case 'dashTab':
		// "widget=tab" entries: the state keeps lists, not keys of its own
		$widget = (string) \http::dataset('widget');
		$tabs = array_filter((array) ($this->state->dashTabs ?? []), static fn($entry): bool => !str_starts_with((string) $entry, $widget . '='));
		$this->state->dashTabs = [...array_values($tabs), $widget . '=' . (string) \http::dataset('tab')];
		break;

	case 'notificationPushed':
		break;
}
