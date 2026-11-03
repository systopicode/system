<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Pagesettings\Panel $this */

use Systopic\System\Settings\EnvironmentFile;
use Systopic\System\Settings\ProjectForm;

if ((string) $this->action === 'saveAuthMethod') {
	$values = \http::values();
	$text = is_object($values) ? (string) ($values->value ?? '') : (is_array($values) ? (string) ($values['value'] ?? '') : (string) $values);
	if (!EnvironmentFile::set((string) \http::dataset('file'), 'auth', 'default_login_method', $text)) {
		\message::error('That change was not saved.');
		$this->updateView('main');
	}
	return;
}

if (in_array((string) $this->action, ['uploadFlag', 'resetFlag'], true)) {
    $token = (string) (\http::posted('flagToken') ?? \http::put('flagToken') ?? '');
    $code = (string) \http::get('code');
    if (!\Systopic\System\Auth\Session::hasRole('cmsAdmin') || strtoupper((string) \http::$method) !== 'POST' || !\Systopic\System\Settings\LanguageFlags::checkToken($token)) {
        \message::error('Reload the settings page and try again.');
    } elseif ($this->action === 'uploadFlag') {
        $error = \Systopic\System\Settings\LanguageFlags::store($code);
        $error === null ? \message::confirm('Flag saved.') : \message::error($error);
    } else {
        \Systopic\System\Settings\LanguageFlags::reset($code)
            ? \message::confirm('Project flag removed. The local default is used where available.')
            : \message::error('There was no project flag to remove.');
    }
    if (\http::ajax()) $this->updateView('main');
    else \http::redirect($this->getHref(\http::$root));
    return;
}

ProjectForm::handle($this, 'page');
