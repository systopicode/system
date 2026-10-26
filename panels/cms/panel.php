<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\LastLogin\LastLogin;
use Systopic\System\Queries\UserAdmin\UserAdmin;

class Panel extends PanelNode {

	/** The request's scope — every CMS panel reads and writes through it. */
	public Scope $scope {
		get => Scope::default();
	}

	public $loadChildModules = TRUE;
	public $showSubnavi = TRUE;

	function buildGranted() {
		if ($this->path === ['cms']) {
			if (!\Systopic\System\Auth\Session::known()) {
				// Only redirect when the user is actually requesting a CMS path.
				// buildGranted() fires for ALL panels, so a request to e.g. /auth/
				// would otherwise trigger this redirect for every cms sibling panel.
				$reqPath = \app::request()->modulePath;
				if ($reqPath === ['cms', 'auth']) {
					// The login form lives below this panel: denying cms here
					// would prune the branch the anonymous user is being sent
					// to, and /cms/auth/ would render an empty document.
					return TRUE;
				}
				if (!empty($reqPath) && $reqPath[0] === 'cms') {
					\http::redirect(\http::$root . 'cms/auth/');
				}
				return FALSE;
			} else {
				return TRUE;
			}
		}

		return \Systopic\System\Auth\Session::hasRole('editor') ||
				STAGE === 'x_DEV' ||
				\app::request()->modulePath === ['cms', 'auth']
		;
	}

	function onLoad() {
		\fs::load('fsUploader');
		if ($this->path === ['cms']) { // update navi if change
			$selected = $this->childSelected;
			$deep = $selected;
			while ($deep !== null && $deep->childSelected !== null && $deep->childSelected->inNavi) {
				$deep = $deep->childSelected;
			}
			// A lightbox (search) is not a module. Remember the one that was open.
			if ($selected !== null && $selected->inNavi) {
				if ($this->state->submoduleName !== $selected->name) {
					$this->updateView('menu');
				}
				$this->state->submoduleName = $selected->name;
				$this->state->submodulePath = $deep !== null ? $deep->path : $selected->path;
			}
			$this->checkReleaseNoteRedirect();
		}
	}

	/** Whoever has not logged in since the last release note is sent to read it. */
	private function checkReleaseNoteRedirect() {
		if (!defined('RELEASE_NOTE_DATE') || !\Systopic\System\Auth\Session::known() || !\Systopic\System\Auth\Session::id()) {
			return;
		}
		$lastLogin = LastLogin::of($this->scope, (int) \Systopic\System\Auth\Session::id());
		if (($lastLogin?->getTimestamp() ?? 0) < strtotime(RELEASE_NOTE_DATE)) {
			if (strpos($_SERVER['REQUEST_URI'] ?? '', 'cms/about/releases') === false) {
				\http::redirect('cms/about/releases#cms/about/releases');
			}
		}
	}

	/**
	 * The dashboard widgets of the panels one and two levels down, in tree
	 * order: `dashboard.tpl.php` and `dashboard.<name>.tpl.php` in the panel's
	 * folder, keyed `pages`, `pages.todo`, `settings/tags`.
	 *
	 * Call it while this panel renders: the navigator opens each child in its
	 * own folder. Children it hides (no right to them) bring no widgets.
	 *
	 * @return array<string, array{panel: PanelNode, file: string}>
	 */
	public function dashboardWidgets(): array {
		$widgets = [];
		$collect = function (PanelNode $panel) use (&$widgets): void {
			$files = array_filter(glob('dashboard*.tpl.php') ?: [], static fn(string $f): bool => (bool) preg_match('~^dashboard(\.\w+)?\.tpl\.php$~', $f));
			usort($files, static fn(string $a, string $b): int => [strlen($a) > 17, $a] <=> [strlen($b) > 17, $b]);
			foreach ($files as $file) {
				$key = implode('/', array_slice($panel->path, 1)) . substr($file, 9, -8);
				$widgets[$key] = ['panel' => $panel, 'file' => getcwd() . '/' . $file];
			}
		};
		$this->eachChild(function (PanelNode $child) use ($collect): void {
			$collect($child);
			$child->eachChild($collect);
		});
		return $widgets;
	}

	/** "5 minutes ago", "yesterday", "12 Mar 2026" - for the lists of the dashboard. */
	public static function ago(?string $datetime): string {
		$time = $datetime ? strtotime($datetime) : false;
		if ($time === false) {
			return '';
		}
		$seconds = time() - $time;
		$plural = static fn(int $n, string $unit): string => "$n $unit" . ($n === 1 ? '' : 's') . ' ago';
		return match (true) {
			$seconds < 60 => 'just now',
			$seconds < 3600 => $plural(intdiv($seconds, 60), 'minute'),
			$seconds < 86400 => $plural(intdiv($seconds, 3600), 'hour'),
			$seconds < 172800 => 'yesterday',
			$seconds < 604800 => $plural(intdiv($seconds, 86400), 'day'),
			default => \Systopic\System\Sys\LocalTime::format($time, 'j M Y'),
		};
	}

	/**
	 * A widget with tabs (`nav.dashTabs`, action dashTab): the key of the open
	 * one, the first until the user picks another in this session. Not named
	 * like the action: app.php calls a method of that name instead of actions.php.
	 *
	 * @param array<string, string> $tabs key => label
	 */
	public static function widgetTab(PanelNode $widget, string $key, array $tabs): string {
		$cms = $widget;
		while ($cms->name !== 'cms' && $cms->parent !== null) {
			$cms = $cms->parent;
		}
		foreach ((array) ($cms->state->dashTabs ?? []) as $entry) {
			[$widget, $tab] = explode('=', (string) $entry, 2) + ['', ''];
			if ($widget === $key && isset($tabs[$tab])) {
				return $tab;
			}
		}
		return (string) array_key_first($tabs);
	}

	/** @return array{order: list<string>, hidden: list<string>} how this user arranged the dashboard */
	public function dashboardLayout(): array {
		$user = \Systopic\System\Auth\Session::current()->user;
		$data = $user === null ? null : json_decode((string) $user->sessiondata?->value, true);
		$keys = static fn($list): array => array_values(array_filter((array) $list, 'is_string'));
		return [
			'order' => $keys($data['dashboard']['order'] ?? []),
			'hidden' => $keys($data['dashboard']['hidden'] ?? []),
		];
	}

	/** Columns of the dashboard; in the stored order a '|' closes one. */
	public const DASH_COLUMNS = 3;

	/**
	 * The widgets column by column. An order from before the columns is dealt
	 * out evenly, a widget new since the last save joins the shortest column.
	 *
	 * @param list<string> $keys
	 * @param list<string> $order
	 * @return list<list<string>>
	 */
	public function dashboardColumns(array $keys, array $order): array {
		$columns = [[]];
		foreach ($order as $entry) {
			if ($entry === '|') {
				count($columns) < self::DASH_COLUMNS && $columns[] = [];
			} elseif (in_array($entry, $keys, true)) {
				$columns[array_key_last($columns)][] = $entry;
			}
		}
		$columns = array_pad($columns, self::DASH_COLUMNS, []);
		$new = array_values(array_diff($keys, array_merge(...$columns)));
		if (!in_array('|', $order, true)) {
			$all = [...array_merge(...$columns), ...$new];
			return array_pad(array_chunk($all, max(1, (int) ceil(count($all) / self::DASH_COLUMNS))), self::DASH_COLUMNS, []);
		}
		foreach ($new as $key) {
			$sizes = array_map('count', $columns);
			$columns[array_search(min($sizes), $sizes, true)][] = $key;
		}
		return $columns;
	}

	/** Kept with the account, so the arrangement follows the user to every device. */
	public function saveDashboardLayout(array $order, array $hidden): void {
		$user = UserAdmin::of($this->scope)->user((int) \Systopic\System\Auth\Session::id());
		if ($user === null) {
			return;
		}
		$data = json_decode((string) $user->sessiondata?->value, true);
		$data = is_array($data) ? $data : [];
		$seen = [];
		$order = array_values(array_filter($order, static function (string $entry) use (&$seen): bool {
			return $entry === '|' || !isset($seen[$entry]) && $seen[$entry] = true;
		}));
		$data['dashboard'] = ['order' => $order, 'hidden' => array_values(array_unique($hidden))];
		$user->sessiondata = new \Systopic\Db\Type\Text((string) json_encode($data));
		$this->scope->save($user);
	}

	function actionGranted($action = NULL) {
		return parent::actionGranted($action) || STAGE === 'DEV';
	}

	function getDefaultState() {
		if ($this->name === 'cms' || $this->parent->name === 'cms') {
			return (object) [
						'childSelected' => FALSE,
						'pylonWidth' => 180,
						'submoduleName' => FALSE,
						'submodulePath' => [],
						'dashboard' => [],
						'dashTabs' => [],
						'dashEdit' => false,
			];
		}
		return parent::getDefaultState();
	}

	function sendUserEmail($template, $subject, $user) {
		$path = $template;
		if (!preg_match('~^(/|[A-Za-z]:)~', str_replace('\\', '/', $template))) {
			$path = rtrim($this->absPath, '/') . '/' . $template;
		}
		ob_start();
		include \fs::toNative($path);
		$body = ob_get_clean();

		return (new \emailSmtp())
						->To($user->email, $user->fullname)
						->Subject($subject)
						->Body($body)
						->send()
		;
	}
}
