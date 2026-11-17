<?php
declare(strict_types=1);
// Phase 2 of media-crop-ratios.plan.md: ratios and sizes as lists in media.settings.json, edited through the settings GUI's writer.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\Variants;
use Systopic\System\Settings\ProjectFile;
use Systopic\System\Settings\ProjectJson;
require_once dirname(__DIR__) . '/src/Settings/ProjectFile.php'; // Group / Item live in this file

$dir = sys_get_temp_dir() . '/fluxo-media-variants-' . bin2hex(random_bytes(5));
mkdir($dir);
$path = $dir . '/media.settings.json';
$read = static fn(): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$group = static function (string $id) use ($path): ?object {
    foreach (ProjectJson::groups($path) as $group) {
        if ($group->id === $id) return $group;
    }
    return null;
};
$texts = static fn(?object $group): array => $group === null ? [] : array_map(static fn($item): string => $item->text, $group->items);
try {
    ProjectJson::put($path, ['formats' => ['sample' => ['megapixels' => 2]], 'custom' => ['keep' => true]]);
    check('a file that names no ratios shows the ones in force', $texts($group('media:ratios')) === Variants::RATIOS);
    check('a file that names no sizes shows the ones in force', $texts($group('media:sizes')) === ['0.5', '1', '2', '5', '10']);
    check('both are lists, not records', $group('media:ratios')->shape === 'list' && $group('media:sizes')->shape === 'list');
    check('showing them does not write the file', !isset($read()['ratios']) && !isset($read()['sizes']));

    check('a ratio is saved reduced', ProjectJson::saveItem($path, 'media:ratios', '0', '10:4') === true && $read()['ratios'][0] === '5:2');
    check('the rest of the list comes along on the first write', array_slice($read()['ratios'], 1) === array_slice(Variants::RATIOS, 1));
    check('a decimal ratio is saved as a fraction', ProjectJson::saveItem($path, 'media:ratios', '0', '2.35:1') === true && $read()['ratios'][0] === '47:20');
    check('saving the same again is no change', ProjectJson::saveItem($path, 'media:ratios', '0', '47/20') === null);
    foreach (['nonsense', '0:1', '', '1:2:3'] as $bad) {
        check("ratio '$bad' is refused", ProjectJson::saveItem($path, 'media:ratios', '0', $bad) === false && $read()['ratios'][0] === '47:20');
    }
    check('a ratio that is already on the list is refused', ProjectJson::saveItem($path, 'media:ratios', '0', '32:18') === false);
    check('add puts the next usual ratio at the end', ProjectJson::addItem($path, 'media:ratios') === true && end($read()['ratios']) === '2:1');
    $count = count($read()['ratios']);
    check('remove takes one out', ProjectJson::removeItem($path, 'media:ratios', '0') === true && count($read()['ratios']) === $count - 1 && !in_array('47:20', $read()['ratios'], true));
    check('move reorders', ProjectJson::moveItem($path, 'media:ratios', (string) ($count - 2), '0', false) === true && $read()['ratios'][0] === '2:1');
    check('a ratio has no fields and no key to rename', ProjectJson::setField($path, 'media:ratios', '0', 'label', 'x') === false
        && ProjectJson::renameItem($path, 'media:ratios', '0', 'x') === false);

    check('a size is saved as a number', ProjectJson::saveItem($path, 'media:sizes', '1', '1,5') === true && $read()['sizes'] === [0.5, 1.5, 2, 5, 10]);
    foreach (['0', '-2', 'big', '500', ''] as $bad) {
        check("size '$bad' is refused", ProjectJson::saveItem($path, 'media:sizes', '1', $bad) === false);
    }
    check('a size that is already on the list is refused', ProjectJson::saveItem($path, 'media:sizes', '1', '2') === false);
    check('add doubles the largest size', ProjectJson::addItem($path, 'media:sizes') === true && end($read()['sizes']) === 20);
    ProjectJson::put($path, ['sizes' => [80], 'ratios' => ['1:1']]);
    check('add does not go beyond what a size may be', ProjectJson::addItem($path, 'media:sizes') === false && $read()['sizes'] === [80]);
    check('the last size cannot be removed', ProjectJson::removeItem($path, 'media:sizes', '0') === false && $read()['sizes'] === [80]);
    check('the last ratio cannot be removed', ProjectJson::removeItem($path, 'media:ratios', '0') === false && $read()['ratios'] === ['1:1']);

    ProjectJson::put($path, ['formats' => ['sample' => ['megapixels' => 2]], 'custom' => ['keep' => true], 'external' => ['youtube']]);
    ProjectJson::saveItem($path, 'media:sizes', '0', '0.25');
    check('other sections and unknown data stay', $read()['formats']['sample']['megapixels'] === 2 && $read()['custom']['keep'] === true && $read()['external'] === ['youtube']);
    check('the external list still works as before', ProjectJson::addItem($path, 'define:EXTERNAL_MEDIAS') === true && $read()['external'] === ['youtube', 'new']
        && ProjectJson::saveItem($path, 'define:EXTERNAL_MEDIAS', '1', 'vimeo') === true && ProjectJson::removeItem($path, 'define:EXTERNAL_MEDIAS', '0') === true && $read()['external'] === ['vimeo']);

    // The document the settings.php -> JSON conversion builds keeps both lists.
    $document = ProjectJson::documentFromGroups('media', ProjectJson::groups($path));
    check('conversion keeps ratios and sizes', $document['ratios'] === Variants::RATIOS && $document['sizes'] === [0.25, 1, 2, 5, 10]);
} finally {
    @unlink($path);
    @rmdir($dir);
}
echo "media variants settings: all checks passed\n";
