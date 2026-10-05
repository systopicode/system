<?php

declare(strict_types=1);

namespace Systopic\System\Client\Contracts;

/**
 * Marker interface: the implementing PHP class has a corresponding JavaScript
 * file that must be loaded *eagerly* with the page (synchronous, before
 * DOMContentLoaded). PHP adds the file to $jsAppFiles via
 * client::loadJSfromFileInfo() as soon as the class is autoloaded.
 *
 * Without this interface the JS file is loaded lazily on first use via the
 * async JS mechanism: sys.getClass(className, callback).
 *
 * Replaces the legacy `clientPreload` interface. Extends ProvidesClientScript
 * to signal that the class also has a JS counterpart.
 *
 * No methods are required. The presence of the interface is the signal.
 */
interface PreloadsClientScript extends ProvidesClientScript
{
}
