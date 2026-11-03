<?php

/**
 * cms/controlmap/discover/config — the project's layer of one panel
 * (config/controlmap.json), key by key. The packages' panel.config.json
 * stay as they are.
 *
 * @var \Systopic\System\Panels\Root\Cms\Controlmap\Discover\Config\Panel $this
 */

$panel = $this->subject;
$key = (string) http::dataset('key');

if ($panel !== NULL && $key !== '') {
	$map = $this->map;
	$set = $map->projectSays($panel)[$key] ?? NULL;
	try {
		switch ($this->action) {

			case 'overrideConfig':     // decide it here, starting from what it comes to now —
			                           // or, where the code decides, the roles the code asks for
				$start = $map->effective($panel, $key)?->roles;
				if ($start === NULL) {
					$known = Systopic\System\Queries\UserRights\UserRights::keyedRoles($this->scope);
					$asked = $key === 'build' ? ($panel->grants['build'] ?? []) : (str_starts_with($key, 'right.') ? [] : ($panel->grants['action'] ?? []));
					$start = array_values(array_filter(array_map('strval', array_keys($asked)), static fn(string $term) => isset($known[$term])));
				}
				$map->write($panel->path, $key, $start);
				break;

			case 'toggleConfigRole':   // a role in or out of the project's list
				$role = (string) http::dataset('role');
				$roles = $set ?? [];
				$roles = in_array($role, $roles, TRUE) ? array_values(array_diff($roles, [$role])) : [...$roles, $role];
				$map->write($panel->path, $key, $roles);
				break;

			case 'unsetConfig':        // inherit again
				$map->write($panel->path, $key, NULL);
				break;
		}
	} catch (\RuntimeException $e) {
		message::error($e->getMessage());
	}
	$this->updateView('lightbox');
}
