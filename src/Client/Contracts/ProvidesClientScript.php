<?php

declare(strict_types=1);

namespace Systopic\System\Client\Contracts;

/**
 * Marker interface: the implementing PHP class has a corresponding JavaScript
 * file. Does not specify *when* the file is loaded — use PreloadsClientScript
 * to request eager loading with the page.
 *
 * Replaces the legacy `clientClass` interface.
 */
interface ProvidesClientScript
{
}
