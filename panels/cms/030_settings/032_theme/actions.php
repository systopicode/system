<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Theme\Panel $this */

use Systopic\System\Settings\ThemeAssets;
use Systopic\System\Settings\ThemeFile;

if (!\Systopic\System\Auth\Session::isSuperuser()) {
    \message::error('Only a superuser can change the theme.');
    return;
}

$slot = (string) (\http::get('slot') ?: \http::dataset('slot'));
$panel = $this;
$back = static function () use ($panel): void {
    if (\http::ajax()) {
        $panel->updateView();
        return;
    }
    \http::redirect($panel->href);
};

if ($this->action === 'upload') {
    $error = ThemeAssets::store($slot);
    $error === null ? \message::confirm('Saved.') : \message::error($error);
    $back();
    return;
}

if ($this->action === 'clear') {
    ThemeAssets::clear($slot)
        ? \message::confirm('The system image is used again.')
        : \message::error('There was no project image to remove.');
    $back();
    return;
}

if ($this->action === 'saveCss') {
    $posted = \http::posted('css');
    if (!ThemeFile::saveCss(is_string($posted) ? $posted : '')) {
        \message::error('That CSS was not saved.');
        return;
    }
    \message::confirm('Saved.');
    $this->updateView();
    return;
}

if ($this->action !== 'save') {
    return;
}
$values = \http::values();
$raw = is_object($values) ? (string) ($values->value ?? '') : (is_array($values) ? (string) ($values['value'] ?? '') : (string) $values);
if (!ThemeFile::save((string) \http::dataset('key'), $raw)) {
    \message::error('That colour was not saved.');
    return;
}
\message::confirm('Saved.');
$this->updateView();
