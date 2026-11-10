<?php

/**
 * Header actions in .rightAlign → system fitHeader moves them into … on phone.
 *
 * @var \Systopic\System\Panels\Root\Admin\Settings\Config\Panel $this
 */

use Systopic\System\Settings\EnvironmentForm;

if ($this->withChildSelected('controls')) return;

EnvironmentForm::controls($this);
