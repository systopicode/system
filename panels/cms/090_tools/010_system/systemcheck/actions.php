<?php

/** @var \Systopic\System\Panels\PanelNode $this */

if ($this->action === 'probeMediaTools') {
    if (!\Systopic\System\Auth\Session::isSuperuser() || !in_array(strtoupper((string) \http::$method), ['POST', 'PUT'], true)) {
        \message::error('Only a superuser can start media-tool tests using the controls.');
        return;
    }
    foreach (\Systopic\System\Runtime\Capabilities::TOOLS as $name => $purpose) {
        $this->binaryProbes[$name] = \Systopic\System\Runtime\Capabilities::probe($name);
    }
    $this->updateView('main');
}
