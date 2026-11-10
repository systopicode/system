<?php
/**
 * The settings menu: one area per package — the panels it brings (Theme and
 * Tags are the system's), then its configuration (035_config, `?package=`).
 * Which package brought a panel says the folder it lies in; a panel of the
 * project itself goes to an area of its own at the end.
 *
 * @var \Systopic\System\Panels\Root\Admin\Settings\Panel $this
 */

use Systopic\System\Settings\EnvironmentForm;

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$pylonWidth = $this->childSelected?->state->pylonWidth ?? $this->state->pylonWidth ?? 200;
$items = [];
for ($node = $this->firstChild; $node !== null; $node = $node->next) $items[$node->name] = $node;
$config = $items['config'] ?? null;
unset($items['config']);

$packageOf = static function ($node): string {
    $path = str_replace('\\', '/', (string) ($node->absPath ?? ''));
    return preg_match('~/(?:packages|vendor/systopic)/([a-z]+)/panels/~', $path, $m) === 1 ? $m[1] : 'project';
};
$byPackage = [];
foreach ($items as $node) {
    if ($node->inNavi) $byPackage[$packageOf($node)][] = $node;
}
$configured = $config === null ? [] : EnvironmentForm::packages();
$order = array_values(array_unique([...$configured, ...array_keys($byPackage)]));
?>
<div class="pylon cms" style="--pylonWidth: <?= (int) $pylonWidth ?>px;"><header></header><nav>
<?php $first = true;
foreach ($order as $package) {
    $own = $byPackage[$package] ?? [];
    $hasConfig = in_array($package, $configured, true);
    if ($own === [] && !$hasConfig) continue;
    $title = $package === 'project' ? 'Project' : EnvironmentForm::packageTitle($package);
    if (!$first) echo '<hr>';
    $first = false;
    ?>
<h2 class="pylonGroupTitle envPackageTitle"><?php if ($package !== 'project') { ?><i class="<?= $e(EnvironmentForm::packageIcon($package)) ?>" aria-hidden="true"></i> <?php } ?><?= $e($title) ?></h2><ul>
<?php
    foreach ($own as $node) echo $node->getNaviItem();
    if ($hasConfig) {
        $active = $config->inPath() && ($config->state->package ?? '') === $package;
        $li = \html::create('li.nav_config')->attr('data-name', 'config')->attr('data-package', $package)->class($active ? 'active' : null);
        $a = $li->append('a')->href($config->getHref(\http::$root) . '?package=' . rawurlencode($package));
        $a->append('i')->class(\Systopic\System\Ui\Icons::classes('cmsIcon cmsIcon-sliders'))->attr('aria-hidden', 'true');
        $a->append('span')->attr('data-nav-label', 'Config')->append('span')->text('Config');
        echo $li;
    }
    ?>
</ul>
<?php } ?>
</nav>
<div data-drag_type="none" data-on_draginit="draginitPylonwidth"></div></div>
