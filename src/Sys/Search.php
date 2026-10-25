<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;

/**
 * The search of cms/search/ (Cmd+K). It walks every panel the user may open
 * (only those are built), so a project's own panels take part without code:
 *
 * - a panel is found by its menu name and by the fixed texts of its views
 *   (headings, field labels, legends);
 * - a panel with `public function searchHits(Search $search): void` adds its
 *   own records: `$search->add('Contacts', 'fa-user', $name, $company, $href)`.
 */
final class Search
{
    public const PER_GROUP = 8;

    private const ORDER = ['Pages' => 1, 'Media' => 2, 'Tags' => 3, 'Users' => 4, 'Settings' => 5, 'Sections' => 99];

    /** @var array<string, array{icon: string, hits: list<array{title: string, context: string, href: string}>}> */
    public array $groups = [];

    /** @var array<string, true> */
    private array $seen = [];

    private function __construct(
        public readonly string $query,
        public readonly Scope $scope,
    ) {
    }

    public static function run(string $query, PanelNode $cms): self
    {
        $search = new self(trim($query), Scope::default());
        if (mb_strlen($search->query) < 2) {
            return $search;
        }
        $search->walk($cms, []);
        uksort($search->groups, static fn(string $a, string $b): int => (self::ORDER[$a] ?? 50) <=> (self::ORDER[$b] ?? 50));
        return $search;
    }

    /** The shortcut as the user's keyboard labels it; iPads report themselves as Macintosh. */
    public static function hotkey(): string
    {
        return str_contains((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'Mac') ? '⌘K' : 'Ctrl K';
    }

    public function add(string $group, string $icon, string $title, string $context, string $href): void
    {
        $key = $group . "\0" . $title . "\0" . $href;
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;
        $this->groups[$group] ??= ['icon' => $icon, 'hits' => []];
        if (count($this->groups[$group]['hits']) < self::PER_GROUP) {
            $this->groups[$group]['hits'][] = ['title' => $title, 'context' => $context, 'href' => $href];
        }
    }

    public function matches(string $text): bool
    {
        return mb_stripos($text, $this->query) !== false;
    }

    /** The query as a LIKE pattern for a lowercased column. */
    public function like(): string
    {
        return '%' . addcslashes(mb_strtolower($this->query), '%_\\') . '%';
    }

    /** @param list<string> $trail the labels above */
    private function walk(PanelNode $panel, array $trail): void
    {
        $panel->ensureChildrenBuilt();
        foreach ($panel->children as $child) {
            if (!$child->inNavi) {
                continue;
            }
            $label = (string) $child->label;
            $href = $child->getHref(\http::$root);
            $where = implode(' › ', $trail);
            if ($this->matches($label)) {
                $this->add('Sections', 'fa-compass', $label, $where, $href);
            }
            foreach (self::texts($child->absPath) as $text) {
                if ($this->matches($text)) {
                    $this->add('Sections', 'fa-compass', $text, implode(' › ', [...$trail, $label]), $href);
                }
            }
            if (method_exists($child, 'searchHits')) {
                $child->searchHits($this);
            }
            $this->walk($child, [...$trail, $label]);
        }
    }

    /** @return list<string> headings, field labels and legends written in the views of a panel folder */
    private static function texts(string $dir): array
    {
        $dir = \fs::toNative($dir);
        $texts = [];
        foreach ($dir === '' ? [] : (glob(rtrim($dir, '/') . '/*.tpl.php') ?: []) as $file) {
            $html = preg_replace('~<\?.*?(\?>|$)~s', ' ', (string) file_get_contents($file));
            preg_match_all('~<(h[1-4]|label|legend)\b[^>]*>(.*?)</\1>~si', (string) $html, $m);
            foreach ($m[2] as $raw) {
                $text = trim(preg_replace('~\s+~', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (mb_strlen($text) > 2 && mb_strlen($text) <= 80) {
                    $texts[$text] = true;
                }
            }
        }
        return array_keys($texts);
    }
}
