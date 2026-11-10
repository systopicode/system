<?php

/** @var \Systopic\System\Panels\Root\Admin\Settings\Pagesettings\Panel $this */

use Systopic\System\Settings\ProjectForm;

// the languages show here while no other package shows them
if (\Systopic\System\Settings\LanguageFlags::handleAction($this)) {
    return;
}

ProjectForm::handle($this, 'page');
