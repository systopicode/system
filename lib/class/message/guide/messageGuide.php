<?php

#[AllowDynamicProperties]
class messageGuide extends message {

	public function emptyPageTree() {
		return $this
				->type('info')
				->message('no pages have been created yet. you may change this by hitting: ')
				->action('new page', 'cms/pages/new')
		;
	}

	public function noTemplates() {
		return $this
				->type('info')
				->message('No Templates found in your project. you may create a default template')
				->action('Create Template', 'cms/pages/createDefaultTemplate')
				->help('read about rutancms Templates', '/templates')
		;
	}

}
