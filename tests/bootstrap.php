<?php

declare(strict_types=1);

// Standalone checks against sibling packages; no project/production bootstrap.
spl_autoload_register(static function (string $class): void {
    foreach (['Systopic\\System\\' => dirname(__DIR__) . '/src/',
        'Systopic\\Db\\' => dirname(__DIR__, 2) . '/db/src/',
        'Systopic\\Cms\\' => dirname(__DIR__, 2) . '/cms/src/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    }
});
\Systopic\Db\Schema\Registry::addTables(dirname(__DIR__) . '/src/Tables', 'Systopic\\System\\Tables');
\Systopic\Db\Schema\Registry::addTables(dirname(__DIR__, 2) . '/cms/src/Tables', 'Systopic\\Cms\\Tables');

function check(string $label, bool $ok): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    echo "ok: $label\n";
}
function rejects(string $label, callable $call): void {
    try { $call(); } catch (Throwable) { check($label, true); return; }
    check($label, false);
}
