<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Tags\Edit;

use Systopic\System\Client\ClientExport;
use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Tables\Tags\Model as Tag;

/**
 * The lightbox of one tag (`?id=`): its name, to rename, and how many nodes
 * carry it.
 */
class Panel extends PanelNode {

	public bool $inNavi = false;

	public Scope $scope {
		get => Scope::default();
	}

	public ?Tag $tag {
		get => ($id = (int) (\http::dataset('tag_id') ?: \http::get('id'))) > 0 ? TagGroups::tagById($this->scope, $id) : NULL;
	}

	/** @var list<int> the nodes carrying the tag, published or not */
	public array $nodeIds {
		get => $this->tag ? TagLinks::nodeIdsOf($this->scope, (int) $this->tag->id) : [];
	}

	/**
	 * One row per node the tag is on. A page links into the page, a file into
	 * its editor. The node id is what drops the assignment.
	 *
	 * @return list<array{nodeId:int, label:string, href:string}>
	 */
	public function assignments(): array
	{
		$ids = $this->nodeIds;
		if ($ids === []) {
			return [];
		}
		$byNode = $this->assignmentRows($ids);
		$pages = $this->pagesBase();
		$rank = ['de' => 0, 'en' => 1, '*' => 2];
		$out = [];
		foreach ($ids as $nodeId) {
			$rows = $byNode[$nodeId] ?? [];
			usort($rows, static function (array $a, array $b) use ($rank): int {
				$ra = $rank[$a['page_language_iso'] ?? ''] ?? 3;
				$rb = $rank[$b['page_language_iso'] ?? ''] ?? 3;
				return $ra <=> $rb;
			});
			$label = '';
			$media = false;
			foreach ($rows as $row) {
				if (($row['node_type'] ?? '') === 'media') {
					$media = true;
				}
				$pageLabel = trim((string) ($row['page_label'] ?? ''));
				if ($label === '' && $pageLabel !== '') {
					$label = $pageLabel;
				}
				$fileLabel = trim((string) (($row['media_label'] ?? '') !== '' ? $row['media_label'] : ($row['media_filename'] ?? '')));
				if ($label === '' && $fileLabel !== '') {
					$label = $fileLabel;
					$media = true;
				}
			}
			$out[] = [
				'nodeId' => $nodeId,
				'label'  => $label !== '' ? $label : ($media ? 'File ' : 'Page ') . $nodeId,
				'href'   => $media
					? $pages . 'editor/file/?target_id=' . $nodeId
					: $pages . '?id=' . $nodeId,
			];
		}
		usort($out, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
		return $out;
	}

	/** @param list<int> $nodeIds @return array<int, list<array<string, mixed>>> */
	private function assignmentRows(array $nodeIds): array
	{
		$in = implode(',', array_map(static fn(int $id): int => $id, $nodeIds));
		$nodes = \Systopic\Db\Schema\Schema::tableNameOf(\Systopic\System\Tables\Nodes\Model::class);
		$medias = \Systopic\Db\Schema\Schema::tableNameOf(\Systopic\System\Tables\Medias\Model::class);
		$pages = class_exists(\Systopic\Cms\Tables\Pages\Model::class)
			? \Systopic\Db\Schema\Schema::tableNameOf(\Systopic\Cms\Tables\Pages\Model::class)
			: null;
		$sql = 'SELECT n.node_id, n.node_type, m.media_label, m.media_filename'
			. ($pages !== null ? ', p.page_label, p.page_language_iso' : '')
			. " FROM {$nodes} n"
			. " LEFT JOIN {$medias} m ON m.media_node_id = n.node_id"
			. ($pages !== null ? " LEFT JOIN {$pages} p ON p.page_node_id = n.node_id" : '')
			. " WHERE n.node_id IN ($in)";
		$grouped = [];
		foreach ($this->scope->connection->all($sql) as $row) {
			$grouped[(int) $row['node_id']][] = $row;
		}
		return $grouped;
	}

	private function pagesBase(): string
	{
		$panel = $this;
		while ($panel->parent !== null && $panel->name !== 'cms') {
			$panel = $panel->parent;
		}
		return rtrim($panel->href, '/') . '/pages/';
	}

	function getDefaultState() {
		return (object) ['hiddenViews' => []];
	}

	/** panel.js reads the tag's id from `__data.tag` to rename it. */
	public function exportClientData(): ClientExport {
		$export = parent::exportClientData();
		if ($this->tag) {
			$export->data->tag = ['id' => (int) $this->tag->id, 'name' => $this->tag->name];
		}
		return $export;
	}
}
