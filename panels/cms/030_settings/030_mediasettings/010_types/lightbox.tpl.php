<?php
use Systopic\System\Media\GroupPolicy;
$group = $this->groupData();
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
if ($group === null) { message::error('Media group not found.'); return; }
$templates = $this->state->kind === 'templates';
$picked = $templates ? array_filter(array_map('trim', explode(';', (string) ($group['templates'] ?? ''))))
    : GroupPolicy::parse((string) ($group['file_extension'] ?? ''));
?>
<div class="cmslightbox cms mediaTypes" data-size="2">
<section><header><h1><?= $templates ? 'Templates' : 'File types' ?> · <?= $e($group['label'] ?? $this->state->group) ?></h1>
<ul class="barmenu"><li><a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a></li></ul></header>
<main><p class="tagsHint">Changes are saved immediately. <?= $templates ? 'No selection: all templates.' : 'No selection: all supported types.' ?></p>
<div class="mediaTypeChoices">
<?php foreach ($this->choices() as $key => [$label, $capability]) { $id = 'media-choice-' . md5((string) $key); ?>
<div class="mediaTypeChoice"><input class="custom" id="<?= $id ?>" type="checkbox" aria-label="<?= $e($label) ?>" name="value" data-choice="<?= $e($key) ?>" data-on_change="toggle"<?= in_array($key, $picked, true) ? ' checked' : '' ?>><label for="<?= $id ?>" aria-hidden="true"></label><label class="mediaChoiceText" for="<?= $id ?>"><?= $e($label) ?><?php if (!$templates || $capability !== $label) { ?><small><?= $e($capability) ?></small><?php } ?></label></div>
<?php } ?>
<?php if ($this->choices() === []) { ?><p class="tagsHint">No templates available in this project.</p><?php } ?>
</div></main></section></div>
