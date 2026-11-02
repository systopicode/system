<?php
/**
 * One panel in the configs: a row per key (build, the actions' default,
 * each action, each named right), a column per layer, then what it comes
 * to and where that is from. "In code" is what the panel's own
 * buildGranted() / actionGranted() ask — what decides as long as no config
 * does. Below, each layer's file as it is.
 *
 * The project's column (config/controlmap.json) is edited here: the pen
 * takes a key over with what it comes to now, then each role is switched
 * on and off, the cross gives it back to the layers below (actions.php).
 *
 * @var \Systopic\System\Panels\Root\Cms\Controlmap\Discover\Config\Panel $this
 */

use Systopic\System\Queries\UserRights\UserRights;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$panel = $this->subject;
$map = $this->map;
$known = UserRights::keyedRoles($this->scope);
$roles = static function (array $keys) use ($e, $known): string {
	$html = '';
	foreach ($keys as $key) {
		$html .= isset($known[$key])
			? "<span class='term'>{$e($key)}</span>"
			: "<span class='term is-unknown' title='No role has this key'>{$e($key)}</span>";
	}
	return $html === '' ? "<span class='term is-nobody' title='An empty list: nobody but a superuser'>nobody</span>" : $html;
};
// the project's cell: a pen to take the key over, or every role to switch, and a cross
$editable = static function (string $key, ?array $list) use ($e, $known): string {
	if ($list === NULL) {
		return "<span class='configSet' data-on_click='overrideConfig' data-key='{$e($key)}' title='Decide it for this installation, starting from what it comes to now'><i class='fa-regular fa-pen'></i></span>";
	}
	$html = '';
	foreach (array_values(array_unique([...array_map('strval', array_keys($known)), ...$list])) as $role) {
		$on = in_array($role, $list, TRUE);
		$html .= "<span class='term roleToggle" . ($on ? ' is-on' : '') . (isset($known[$role]) ? '' : ' is-unknown') . "'"
			. " data-on_click='toggleConfigRole' data-key='{$e($key)}' data-role='{$e($role)}'"
			. " title='" . ($on ? 'Take it out' : 'Let it in') . "'>{$e($role)}</span>";
	}
	return $html . "<span class='configUnset' data-on_click='unsetConfig' data-key='{$e($key)}' title='Inherit again: out of config/controlmap.json'><i class='fa-regular fa-xmark'></i></span>";
};
$focus = $this->focus;
?>
<div class="cms cmslightbox controlmapConfig" data-size="2">
	<section>
		<header>
			<h1><?= $e($panel === NULL ? 'Panel not found' : $panel->path . '/') ?></h1>
			<ul class="barmenu">
				<li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<?php if ($panel === NULL) { ?>
				<p class="tagsHint">There is no panel <?= $e($this->state->path ?? '') ?> (any more).</p>
			<?php } else {
				$layers = $map->layers($panel);
				$own = $map->own($panel);
				// the keys: build, the actions' default, every named right; then the
				// actions — the one clicked and those a layer names on top, the
				// others (the code's and the ones only a layer names) behind a switch
				$keys = ['build' => 'Build', 'actions' => 'Actions'];
				foreach ($map->rights($panel) as $right) {
					$keys['right.' . $right] = $right;
				}
				$others = [];
				$actionKeys = array_map(static fn($action) => 'action.' . $action->name, array_values($panel->actions));
				foreach (array_keys($own) as $key) {
					if (str_starts_with($key, 'action.') && !in_array($key, $actionKeys, TRUE)) {
						$actionKeys[] = $key;
					}
				}
				foreach ($actionKeys as $key) {
					if ($key === $focus || isset($own[$key])) {
						$keys[$key] = substr($key, 7);
					} else {
						$others[$key] = substr($key, 7);
					}
				}
				$inCode = [
					'build' => array_keys($panel->grants['build'] ?? []),
					'actions' => array_keys($panel->grants['action'] ?? []),
				];
				$row = function (string $key, string $label) use ($e, $map, $panel, $layers, $roles, $editable, $inCode, $focus): string {
					$grant = $map->effective($panel, $key);
					$isAction = str_starts_with($key, 'action.');
					$notInCode = $isAction && !isset($panel->actions[substr($key, 7)]);
					$icon = match (TRUE) {
						$key === 'build' => 'fa-eye',
						$key === 'actions' => 'fa-bolt',
						str_starts_with($key, 'right.') => 'fa-key',
						default => '',
					};
					$html = "<tr class='" . ($focus === $key ? 'is-focus' : '') . ($isAction ? ' is-action' : '') . "'><th>"
						. ($icon !== '' ? "<i class='fa-regular $icon'></i>" : '') . $e($label)
						. ($notInCode ? "<span class='none' title='A layer names it, the code does not'>not in code</span>" : '')
						. '</th><td>';
					foreach ($inCode[$key] ?? [] as $term) {
						$html .= "<span class='term'>{$e($term)}</span>";
					}
					$html .= '</td>';
					foreach ($layers as $layer) {
						$list = \Systopic\System\Controlmap\Map::flatten($layer['data'] ?? [])[$key] ?? NULL;
						$cell = $layer['layer'] === 'config' ? $editable($key, $list) : ($list === NULL ? '' : $roles($list));
						$html .= "<td class='" . ($focus === 'layer.' . $layer['layer'] ? 'is-focus' : '') . ($layer['layer'] === 'config' ? ' is-editable' : '') . "'>$cell</td>";
					}
					$html .= "<td class='effective'>";
					if ($grant === NULL) {
						$html .= "<span class='none'>the code decides</span>";
					} else {
						$html .= $roles($grant->roles);
						if ($grant->path !== $panel->path || $grant->key !== $key) {
							$html .= "<span class='from' title='{$e($grant->from)}'>from {$e(($grant->path === $panel->path ? '' : $grant->path . '/ ') . $grant->key)}</span>";
						}
					}
					return $html . '</td></tr>';
				};
				?>
				<p class="tagsHint">
					Who may see the panel and run its actions, as the configs say it — each layer replaces the one left of it, key by key.
					Where no config says anything, the code decides (<em>In code</em>). The column <em>config</em> is this installation's
					(config/controlmap.json) and is edited here; the packages' files are not. Nothing at runtime asks the configs yet.
				</p>
				<table class="discoverList configMap">
					<thead>
						<tr>
							<th></th>
							<th>In code</th>
							<?php foreach ($layers as $layer) { ?>
								<th class="<?= $focus === 'layer.' . $layer['layer'] ? 'is-focus' : '' ?>" title="<?= $e($layer['file']) ?>">
									<?= $e($layer['layer'] === 'config' ? 'config' : $layer['layer']) ?>
								</th>
							<?php } ?>
							<th>Effective</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($keys as $key => $label) {
							echo $row($key, $label);
						} ?>
					</tbody>
					<?php if ($others !== []) { ?>
						<tbody class="others">
							<?php foreach ($others as $key => $label) {
								echo $row($key, $label);
							} ?>
						</tbody>
						<tbody>
							<tr class="othersSwitch"><td colspan="<?= 3 + count($layers) ?>">
								<label><input type="checkbox" class="showOthers"> <?= count($others) ?> more actions, as the actions' default says</label>
							</td></tr>
						</tbody>
					<?php } ?>
				</table>

				<h2>Files</h2>
				<?php foreach ($layers as $layer) {
					$isConfig = $layer['layer'] === 'config'; ?>
					<div class="configFile<?= $focus === 'layer.' . $layer['layer'] ? ' is-focus' : '' ?>">
						<h3><?= $e($layer['layer']) ?> <span><?= $e(\fs::toInternal($layer['file'])) ?><?= $isConfig ? ' → "' . $e($panel->path) . '"' : '' ?></span></h3>
						<?php if ($layer['error'] !== NULL) { ?>
							<p class="error"><?= $e($layer['error']) ?></p>
						<?php } elseif (!$layer['exists']) { ?>
							<p class="none"><?= $isConfig ? 'No entry for this panel.' : 'No file.' ?></p>
						<?php } else { ?>
							<pre><?= $e($isConfig
								? json_encode($layer['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
								: file_get_contents(\fs::toNative($layer['file']))) ?></pre>
						<?php } ?>
					</div>
				<?php } ?>
			<?php } ?>
		</main>
	</section>
</div>
