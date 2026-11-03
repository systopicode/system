<?php
$items = [];
$collect = function ($child) use (&$items) {
	$item = $child->getNaviItem();
	if ($item === null) {
		return;
	}
	$items[] = [
		'order' => $child->naviOrder ?? \Systopic\System\Panels\Tree\PanelTree::folderToOrder((string) $child->folder),
		'folder' => (string) $child->folder,
		'item' => $item,
	];
};
for ($child = $this->firstChild; $child !== null; $child = $child->next) $collect($child);
usort($items, static function (array $a, array $b): int {
	return $a['order'] <=> $b['order'] ?: strnatcasecmp($a['folder'], $b['folder']);
});
foreach ($items as $row) {
	echo $row['item'];
}
