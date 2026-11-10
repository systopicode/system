<?php
use Systopic\System\Media\GroupPolicy;
use Systopic\System\Settings\ProjectFile;
if (!in_array($this->action, ['toggle','templateMode'], true)) return;
$choice = (string) http::dataset('choice');
$group = $this->groupData();
$path = ProjectFile::path('media');
if (!$path || !$group || ($this->action === 'toggle' && !isset($this->choices()[$choice]))) { message::error('That choice could not be saved.'); return; }
$templates = $this->state->kind === 'templates';
$field = $templates ? 'templates' : 'file_extension';
$picked = $templates ? array_filter(array_map('trim', explode(';', (string) ($group[$field] ?? '')))) : GroupPolicy::parse((string) ($group[$field] ?? ''));
$values = http::values();
if ($this->action === 'templateMode') {
    if (!$templates) return;
    $mode = is_object($values) ? ($values->templateMode ?? '') : ($values['templateMode'] ?? '');
    if (!in_array($mode, ['all','selected'], true)) return;
    $picked = $mode === 'all' ? [] : ($picked ?: ['__none__']);
} else {
    $picked = array_values(array_diff($picked, [$choice, '__none__']));
    $checked = is_object($values) ? ($values->value ?? false) : ($values['value'] ?? false);
    if (in_array($checked, [1, '1', true, 'true', 'on'], true)) $picked[] = $choice;
    if ($templates && !$picked) $picked = ['__none__'];
}
$result = ProjectFile::setField($path, 'var:media_groups', (string) $this->state->group, $field, implode($templates ? ';' : ', ', $picked));
if ($result === false) message::error('That choice was not saved.');
else { $this->parent->updateView('main'); }
