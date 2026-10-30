<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Controlmap\Discover;

use Systopic\System\Controlmap\DiscoveredPanel;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

/**
 * cms/controlmap/discover — the panels one level below the node chosen in
 * the tree (the parent's pylon), or all of them (`?all=1`). Read off the
 * code on every view, nothing kept.
 *
 * @property \Systopic\System\Panels\Root\Cms\Controlmap\Panel $parent
 */
class Panel extends CmsPanel {

	public $icon = 'fa-regular fa-magnifying-glass';

	public bool $showAll {
		get => !empty($this->state->showAll);
	}

	function getDefaultState() {
		return (object) ['showAll' => FALSE];
	}

	/**
	 * A node of the tree and the switch are gets to this panel; the layer
	 * stays the same, so the views have to be marked (see the audit panel).
	 */
	public function onLoadSelected(): void {
		if (\http::method() !== 'GET') {
			return;
		}
		if (\http::get('all', NULL) !== NULL) {
			$this->state->showAll = (bool) \http::get('all');
		} elseif (\http::get('panel', NULL) !== NULL) {
			$this->state->showAll = FALSE;
		}
		$this->updateViews(['main', 'controls']);
	}

	/**
	 * One panel as a row of the table: name and path (indented by `$indent`
	 * levels), where it comes from, what its code asks for access, its
	 * actions as chips — an entry action (`$entryActions`) marked.
	 */
	public function row(DiscoveredPanel $panel, int $indent = 0): string {
		$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
		$classTitle = $panel->className === NULL
			? 'No class file: the parent bequeaths its class, or PanelNode'
			: $panel->className . ($panel->extends !== NULL ? ' extends ' . $panel->extends : '') . ' (' . $panel->classFile . ')';

		$origins = '';
		foreach ($panel->origins as $origin) {
			$winning = $origin === $panel->origin;
			$origins .= "<span class='origin" . ($winning ? ' is-winning' : '') . "' title='"
				. ($winning ? 'Class and templates come from here' : 'Contributes folders') . "'>{$e($origin)}</span>";
		}

		$grants = '';
		foreach (['build' => ['fa-eye', 'buildGranted(): who sees the panel'], 'action' => ['fa-bolt', 'actionGranted(): who runs its actions']] as $key => [$icon, $title]) {
			if (isset($panel->grants[$key])) {
				$grants .= "<div title='$title'><i class='fa-regular $icon'></i>" . $this->terms($panel->grants[$key]) . '</div>';
			}
		}
		if ($panel->grants === [] && !$panel->isPlain) {
			$grants = "<span class='none' title='Not overridden: the parent class decides (PanelNode: anybody signed in)'>inherited</span>";
		}

		$actions = '';
		foreach ($panel->actions as $action) {
			$title = implode(', ', $action->kinds) . ' — ' . implode(', ', $action->places)
				. ($action->entry ? ' — may be opened from outside (GET)' : '');
			// the first place it is named, in syscoder
			$first = (string) ($action->places[0] ?? '');
			$colon = strrpos($first, ':');
			$href = $colon === FALSE ? NULL : $this->syscoder($panel->dir . '/' . substr($first, 0, $colon), (int) substr($first, $colon + 1));
			$tag = $href === NULL ? 'span' : 'a';
			$link = $href === NULL ? '' : " href='{$e($href)}' target='syscoder'";
			$actions .= "<$tag class='action" . ($action->entry ? ' is-entry' : '') . "'$link title='{$e($title)}'>"
				. ($action->entry ? "<i class='fa-regular fa-arrow-right-to-bracket'></i>" : '')
				. "{$e($action->name)}</$tag>";
		}
		$classHref = $panel->classFile === NULL ? NULL : $this->syscoder($panel->dir . '/' . $panel->classFile, 1);
		$classLink = $classHref === NULL ? '' : " <a class='code' href='{$e($classHref)}' target='syscoder' title='{$e($panel->classFile)} in syscoder'><i class='fa-regular fa-code'></i></a>";

		$name = $panel->children === []
			? $e($panel->name)
			: "<a class='ajax' href='./?panel=" . rawurlencode($panel->path) . "' title='Show the panels below'>{$e($panel->name)}</a>";
		return "<tr class='" . ($panel->isPlain ? 'plain' : '') . "' style='--depth: $indent'>"
			. "<td class='panel' title='{$e($classTitle)}'><span class='name'>$name</span>$classLink<span class='path'>{$e($panel->path)}</span></td>"
			. "<td class='origin'>$origins</td>"
			. "<td class='grants'>$grants</td>"
			. "<td class='actions'>$actions</td>"
			. '</tr>';
	}

	/**
	 * The address that opens a file at a line in syscoder (tools/syscoder,
	 * the editor behind the debug links) — null where it is not installed
	 * under the document root, as on a stage.
	 */
	public function syscoder(string $file, int $line): ?string {
		static $base = FALSE;
		if ($base === FALSE) {
			$base = NULL;
			$root = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
			foreach (['/packages/tools/syscoder/', '/_tools/syscoder/'] as $candidate) {
				if ($root !== '' && is_file($root . $candidate . 'index.php')) {
					$base = $candidate;
					break;
				}
			}
		}
		return $base === NULL ? NULL : $base . '?file=' . rawurlencode(\fs::toInternal($file)) . '&line=' . max(1, $line);
	}

	/** @param list<string> $terms what a grant method asks for, as chips */
	private function terms(array $terms): string {
		$html = '';
		foreach ($terms as $term) {
			$known = in_array($term, ['custom', 'parent', 'superuser', 'signed in', 'everyone', 'nobody'], TRUE);
			$html .= "<span class='term" . ($known ? ' is-' . str_replace(' ', '', $term) : '') . "'>"
				. htmlspecialchars($term, ENT_QUOTES) . '</span>';
		}
		return $html;
	}
}
