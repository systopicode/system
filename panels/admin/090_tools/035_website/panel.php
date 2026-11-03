<?php
namespace Systopic\System\Panels\Root\Admin\Tools\Website;
require_once dirname(__DIR__) . '/MenuPanel.php';
class Panel extends \Systopic\System\Panels\Root\Admin\Tools\MenuPanel {
    public string $label = 'Website checks';
    public function onLoad() {
        // Page-content checks are supplied by the CMS package, not standalone System.
        if ($this->firstChild === null) { $this->inNavi = false; return; }
        parent::onLoad();
    }
}
