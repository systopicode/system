<?php
/** Explicitly scoped deployment cleanup. Dry run by default; never traverses Git history. */
declare(strict_types=1);
$root = realpath($argv[1] ?? '');
if ($root === false || !is_dir($root) || $root === DIRECTORY_SEPARATOR) throw new RuntimeException('Pass the installation asset directory, e.g. public/assets/sys/public.');
$apply = in_array('--apply', $argv, true);
foreach (['css/11_fontawesome.min.css','fonts/fontawesome/fa-regular-400.woff2','fonts/fontawesome/fa-duotone-regular-400.woff2'] as $relative) {
    $path = $root . '/' . $relative;
    if (!file_exists($path)) continue;
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $root . '/') || is_link($path)) throw new RuntimeException('Refusing a symlink or a path outside the selected asset directory.');
    echo ($apply ? 'Remove ' : 'Would remove ') . $relative . PHP_EOL;
    if ($apply && !unlink($path)) throw new RuntimeException('Could not remove ' . $relative);
}
