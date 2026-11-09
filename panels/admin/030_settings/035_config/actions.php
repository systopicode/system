<?php

/** @var \Systopic\System\Panels\Root\Admin\Settings\Config\Panel $this */

use Systopic\System\Settings\EnvironmentForm;

// open, close, pick: only what is shown changes
if (EnvironmentForm::viewState($this)) return;
EnvironmentForm::handle($this);
