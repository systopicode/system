<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

/**
 * Who may, as the map says it for one panel and one key (`build`,
 * `actions`, `action.publish`, `right.move`) — and where it says so: the
 * panel it is inherited from, the layer (`system`, `cms`, `project`: a
 * panel.config.json; `config`: config/controlmap.json) and the key there.
 */
final class Grant
{
    /** @param list<string> $roles role keys */
    public function __construct(
        public readonly array $roles,
        public readonly string $path,
        public readonly string $layer,
        public readonly string $key,
    ) {}

    /** Where it comes from, for a title: `admin/pages · cms · actions`. */
    public string $from {
        get => ($this->path === '' ? '/' : $this->path) . ' · ' . $this->layer . ' · ' . $this->key;
    }
}
