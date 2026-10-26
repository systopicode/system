<?php
$this->loadJS();
// Search has no page of its own. Keep the open module behind it. The footer
// texts stay over the dashboard: another module there looks like a wrong page,
// and closing it builds the dashboard again at the top.
$selected = $this->childSelected;
$behind = '';
if ($selected !== null && !$selected->inNavi && $selected->name === 'search') {
	$name = (string) ($this->state->submoduleName ?? '');
	$keep = $name !== '' ? $this->child($name) : null;
	if ($keep !== null && $keep->inNavi) {
		$behind = $name;
	}
}
$surface = function (string $view, ?callable $fn = null) use ($behind) {
	if ($behind !== '') {
		return $this->withChild($behind, $view, $fn);
	}
	return $fn === null ? $this->withChildSelected($view) : $this->withChildSelected($view, $fn);
};
$module = $behind !== '' ? $this->child($behind) : $this->childSelected;
// Search stays over the open module. Do not render that module's page
// preview here: it runs the public templates and can fail under this URL.
$home = $behind !== '' ? false : !$surface('main', static fn() => null);
// A sheet only where a list stays behind a leaf. A branch (Logs) only
// unfolds its submenu. Dashboard, backup and the profile are the page.
$deep = $module;
if ($deep !== null) {
	while ($deep->childSelected !== null && $deep->childSelected->inNavi) {
		$deep = $deep->childSelected;
	}
}
$deepHasChildren = false;
if ($deep !== null && $deep !== $module) {
	for ($child = $deep->firstChild; $child !== null; $child = $child->next) {
		if ($child->inNavi) {
			$deepHasChildren = true;
			break;
		}
	}
}
$branchOnly = (string) (\http::get('branch') ?? '') === '1';
$sheet = !$branchOnly
	&& $module !== null && $module->inNavi
	&& !in_array($module->name, ['backup', 'user'], true)
	&& (
		($module->name === 'pages' && (int) ($module->state->id ?? 0) > 1)
		|| ($module->childSelected !== null && $deep !== $module && !$deepHasChildren)
	);
?>

<?php
// Search is only the dialog. The shell keeps the module that was open,
// so the pylon and the page do not switch to another one.
$shellPath = $this->path;
if ($behind !== '') {
	$remembered = $this->state->submodulePath ?? [];
	if (is_array($remembered) && $remembered !== []) {
		$shellPath = $remembered;
	}
}
?>
<body class="<?= 'CMS_VERSIONDATE' ?> <?= implode(' ', $shellPath) ?> <?= 'system' ?>"<?= $sheet ? ' data-cms-window="edit"' : '' ?>>
	<header class="cmsMobileHeader cms">
		<button type="button" data-cms-window="menu" aria-label="Open navigation" aria-expanded="false"><i class="fa-regular fa-bars"></i></button>
		<a class="cmsMobileDashboardBrand" href="<?= http::$root ?>cms/" aria-label="Dashboard"><img src="<?= \Systopic\System\Sys\Packages::panelAssetHref('img/logo-horizontal-dark.png') ?>" alt="fluxo"></a>
		<strong data-cms-module><?= htmlspecialchars((string) ($module?->label ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
		<span class="cmsStage <?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?></span>
		<a class="ajax cmsSearchLink" href="<?= http::$root ?>cms/search/" aria-label="Search"><i class="fa-regular fa-magnifying-glass"></i></a>
		<button type="button" class="cmsMobileMenuClose" data-cms-window="menu" aria-label="Close navigation"><i class="fa-regular fa-xmark"></i></button>
	</header>
	<main<?= DEBUG() ? ' data-debug' : '' ?>>
		<div class="main cms">
			<header>
				<a class="cmsBrand" href="<?= http::$root ?>cms/" title="fluxo cms">
					<img class="brandMark" src="<?= \Systopic\System\Sys\Packages::panelAssetHref('img/signet.svg') ?>" alt="fluxo">
					<img class="brandFull" src="<?= \Systopic\System\Sys\Packages::panelAssetHref('img/logo-horizontal-dark.png') ?>" alt="fluxo">
				</a>
			</header>
			<nav>
				<ul>
					<?php $this->withView('menu'); ?>
				</ul>
				<div class="bottomMenu">
					<?php
					if (isset($modules['bottomMenu'])) {
						foreach ($modules['bottomMenu']['submodules'] as $key => $values) {
					?>
							<div class="<?= $key ?>">
								<?php if (isset($values['href'])) { ?>
									<a title="<?= $values['label'] ?>" href="<?= $values['href'] ?>" class="<?= $values['ajax'] ?> progressBar"><img src="<?= http::$root ?>panels/cms/bottomMenu/<?= $key ?>/icon.svg"></a>
								<?php
								} else {
									fs::includeFile(fs::$root . 'panels/cms/bottomMenu/' . $key . '/menu.tül.php');
								}
								?>
							</div>
					<?php
						}
					}
					?>
					<?php $hotkey = \Systopic\System\Sys\Search::hotkey(); ?>
					<a class="cmsHelp cmsSearchLink ajax" href="<?= http::$root ?>cms/search/" title="Search (<?= $hotkey ?>)">
						<i class="fa-duotone fa-regular fa-magnifying-glass"></i><span>Search</span><kbd class="cmsKeys"><?= $hotkey ?></kbd>
					</a>
					<a class="cmsHelp" target="_blank" rel="noopener" href="https://fluxo-help.1f2c.de" title="Help">
						<i class="fa-duotone fa-regular fa-circle-question"></i><span>Help</span>
					</a>
					<?php $this->withChild('user', 'signetmenu'); ?>
					<span class="cmsMobileOnly cmsStage <?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?></span>
				</div>
			</nav>
		</div>
		<?php $surface('pylon'); ?>
		<div class="content"<?= $sheet ? ' data-cms-sheet="1"' : '' ?>>
			<header class="cms">
				<?php $surface('controls'); ?>
				<?php $this->withView('preview'); ?>
			</header>
			<?php
			echo message::flush(['template']);
			echo '<div class=flexRow>';
			echo '	<div class=flexColumn>';

			if ($home) {
				$this->withView('home');
			} elseif ($behind !== '') {
				// Pages stays out: its preview runs the public templates.
				// Every other module is opened for real. A bare include runs
				// the views in the wrong folder and the page stays empty.
				if ($behind !== 'pages') {
					$path = is_array($this->state->submodulePath ?? null) ? array_values($this->state->submodulePath) : [];
					if ($path !== [] && ($path[0] ?? '') === 'cms') {
						array_shift($path);
					}
					if ($path === [] || $path[0] !== $behind) {
						$path = [$behind];
					}
					$draw = function ($panel, array $rest) use (&$draw) {
						if ($rest === []) {
							$panel->withView('main');
							return;
						}
						$name = array_shift($rest);
						$panel->withChild($name, 'main', function ($child) use ($rest, $draw) {
							$draw($child, $rest);
						});
					};
					$draw($this, $path);
				}
			} else {
				$surface('main');
			}

			if ($behind === '') {
				$surface('footer');
			}

			echo '</div>';
			if ($behind === '') {
				$surface('sidebar');
			}
			echo '</div>';
			?>
		</div>
	</main>
	<footer class="cmsStatusbar cms">
		<span class="cmsJobs" hidden></span>
		<?php if ($behind === '') { $surface('status'); } ?>
		<span class="cmsStage <?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?></span>
	</footer>
	<?php
	$this->withSelected('lightbox');
	echo message::flush();
	?>
	<button type="button" class="cmsMobileBackdrop" data-cms-close aria-label="Close window" tabindex="-1"></button>
	<script>
		(function () {
			var root = document.body;
			var mobile = matchMedia('(max-width: 1024px)');
			<?php include __DIR__ . '/mobile.js'; ?>

			var storedDevice = null;
			try { storedDevice = localStorage.getItem(mobile.matches ? 'cmsDeviceMobile' : 'cmsDevice'); } catch (e) {}
			root.setAttribute('data-cms-device', storedDevice || (mobile.matches ? 'fit' : 'desktop'));

			var tabScrollLeft = null;
			var closedSections = [];
			try { closedSections = JSON.parse(localStorage.getItem('cmsSidebarClosed') || '[]'); } catch (e) {}
			if (!Array.isArray(closedSections)) closedSections = [];

			function syncTabs(nav) {
				var list = nav.querySelector('ul');
				if (!list) return;
				var prev = nav.querySelector('.tabScroll.prev');
				var next = nav.querySelector('.tabScroll.next');
				if (!prev || !next) return;
				var maxScroll = list.scrollWidth - list.clientWidth;
				prev.hidden = list.scrollLeft <= 1;
				next.hidden = list.scrollLeft >= maxScroll - 1;
			}

			function initTabs() {
				// a width the site does not define (another project, a changed css) shows desktop
				if (document.querySelector('.cmsPreview')) {
					var wanted = null;
					try { wanted = localStorage.getItem(mobile.matches ? 'cmsDeviceMobile' : 'cmsDevice'); } catch (e) {}
					var devices = document.querySelector('.deviceSwitch');
					var device = wanted && devices && devices.querySelector('[data-cms-device="' + wanted + '"]') ? wanted : (mobile.matches && devices?.querySelector('[data-cms-device="fit"]') ? 'fit' : 'desktop');
					// called for every mutation (the observer below): a write each time restyles the page
					root.getAttribute('data-cms-device') !== device && root.setAttribute('data-cms-device', device);
				}
				document.querySelectorAll('aside.cms nav.sidebarTabs:not([data-ready])').forEach(function (nav) {
					nav.setAttribute('data-ready', '');
					var list = nav.querySelector('ul');
					if (!list) return;
					if (tabScrollLeft !== null) list.scrollLeft = tabScrollLeft;
					var selected = list.querySelector('a.selected');
					if (selected) {
						var left = selected.offsetLeft - list.offsetLeft;
						if (left < list.scrollLeft || left + selected.offsetWidth > list.scrollLeft + list.clientWidth) {
							list.scrollLeft = left - (list.clientWidth - selected.offsetWidth) / 2;
						}
					}
					syncTabs(nav);
					list.addEventListener('scroll', function () {
						tabScrollLeft = list.scrollLeft;
						syncTabs(nav);
					}, {passive: true});
					if (window.ResizeObserver) new ResizeObserver(function () { syncTabs(nav); }).observe(list);
				});
				document.querySelectorAll('aside.cms details.sidebarSection:not([data-ready])').forEach(function (section) {
					section.setAttribute('data-ready', '');
					section.open = closedSections.indexOf(section.getAttribute('data-section')) === -1;
				});
				document.querySelectorAll('ul.tree span.label[contenteditable="plaintext-only"]:not([data-ready])').forEach(function (label) {
					label.setAttribute('data-ready', '');
					label.focus();
					var range = document.createRange();
					range.selectNodeContents(label);
					var selection = window.getSelection();
					selection.removeAllRanges();
					selection.addRange(range);
				});
			}

			document.addEventListener('toggle', function (event) {
				var section = event.target;
				if (!section.matches || !section.matches('aside.cms details.sidebarSection[data-ready]')) return;
				var name = section.getAttribute('data-section');
				var index = closedSections.indexOf(name);
				if (section.open) {
					if (index !== -1) closedSections.splice(index, 1);
				} else if (index === -1) {
					closedSections.push(name);
				}
				try { localStorage.setItem('cmsSidebarClosed', JSON.stringify(closedSections)); } catch (e) {}
			}, true);

			document.addEventListener('keydown', function (event) {
				if (event.key !== 'Enter' || event.shiftKey) return;
				var field = event.target.closest('[contenteditable="plaintext-only"][data-on_blur="updateLabel"]');
				if (!field) return;
				event.preventDefault();
				if (field.classList.contains('pageTitle')) field.setAttribute('contenteditable', 'false');
				field.blur();
			}, true);

			document.addEventListener('focusout', function (event) {
				var title = event.target.closest && event.target.closest('.pageTitle');
				if (title) title.setAttribute('contenteditable', 'false');
				var back = event.relatedTarget && event.relatedTarget.closest && event.relatedTarget.closest('[data-page-title-edit]');
				if (back) back.blur();
			}, true);

			document.addEventListener('click', function (event) {
				var titleEdit = event.target.closest('[data-page-title-edit]');
				if (titleEdit) {
					var title = titleEdit.parentNode.querySelector('.pageTitle');
					if (title) {
						title.setAttribute('contenteditable', 'plaintext-only');
						title.focus();
					}
					// The pencil stays focused after the title is done, and
					// keeps its ring. Leave it only when the title has the caret.
					requestAnimationFrame(function () {
						if (document.activeElement === title) titleEdit.blur();
					});
					return;
				}
				var scrollButton = event.target.closest('[data-tab-scroll]');
				if (scrollButton) {
					var list = scrollButton.parentNode.querySelector('ul');
					if (list) list.scrollBy({left: list.clientWidth * 0.6 * Number(scrollButton.getAttribute('data-tab-scroll')), behavior: 'smooth'});
					return;
				}
				var deviceButton = event.target.closest('[data-cms-device]');
				if (deviceButton && deviceButton !== root) {
					var device = deviceButton.getAttribute('data-cms-device');
					root.setAttribute('data-cms-device', device);
					try { localStorage.setItem(mobile.matches ? 'cmsDeviceMobile' : 'cmsDevice', device); } catch (e) {}
				}
			});

			var host = document.querySelector('main');
			if (host && window.MutationObserver) {
				new MutationObserver(initTabs).observe(host, {childList: true, subtree: true});
			}
			initTabs();
		}());
	</script>
</body>