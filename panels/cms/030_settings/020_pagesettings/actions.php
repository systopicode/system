<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Pagesettings\Panel $this */

use Systopic\System\Settings\EnvironmentFile;
use Systopic\System\Settings\ProjectForm;

if ((string) $this->action === 'saveAuthMethod') {
	$values = \http::values();
	$text = is_object($values) ? (string) ($values->value ?? '') : (is_array($values) ? (string) ($values['value'] ?? '') : (string) $values);
	if (EnvironmentFile::set((string) \http::dataset('file'), 'auth', 'default_login_method', $text)) {
		\message::confirm('Saved.');
	} else {
		\message::error('That change was not saved.');
		$this->updateView('main');
	}
	return;
}

ProjectForm::handle($this, 'page');
