<?php

/** @var \Systopic\System\Panels\Root\Admin\Settings\Environment\Check\Panel $this */

use Systopic\System\Settings\EnvironmentForm;

EnvironmentForm::checkList((string) $this->state->file);
