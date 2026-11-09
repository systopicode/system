<?php
$pylonWidth = $this->childSelected?->state->pylonWidth ?? $this->state->pylonWidth ?? 200;
$items = [];
for ($node = $this->firstChild; $node !== null; $node = $node->next) $items[$node->name] = $node;
?>
<div class="pylon cms" style="--pylonWidth: <?= (int) $pylonWidth ?>px;"><header></header><nav><ul>
<?php foreach (['environment','pagesettings','mediasettings','tags','theme'] as $name) {
    if (isset($items[$name])) { echo $items[$name]->getNaviItem(); unset($items[$name]); }
} ?>
<?php if ($items) { ?></ul><hr><h2 class="pylonGroupTitle">Pages</h2><ul><?php foreach ($items as $node) echo $node->getNaviItem(); } ?>
</ul></nav>
<?php if ($this->childSelected?->name === 'environment') \Systopic\System\Settings\EnvironmentForm::legend(); // what the marks of Environment mean ?>
<div data-drag_type="none" data-on_draginit="draginitPylonwidth"></div></div>
