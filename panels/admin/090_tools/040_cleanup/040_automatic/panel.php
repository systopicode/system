<?php

declare(strict_types=1);
namespace Systopic\System\Panels\Root\Admin\Tools\Cleanup\Automatic;

class Panel extends \Systopic\System\Panels\PanelNode
{
    public ?array $cleanupPreview = null;

    public ?string $webcronKey = null;

    public function __construct($name, $parent = null) { parent::__construct($name, $parent); $this->label = 'Cleanup'; }
    public function buildGranted() { return \Systopic\System\Auth\Session::isSuperuser(); }
}
