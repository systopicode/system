<?php

declare(strict_types=1);

namespace Systopic\System\Client;

use Systopic\System\Client\Contracts\ClientExportable;
use Systopic\System\Client\Contracts\ProvidesClientScript;
use Systopic\System\Client\Contracts\StaticClientExportable;

/**
 * Collects and flushes all PHP→JS client data exports for a single request.
 *
 * Replaces the legacy static `clientInterfaces` class with an injectable
 * service. One instance is created per request in bootstrap/loader and
 * passed to whatever renders the page (templates, app.php, etc.).
 *
 * Two export channels:
 *
 *  - Instance exports (ClientExportable): collected by calling
 *    register($object) for each panel/singleton that should appear in the
 *    JS sys.* tree. Used by PanelTree to register panel nodes.
 *
 *  - Static exports (StaticClientExportable): collected by calling
 *    registerClass($className) when a class is autoloaded. Each class
 *    contributes its own exportStaticClientData() result.
 *
 * Both are flushed together via collectAll(), which returns a flat array
 * of ClientExport objects ready for json_encode and handoff to
 * sys.lib.updateClientData().
 */
class ClientRegistry
{
    /** @var ClientExportable[] */
    private array $instanceExporters = [];

    /** @var string[] Class names implementing StaticClientExportable */
    private array $staticExporterClasses = [];

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Register an object whose exportClientData() will be called on flush.
     * Typically called by PanelTree for each panel node.
     */
    public function register(ClientExportable $object): void
    {
        $this->instanceExporters[] = $object;
    }

    /**
     * Register a class name for static export. Called from the autoloader
     * whenever a class implementing StaticClientExportable is loaded.
     * Also triggers eager JS loading when the class implements
     * ProvidesClientScript.
     */
    public function registerClass(string $className): void
    {
        if (is_a($className, StaticClientExportable::class, true)) {
            $this->staticExporterClasses[] = $className;
        }
        if (is_a($className, ProvidesClientScript::class, true)) {
            $this->loadClientScript($className);
        }
    }

    // =========================================================================
    // Export
    // =========================================================================

    /**
     * Collect all exports from both channels and return a flat array of
     * ClientExport DTOs. The array is directly json_encode-able.
     *
     * @return ClientExport[]
     */
    public function collectAll(): array
    {
        $exports = [];

        foreach ($this->instanceExporters as $exporter) {
            $exports[] = $exporter->exportClientData();
        }

        foreach ($this->staticExporterClasses as $class) {
            $result = $class::exportStaticClientData();
            if (is_array($result)) {
                array_push($exports, ...$result);
            } else {
                $exports[] = $result;
            }
        }

        return $exports;
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Triggers eager loading of the JS file associated with $className.
     * Delegates to the legacy client::loadJSfromFileInfo() via the
     * autoloader's file-info mechanism until that layer is modernised.
     */
    private function loadClientScript(string $className): void
    {
        // Retrieve the file-info object that the legacy fsAutoloader
        // produces (contains reflectionClass, path, root, etc.) and hand
        // it to client::loadJSfromFileInfo(). This keeps JS-loading working
        // without duplicating the path-resolution logic here.
        $fileInfo = \fsAutoloader::getFileInfoByClassname($className);
        if ($fileInfo !== false) {
            \client::loadJSfromFileInfo($fileInfo);
        }
    }
}
