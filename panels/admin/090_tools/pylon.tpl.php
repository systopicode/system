<?php
/** Navigation groups are presentation only: existing routes and saved panel state stay intact. */
$maintenance = $this->child('maintenance');
$groups = [
    ['System','cpu',[$this->child('system')]],
    ['Cleanup','broom',[$this->child('cleanup')]],
    ['Logs','file-lines',[$this->child('logs')]],
    ['Database','database',[$this->child('database')]],
    ['Remote debugging','bug',[$this->child('debug')]],
    ['Media repair','photo-film',[$this->child('media')]],
    ['Migration','repeat',[$this->child('migration')]],
];
$labels = ['links' => 'Links', 'misses' => 'Incoming · 404', 'rewrite' => 'URL paths', 'trash' => 'Trash', 'unused' => 'Unused media', 'duplicates' => 'Duplicates', 'cleanup' => 'Automatic cleanup'];
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
// The framework's eachChild/getNavi walk uses the active navigator context.
// These presentation groups also reference panels outside that context.
$groupChildren = static function ($parent) {
    $ul = \html::create('ul');
    for ($child = $parent->firstChild; $child !== null; $child = $child->next) {
        $child->label = match ($parent->name . '/' . $child->name) {
            'website/links' => 'Links',
            'website/misses' => 'Incoming · 404',
            'website/paths' => 'URL paths',
            'cleanup/automatic' => 'Automatic cleanup',
            'cleanup/trash' => 'Trash',
            'cleanup/unused' => 'Unused media',
            'cleanup/duplicates' => 'Duplicates',
            'system/systemcheck' => 'System check',
            'system/phpinfo' => 'PHP information',
            'logs/failed' => 'Failed actions',
            'logs/email' => 'Email log',
            'logs/signin' => 'Sign-in log',
            'logs/php' => 'PHP log',
            'logs/backup_log' => 'Backup log',
            default => $child->label,
        };
        $item = $child->getNaviItem();
        if ($item !== null) $ul->append($item);
    }
    return $ul;
};

?>
<div class="pylon cms" style="--pylonWidth: <?= (int) ($this->state->pylonWidth ?? 250) ?>px;">
<header></header><nav><ul>
<?php foreach ($groups as [$label, $icon, $panels]):
    $panels = array_values(array_filter($panels, static fn($panel) => $panel !== null && $panel->inNavi));
    if (!$panels) continue;
    $active = count(array_filter($panels, static fn($panel) => $panel->inPath())) > 0;
    $first = $panels[0];
?>
<li class="<?= $active ? 'active' : '' ?>"><a href="<?= $e($first->getHref(\http::$root)) ?>"<?= count($panels) > 1 || $first->firstChild ? ' data-cms-branch="1"' : '' ?>><i class="cmsIcon cmsIcon-<?= $e($icon) ?>"></i><span data-nav-label="<?= $e($label) ?>"><span><?= $e($label) ?></span></span></a>
<?php if ($active): ?>
<?php if (count($panels) > 1): ?><ul><?php foreach ($panels as $panel): ?><li class="<?= $panel->inPath() ? 'active' : '' ?>"><a href="<?= $e($panel->getHref(\http::$root)) ?>"><span data-nav-label="<?= $e($labels[$panel->name] ?? $panel->label) ?>"><span><?= $e($labels[$panel->name] ?? $panel->label) ?></span></span></a></li><?php endforeach; ?></ul>
<?php elseif ($first->firstChild): echo $groupChildren($first); endif; ?>
<?php endif; ?></li>
<?php endforeach; ?>
<?php
// Keep additional package-provided tools reachable without changing their routes.
$additional = [];
for ($panel = $this->firstChild; $panel !== null; $panel = $panel->next) {
    if (!in_array($panel->name, ['system','website','cleanup','media','maintenance','logs','database','debug','migration'], true)) $additional[] = $panel->getNaviItem();
}
for ($panel = $maintenance?->firstChild; $panel !== null; $panel = $panel->next) {
    if (!in_array($panel->name, ['links','misses','rewrite','trash','unused','duplicates','cleanup','media'], true)) $additional[] = $panel->getNaviItem();
}
$website = $this->child('website');
if ($website && $website->inNavi) {
    echo '</ul><hr><h2 class="pylonGroupTitle">Pages</h2><ul>';
    $website->label = 'Website checks';
    $item = $website->getNaviItem();
    if ($item && $website->inPath()) $item->append($groupChildren($website));
    echo $item;
}
if (array_filter($additional)) echo '</ul><hr><h2 class="pylonGroupTitle">Extensions</h2><ul>' . implode('', $additional);
?>
</ul></nav><div data-drag_type="none" data-on_draginit="draginitPylonwidth"></div>
</div>
