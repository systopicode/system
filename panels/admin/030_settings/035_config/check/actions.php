<?php

/**
 * The buttons of the list: the page does them, then the list is drawn again.
 *
 * @var \Systopic\System\Panels\Root\Admin\Settings\Config\Check\Panel $this
 */

use Systopic\System\Settings\EnvironmentForm;

if (in_array($this->action, ['prune', 'dropOrphans'], true)) {
    EnvironmentForm::handle($this->parent, $this->action);
    $this->updateView('lightbox');
}
