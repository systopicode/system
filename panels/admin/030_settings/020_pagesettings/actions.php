<?php

/** @var \Systopic\System\Panels\Root\Admin\Settings\Pagesettings\Panel $this */

use Systopic\System\Settings\EnvironmentFile;
use Systopic\System\Settings\ProjectForm;

if ((string) $this->action === 'saveAuthMethod') {
	$values = \http::values();
	$text = is_object($values) ? (string) ($values->value ?? '') : (is_array($values) ? (string) ($values['value'] ?? '') : (string) $values);
	if (!EnvironmentFile::set('system', 'auth.defaultSignInMethod', $text)) {
		\message::error('That change was not saved.');
		$this->updateView('main');
	}
	return;
}

// the languages show here while no other package shows them
if (\Systopic\System\Settings\LanguageFlags::handleAction($this)) {
    return;
}

ProjectForm::handle($this, 'page');
