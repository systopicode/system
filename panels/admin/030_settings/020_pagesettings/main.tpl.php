<?php

/** @var \Systopic\System\Panels\Root\Admin\Settings\Pagesettings\Panel $this */

use Systopic\System\Settings\ProjectForm;

// what a panel of another package shows (Website of the cms) is not shown here
ProjectForm::render('page', [], $this->claimed());
