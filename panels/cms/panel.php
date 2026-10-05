<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\LastLogin\LastLogin;

class Panel extends PanelNode {

	/** The request's scope — every CMS panel reads and writes through it. */
	public Scope $scope {
		get => Scope::default();
	}

	public $loadChildModules = TRUE;
	public $showSubnavi = TRUE;

	function buildGranted() {
		if ($this->path === ['cms']) {
			if (!\Systopic\System\Auth\Session::known()) {
				// Only redirect when the user is actually requesting a CMS path.
				// buildGranted() fires for ALL panels, so a request to e.g. /auth/
				// would otherwise trigger this redirect for every cms sibling panel.
				$reqPath = \app::request()->modulePath;
				if ($reqPath === ['cms', 'auth']) {
					// The login form lives below this panel: denying cms here
					// would prune the branch the anonymous user is being sent
					// to, and /cms/auth/ would render an empty document.
					return TRUE;
				}
				if (!empty($reqPath) && $reqPath[0] === 'cms') {
					\http::redirect(\http::$root . 'cms/auth/');
				}
				return FALSE;
			} else {
				return TRUE;
			}
		}

		return \Systopic\System\Auth\Session::hasRole('editor') ||
				STAGE === 'x_DEV' ||
				\app::request()->modulePath === ['cms', 'auth']
		;
	}

	function onLoad() {
		\fs::load('fsUploader');
		if ($this->path === ['cms']) { // update navi if change
			if ($this->state->submoduleName !== ($this->childSelected->name ?? FALSE)) {
				$this->updateView('menu');
			}
			$this->state->submoduleName = $this->childSelected->name ?? FALSE;

			$this->checkReleaseNoteRedirect();
		}
	}

	/** Whoever has not logged in since the last release note is sent to read it. */
	private function checkReleaseNoteRedirect() {
		if (!defined('RELEASE_NOTE_DATE') || !\Systopic\System\Auth\Session::known() || !\Systopic\System\Auth\Session::id()) {
			return;
		}
		$lastLogin = LastLogin::of($this->scope, (int) \Systopic\System\Auth\Session::id());
		if (($lastLogin?->getTimestamp() ?? 0) < strtotime(RELEASE_NOTE_DATE)) {
			if (strpos($_SERVER['REQUEST_URI'] ?? '', 'cms/about/releases') === false) {
				\http::redirect('cms/about/releases#cms/about/releases');
			}
		}
	}

	function actionGranted($action = NULL) {
		return parent::actionGranted($action) || STAGE === 'DEV';
	}

	function getDefaultState() {
		if ($this->name === 'cms' || $this->parent->name === 'cms') {
			return (object) [
						'childSelected' => FALSE,
						'pylonWidth' => 180,
						'submoduleName' => FALSE,
			];
		}
		return parent::getDefaultState();
	}

	function sendUserEmail($template, $subject, $user) {
		$path = $template;
		if (!preg_match('~^(/|[A-Za-z]:)~', str_replace('\\', '/', $template))) {
			$path = rtrim($this->absPath, '/') . '/' . $template;
		}
		ob_start();
		include \fs::toNative($path);
		$body = ob_get_clean();

		return (new \emailSmtp())
						->To($user->email, $user->fullname)
						->Subject($subject)
						->Body($body)
						->send()
		;
	}
}
