<?php
$this->loadJS();
?>

<body class="<?= 'CMS_VERSIONDATE' ?> <?= implode(' ', $this->path) ?> <?= 'system' ?>">
	<main <?= DEBUG() ? ' style="border: 22px solid lightblue;border-bottom:none; height:calc(100% - 22px);"' : '' ?>>
		<div class="main cms">
			<header>
				<a class="cmsBrand<?= $this->childSelected ? '' : ' is-home' ?>" href="<?= http::$root ?>cms/" title="fluxo cms">
					<img class="brandMark" src="<?= http::$sysRoot ?>public/img/system/signet.svg" alt="fluxo">
					<img class="brandFull" src="<?= http::$sysRoot ?>public/img/system/logo-horizontal-dark.png" alt="fluxo">
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
					<a class="cmsHelp" target="_blank" rel="noopener" href="https://fluxo-help.1f2c.de" title="Help">
						<i class="fa-duotone fa-regular fa-circle-question"></i><span>Help</span>
					</a>
					<?php $this->withChild('user', 'signetmenu'); ?>
				</div>
			</nav>
		</div>
		<?php $this->withChildSelected('pylon'); ?>
		<div class="content">
			<header class="cms">
				<?php $this->withChildSelected('controls'); ?>
				<?php if (function_exists('appCmsPreviewUrl')): ?>
				<a class="button cmsPreviewBtn" target="_blank" rel="noopener noreferrer"
					href="<?= htmlspecialchars(appCmsPreviewUrl()) ?>" title="Vorschau in der App">
					<i class="fa-light fa-eye"></i><span>Vorschau</span>
				</a>
				<?php endif; ?>
			</header>
			<?php
			echo message::flush(['template']);
			echo '<div class=flexRow>';
			echo '	<div class=flexColumn>';

			$this->withChildSelected();

			$this->withChildSelected('footer');

			echo '</div>';
			$this->withChildSelected('sidebar');
			echo '</div>';
			?>
		</div>
	</main>
	<footer class="cmsStatusbar cms">
		<span class="cmsJobs" hidden></span>
		<?php $this->withChildSelected('status'); ?>
		<span class="cmsStage <?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) STAGE, ENT_QUOTES, 'UTF-8') ?></span>
	</footer>
	<?php
	$this->withSelected('lightbox');
	echo message::flush();
	?>
	<nav class="cmsPanes" aria-label="Sections">
		<button type="button" data-cms-pane="menu">Menu</button>
		<button type="button" data-cms-pane="tree">Tree</button>
		<button type="button" data-cms-pane="editor" aria-pressed="true">Edit</button>
		<button type="button" data-cms-pane="side">Side</button>
	</nav>
	<script>
		(function () {
			var root = document.body;
			var bar = document.querySelector('.cmsPanes');
			if (bar) {
				function button(name) {
					return bar.querySelector('[data-cms-pane="' + name + '"]');
				}
				if (!document.querySelector('main > div.pylon')) button('tree').hidden = true;
				if (!document.querySelector('main > div.content aside')) button('side').hidden = true;
				function setPane(name) {
					['menu', 'tree', 'editor', 'side'].forEach(function (pane) {
						root.classList.toggle('cmsPane-' + pane, pane === name);
					});
					bar.querySelectorAll('button').forEach(function (item) {
						item.setAttribute('aria-pressed', item.getAttribute('data-cms-pane') === name ? 'true' : 'false');
					});
				}
				bar.addEventListener('click', function (event) {
					var item = event.target.closest('button');
					if (!item || item.hidden) return;
					setPane(item.getAttribute('data-cms-pane'));
				});
				setPane('editor');
			}

			var storedDevice = null;
			try { storedDevice = localStorage.getItem('cmsDevice'); } catch (e) {}
			root.setAttribute('data-cms-device', storedDevice || 'desktop');
			var storedButton = document.querySelector('.deviceSwitch [data-cms-device="' + (storedDevice || 'desktop') + '"]');
			if (storedButton && storedButton.getAttribute('data-cms-width')) {
				root.style.setProperty('--cms-device-width', storedButton.getAttribute('data-cms-width') + 'px');
			}

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
				document.querySelectorAll('ul.tree span.label[contenteditable="true"]:not([data-ready])').forEach(function (label) {
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
				var field = event.target.closest('[contenteditable="true"][data-on_blur="updateLabel"]');
				if (!field) return;
				event.preventDefault();
				if (field.classList.contains('pageTitle')) field.setAttribute('contenteditable', 'false');
				field.blur();
			}, true);

			document.addEventListener('focusout', function (event) {
				var title = event.target.closest && event.target.closest('.pageTitle');
				if (title) title.setAttribute('contenteditable', 'false');
			}, true);

			document.addEventListener('click', function (event) {
				var titleEdit = event.target.closest('[data-page-title-edit]');
				if (titleEdit) {
					var title = titleEdit.parentNode.querySelector('.pageTitle');
					if (title) {
						title.setAttribute('contenteditable', 'true');
						title.focus();
					}
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
					var width = deviceButton.getAttribute('data-cms-width');
					root.setAttribute('data-cms-device', device);
					if (width) root.style.setProperty('--cms-device-width', width + 'px');
					else root.style.removeProperty('--cms-device-width');
					try { localStorage.setItem('cmsDevice', device); } catch (e) {}
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