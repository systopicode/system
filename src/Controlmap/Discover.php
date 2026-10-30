<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

use Systopic\System\Panels\Builder\FolderResolver;
use Systopic\System\Panels\PanelRootFolder;
use Systopic\System\Panels\Tree\PanelTree;

/**
 * Every panel of the installation and the actions it has — read off the
 * folders and the code, without building a panel or running a line of it.
 *
 * The folders are merged as the panel tree merges them
 * (`FolderResolver::discover()`, every registered root, last wins), so what
 * shows here is what a request could reach — including the panels no
 * access check lets the current user build. The class file and
 * `actions.php` are those of the winning folder, as at runtime
 * (`PanelBuilder` changes into it before an action runs).
 *
 * Nothing is stored: the code is the truth, and reading it takes a moment.
 * The configs that say who may do what refer to the logical paths found
 * here (cms/controlmap, see `.cursor/docs/controlmap.md`).
 */
final class Discover
{
    /** @param list<PanelRootFolder> $roots in registration order */
    public function __construct(private readonly array $roots) {}

    /** The roots of the panel tree of this request. */
    public static function ofRequest(): self
    {
        return new self(PanelTree::getInstance()?->getRoots() ?? []);
    }

    /** @return list<DiscoveredPanel> the top-level panels, everything below hanging off them */
    public function panels(): array
    {
        return $this->below([], []);
    }

    /**
     * @param list<string> $realPath folders, as FolderResolver takes them
     * @param list<string> $path     logical names
     * @return list<DiscoveredPanel>
     */
    private function below(array $realPath, array $path): array
    {
        $out = [];
        foreach (FolderResolver::discover($realPath, $this->roots) as $hit) {
            $logical = [...$path, $hit->name];
            $dir = rtrim($hit->absPath, '/');
            // the class file of the winning folder, as PanelBuilder resolves it
            $classFile = null;
            foreach (['panel.php', 'module.php'] as $candidate) {
                if (\fs::isFile("$dir/$candidate")) {
                    $classFile = $candidate;
                    break;
                }
            }
            $class = $classFile === null ? null : ClassScanner::scan("$dir/$classFile");

            $actions = ActionScanner::scan($dir);
            foreach ($class['methods'] ?? [] as $method => $line) {
                $actions[$method] ??= new DiscoveredAction($method);
                $actions[$method]->add('method', (string) $classFile, $line);
            }
            foreach ($class['entry'] ?? [] as $entry) {
                if (isset($actions[$entry])) {
                    $actions[$entry]->entry = true;
                }
            }

            $out[] = new DiscoveredPanel(
                path: implode('/', $logical),
                name: $hit->name,
                folder: $hit->folder,
                origin: self::origin($hit->absPath),
                dir: $dir,
                origins: array_values(array_unique(array_map(self::origin(...), $hit->altPaths))),
                classFile: $classFile,
                className: $class['class'] ?? null,
                extends: $class['extends'] ?? null,
                grants: $class['grants'] ?? [],
                actions: $actions,
                hasTemplates: (\fs::glob("$dir/*.tpl.php") ?: []) !== [],
                children: $this->below([...$realPath, $hit->folder], $logical),
            );
        }
        return $out;
    }

    /** The package a folder belongs to — `system`, `cms` … — or `project`. */
    public static function origin(string $absPath): string
    {
        $path = str_replace('\\', '/', $absPath);
        if (preg_match('~/(?:packages|vendor/systopic)/([^/]+)/panels/~i', $path, $m)) {
            return strtolower($m[1]);
        }
        return 'project';
    }
}
