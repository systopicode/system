<?php

/** @var \Systopic\System\Panels\Root\Admin\Settings\Config\Panel $this */

use Systopic\System\Settings\EnvironmentForm;

// Reuse the existing bounded SVG upload/reset workflow and its role/CSRF checks.
if (\Systopic\System\Settings\LanguageFlags::handleAction($this)) return;

// open, close, pick: only what is shown changes
if (EnvironmentForm::viewState($this)) return;
EnvironmentForm::handle($this);
