<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Access\Panelmap\Discover;

use Systopic\System\Controlmap\DiscoveredPanel;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

/**
 * cms/access/panelmap/discover — the panels one level below the node chosen in
 * the tree (the parent's pylon), or all of them (`?all=1`). Read off the
 * code on every view, nothing kept.
 *
 * @property \Systopic\System\Panels\Root\Cms\Access\Panelmap\Panel $parent
 */
class Panel extends CmsPanel {

	public $icon = 'cmsIcon cmsIcon-magnifying-glass';

	public bool $showAll {
		get => !empty($this->state->showAll);
	}

	/**
	 * What "Show all" is filtered by: the field above the tree
	 * (`filterPanels`), or `?all=1&filter=cmsEditor` (the roles link here).
	 * It filters the tree and the table alike (`matches()`).
	 */
	public string $filter {
		get => (string) ($this->state->filter ?? '');
	}

	function getDefaultState() {
		return (object) ['showAll' => FALSE, 'filter' => ''];
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
			$this->state->filter = trim((string) \http::get('filter', ''));
		} elseif (\http::get('panel', NULL) !== NULL) {
			$this->state->showAll = FALSE;
		}
		$this->updateViews(['main']);   // the pylon the parent updates
	}

	/**
	 * Does the panel have what "Show all" is filtered by — in its name,
	 * access in code or actions? Not in its path nor its origins: every
	 * panel below cms, or from the cms package, would match "cms". Without
	 * a filter, or not showing all, every one does.
	 */
	public function matches(DiscoveredPanel $panel): bool {
		$q = mb_strtolower($this->filter);
		if (!$this->showAll || $q === '') {
			return TRUE;
		}
		$text = [$panel->name];
		foreach ($panel->grants as $terms) {
			array_push($text, ...array_map('strval', array_keys($terms)));
		}
		foreach ($panel->actions as $action) {
			$text[] = $action->name;
		}
		return str_contains(mb_strtolower(implode(' ', $text)), $q);
	}

	/** The panel or one below it matches: the tree shows it while filtering. */
	public function leadsToMatch(DiscoveredPanel $panel): bool {
		if ($this->matches($panel)) {
			return TRUE;
		}
		foreach ($panel->children as $child) {
			if ($this->leadsToMatch($child)) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * One panel as a row of the table: name and path (indented by `$indent`
	 * levels), where it comes from, what its code asks for access, its
	 * actions as chips — an entry action (`$entryActions`) marked. A click
	 * on a chip opens the panel in the configs (the lightbox `config/`), its
	 * </> the line in syscoder; what "Show all" is filtered by is marked
	 * (`is-match`).
	 */
	public function row(DiscoveredPanel $panel, int $indent = 0): string {
		$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
		$match = $this->matcher();
		$classTitle = $panel->className === NULL
			? 'No class file: the parent bequeaths its class, or PanelNode'
			: $panel->className . ($panel->extends !== NULL ? ' extends ' . $panel->extends : '') . ' (' . $panel->classFile . ')';

		$origins = '';
		foreach ($panel->origins as $origin) {
			$winning = $origin === $panel->origin;
			$origins .= $this->chip('origin' . ($winning ? ' is-winning' : ''), $e($origin), $panel->path, 'layer.' . $origin, NULL,
				($winning ? 'Class and templates come from here' : 'Contributes folders') . ' — its panel.config.json');
		}

		$grants = '';
		foreach (['build' => ['cmsIcon-eye', 'buildGranted(): who sees the panel', 'build'], 'action' => ['cmsIcon-bolt', 'actionGranted(): who runs its actions', 'actions']] as $key => [$icon, $title, $focus]) {
			if (isset($panel->grants[$key])) {
				$file = $panel->classFile === NULL ? NULL : $panel->dir . '/' . $panel->classFile;
				$grants .= "<div title='$title'><i class='cmsIcon $icon'></i>" . $this->terms($panel->grants[$key], $file, $panel->path, $focus) . '</div>';
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
			$code = $colon === FALSE ? NULL : $this->syscoder($panel->dir . '/' . substr($first, 0, $colon), (int) substr($first, $colon + 1));
			$actions .= $this->chip('action' . ($action->entry ? ' is-entry' : '') . $match($action->name),
				($action->entry ? "<i class='cmsIcon cmsIcon-right-to-bracket'></i>" : '') . $e($action->name),
				$panel->path, 'action.' . $action->name, $code, $title);
		}
		$classHref = $panel->classFile === NULL ? NULL : $this->syscoder($panel->dir . '/' . $panel->classFile, 1);
		$classLink = $classHref === NULL ? '' : " <a class='code' href='{$e($classHref)}' target='syscoder' title='{$e($panel->classFile)} in syscoder'><i class='cmsIcon cmsIcon-file-lines'></i></a>";

		$name = $panel->children === []
			? $e($panel->name)
			: "<a class='ajax' href='{$e(\Systopic\System\Panels\Root\Cms\Access\Panelmap\Panel::nodeHref($panel->path))}' title='Show the panels below'>{$e($panel->name)}</a>";
		return "<tr class='" . ($panel->isPlain ? 'plain' : '') . "' style='--depth: $indent'>"
			. "<td class='panel' title='{$e($classTitle)}'><div><span class='name" . $match($panel->name) . "'>$name</span>$classLink<span class='path'>{$e($panel->path)}/</span></div></td>"
			. "<td><div class='discoverChips'>$origins</div></td>"
			. "<td class='grants'>$grants</td>"
			. "<td><div class='discoverChips'>$actions</div></td>"
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

	/**
	 * What a grant method asks for, as chips — `$focus` (build, actions) in
	 * the lightbox, the </> its line in the class file.
	 *
	 * @param array<string, int> $terms term => line
	 */
	private function terms(array $terms, ?string $file, string $path, string $focus): string {
		$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
		$match = $this->matcher();
		$html = '';
		foreach ($terms as $term => $line) {
			$term = (string) $term;
			$known = in_array($term, ['custom', 'parent', 'superuser', 'signed in', 'everyone', 'nobody'], TRUE);
			$html .= $this->chip('term' . ($known ? ' is-' . str_replace(' ', '', $term) : '') . $match($term), $e($term),
				$path, $focus, $file === NULL ? NULL : $this->syscoder($file, $line), "Line $line");
		}
		return $html;
	}

	/**
	 * A chip: its label opens the panel in the configs (`config/`, the
	 * lightbox) at `$focus`; a </> behind it the code, where syscoder is
	 * there. `$label` is HTML.
	 */
	private function chip(string $class, string $label, string $path, string $focus, ?string $code, string $title): string {
		$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
		$config = Config\Panel::href($this->href, $path, $focus);
		return "<span class='$class' title='{$e($title)}'>"
			. "<a class='ajax' href='{$e($config)}'>$label</a>"
			. ($code === NULL ? '' : "<a class='code' href='{$e($code)}' target='syscoder' title='In syscoder'><i class='cmsIcon cmsIcon-file-lines'></i></a>")
			. '</span>';
	}

	/** ' is-match' for a text that has what "Show all" is filtered by, else '' */
	public function matcher(): \Closure {
		$q = $this->showAll ? mb_strtolower($this->filter) : '';
		return static fn(string $text): string => $q !== '' && str_contains(mb_strtolower($text), $q) ? ' is-match' : '';
	}
}
