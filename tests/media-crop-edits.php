<?php
declare(strict_types=1);
// Phase 6 of media-crop-ratios.plan.md, step 1: what the image editor does to a medium, worked out without a database.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\CropBox;
use Systopic\System\Media\CropEdits;
use Systopic\System\Media\Ratio;
use Systopic\System\Media\Variants;
use Systopic\System\Queries\MediaCrops\CropSet;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\System\Tables\MediaCrops\Operator;
use Systopic\System\Tables\Medias\Model as MediaRow;

if (!class_exists('fs')) {
    class fs { public static $siteRoot; public static function toNative($path) { return $path; } }
}
if (!class_exists('http')) {
    class http { public static $root = '/site/'; }
}
fs::$siteRoot = strtr(sys_get_temp_dir(), DIRECTORY_SEPARATOR, '/') . '/fluxo-crop-edits-none/';
Variants::reset();
$near = static fn(?float $a, float $b, float $tolerance = 1e-6): bool => $a !== null && abs($a - $b) <= $tolerance;
$medium = static function (): MediaRow {
    $media = new MediaRow();
    $media->id = 42;
    $media->filename = 'bild.jpg';
    $media->mimetype = 'image/jpeg';
    $media->label = 'Bild';
    $media->width = 3000;
    $media->height = 2000;
    return $media;
};
// What an edit leaves behind, as the next read would find it.
$after = static function (CropSet $set, array $edit): CropSet {
    $rows = $set->rows;
    foreach ($edit['remove'] as $row) {
        if ($row instanceof Crop) unset($rows[(string) $row->format]);
    }
    foreach ($edit['save'] as $row) {
        if ($row instanceof Crop) $rows[(string) $row->format] = $row;
    }
    return new CropSet($set->media, array_values($rows));
};
$square = Ratio::of('1:1');

// ------------------------------------------------------------ crop of a ratio
$media = $medium();
$set = new CropSet($media, []);
$edit = CropEdits::crop($set, new CropBox(Ratio::of('16:9'), 0.4, 0.45, 1.5));
check('a crop of a declared ratio is one row to save', $edit !== null && count($edit['save']) === 1 && $edit['remove'] === [] && $edit['save'][0]->format === '16:9');
$set = $after($set, $edit);
check('it is the crop of that ratio from now on', $set->crop(Ratio::of('16:9'))->source === CropBox::MANUAL && $near($set->crop(Ratio::of('16:9'))->scale, 1.5));
check('a ratio that is not declared has no crop', CropEdits::crop($set, new CropBox(Ratio::of('7:5'))) === null);
$edit = CropEdits::crop($set, new CropBox(Ratio::of('32:18'), 0.5, 0.5, 1));
check('saving again changes the same row', count($edit['save']) === 1 && $edit['save'][0] === $set->row('16:9') && $edit['save'][0]->version === 2);
check('a crop may show background', CropEdits::crop($set, new CropBox($square, 0.1, 0.5, 0.7)) !== null);

// ------------------------------------------------------- back to automatic
$set = $after($set, $edit);
$edit = CropEdits::reset($set, '16:9');
check('back to automatic removes the row', $edit['save'] === [] && count($edit['remove']) === 1 && $edit['remove'][0] === $set->row('16:9'));
check('and the crop is computed again', $after($set, $edit)->crop(Ratio::of('16:9'))->source === CropBox::CENTERED);
check('a ratio without a crop of its own: nothing to do', CropEdits::reset($set, '1:1') === ['save' => [], 'remove' => [], 'clipped' => []]);
$legacy = Operator::cropped(null, $media, 'fullWidth', 0.5, 0.5, 1.0, 0.8);
check('rows of the old named formats are left alone', CropEdits::reset(new CropSet($media, [$legacy]), 'fullWidth')['remove'] === []);

// ------------------------------------------------------------------ master
$media = $medium();
$set = new CropSet($media, []);
check('an untouched master set to "automatic, nothing changed": nothing to write', CropEdits::master($set, null, []) === ['save' => [], 'remove' => [], 'clipped' => []]);
$edit = CropEdits::master($set, new CropBox($square, 0.5, 0.5, 1), ['b' => 110]);
$set = $after($set, $edit);
check('the master crop is the row named original, with its look', count($edit['save']) === 1 && $edit['save'][0]->format === Variants::MASTER
    && $set->master()->width === 2000 && $set->master()->height === 2000 && $set->look()['b'] === 110);

// A new master under a crop set by hand: the crop keeps its alignment and scale in the master.
$media = $medium();
$set = $after(new CropSet($media, []), CropEdits::crop(new CropSet($media, []), new CropBox(Ratio::of('16:9'), 0.5, 0.5, 2)));   // centred, half of fill in
$edit = CropEdits::master($set, new CropBox($square, 0.5, 0.5, 1), []);                                                       // the middle square
$kept = Operator::box($after($set, $edit)->row('16:9'));
check('a new master crop: only the master is written, the crops stay as they are', count($edit['save']) === 1 && $edit['clipped'] === []);
check('a crop set by hand keeps alignment and scale in the new master', $near($kept->alignX, 0.5) && $near($kept->alignY, 0.5) && $near($kept->scale, 2) && $kept->inside(2000, 2000));
$set = $after(new CropSet($media, []), CropEdits::crop(new CropSet($media, []), new CropBox(Ratio::of('16:9'), 0.0, 1.0, 1.2)));   // flush left and bottom
$edit = CropEdits::master($set, new CropBox(Ratio::of('4:5'), 0.3, 0.5, 1.4), []);                                            // a much smaller, upright master
$kept = Operator::box($after($set, $edit)->row('16:9'));
$newMaster = $after($set, $edit)->master();
check('flush left and bottom stays flush left and bottom - and inside, whatever the master', $near($kept->alignX, 0.0) && $near($kept->alignY, 1.0)
    && $kept->inside($newMaster->width, $newMaster->height) && $edit['clipped'] === []);
$set = $after(new CropSet($media, []), CropEdits::crop(new CropSet($media, []), new CropBox($square, -0.5, 0.5, 0.8)));           // shows background on purpose
$edit = CropEdits::master($set, new CropBox($square, 0.5, 0.5, 1), []);
$kept = Operator::box($after($set, $edit)->row('1:1'));
check('a crop that showed background on purpose still does', $near($kept->alignX, -0.5) && !$kept->inside(2000, 2000));

// Turning: the master becomes upright, a crop follows the picture.
$media = $medium();
$set = $after(new CropSet($media, []), CropEdits::crop(new CropSet($media, []), new CropBox($square, 0.125, 0.5, 2)));            // 1000 px square, its middle at a quarter
$edit = CropEdits::master($set, null, ['r' => 90]);
$turned = $after($set, $edit);
check('turned by 90 degrees without a master crop: the whole picture, upright', $turned->master()->width === 2000 && $turned->master()->height === 3000 && $turned->look()['r'] === 90.0);
$followed = Operator::box($turned->row('1:1'));
[$cx, $cy] = $followed->centre(2000, 3000);
check('the crop set by hand is turned with it: left became top', $near($cx, 0.5) && $near($cy, 0.25) && $near($followed->scale, 2));
$tilted = $after($set, CropEdits::master($set, null, ['r' => 10]));
check('turned by 10 degrees without a master crop: the largest frame inside the turned picture', $tilted->master()->insideOriginal(1.5) && $tilted->master()->width < 3000 && $tilted->master()->crop->scale === 1.0);

// Back to automatic: the crop goes, the look stays.
$media = $medium();
$set = $after(new CropSet($media, []), CropEdits::master(new CropSet($media, []), new CropBox($square, 0.3, 0.5, 2), ['b' => 120, 'r' => 180]));
$reset = $after($set, CropEdits::reset($set, Variants::MASTER));
check('master back to automatic: the whole picture again, its look kept', $reset->master()->width === 3000 && $reset->master()->height === 2000 && $reset->look()['b'] === 120 && $reset->look()['r'] === 180.0
    && Operator::box($reset->row(Variants::MASTER)) === null);
$unknown = $medium();
$unknown->width = $unknown->height = 0;
rejects('a master needs the size of the original', static fn() => CropEdits::master(new CropSet($unknown, []), new CropBox($square), []));

// -------------------------------------------------------------------- pois
$media = $medium();
$set = $after(new CropSet($media, []), CropEdits::crop(new CropSet($media, []), new CropBox($square, 0.3, 0.5, 1)));
$edit = CropEdits::pois($set, [['x' => 0.9, 'y' => '0.5', 'w' => 0.1, 'h' => 2, 'weight' => 3, 'type' => 'face', 'source' => 'manual', 'evil' => '<script>'],
    ['x' => 'left'], 'nonsense', ['x' => -1, 'y' => 0.2, 'type' => 'Bad Type!']]);
check('points of interest are stored on the medium, cleaned', $edit['save'] === [$media] && $edit['remove'] === []
    && (string) $media->pois === '[{"x":0.9,"y":0.5,"w":0.1,"h":1,"weight":3,"type":"face","source":"manual"},{"x":0,"y":0.2,"w":0,"h":0}]');
check('crops set by hand go before points', $set->crop($square)->source === CropBox::MANUAL && $set->crop(Ratio::of('9:16'))->source === CropBox::POI);
$edit = CropEdits::pois($set, [['x' => 0.9, 'y' => 0.5]], dropCrops: true);
check('with "delete the crops": the rows of the ratios go', count($edit['remove']) === 1 && $edit['remove'][0] === $set->row('1:1') && $after($set, $edit)->crop($square)->source === CropBox::POI);
$withMaster = $after($set, CropEdits::master($set, new CropBox(Ratio::of('3:2'), 0.5, 0.5, 1.2), []));
check('the master crop is not one of them', !in_array($withMaster->row(Variants::MASTER), CropEdits::pois($withMaster, [], dropCrops: true)['remove'], true));
CropEdits::pois($set, []);
check('no points: nothing stored', $media->pois === null);
check('no more than fifty points', count(json_decode((string) (function () use ($set, $media) { CropEdits::pois($set, array_fill(0, 80, ['x' => 0.5, 'y' => 0.5])); return $media->pois; })(), true)) === 50);
$media->pois = null;

// ------------------------------------------------------------------- state
$media = $medium();
$set = $after(new CropSet($media, []), CropEdits::crop(new CropSet($media, []), new CropBox($square, 0.3, 0.5, 2)));
$state = CropEdits::state($set);
check('the editor is told the original and the picture it shows of it', $state['mediaId'] === 42 && $state['cut'] === true && $state['original']['width'] === 3000
    && str_contains($state['original']['url'], '/site/var/formats/000/42/bild--original.webp?v='));
check('the master: whole picture, not set by hand, with its sizes to show', $state['master']['manual'] === false && $state['master']['ratio'] === '3:2' && $state['master']['width'] === 3000
    && $state['master']['angle'] === 0.0 && $state['master']['flip'] === false && $state['master']['look'] === ['b' => 100, 'c' => 100, 's' => 100]
    && count($state['master']['sources']) === 4 && $state['master']['sources'][0]['width'] === 866);
$byName = array_column($state['ratios'], null, 'name');
check('every declared ratio, wide to narrow', array_column($state['ratios'], 'name') === Variants::RATIOS);
check('with its place on the slider: widest at 0, narrowest at 1, 1:1 where the logarithm puts it', $byName['2:1']['position'] === 0.0 && $byName['9:16']['position'] === 1.0
    && $near($byName['1:1']['position'], log(2) / log(2 / 0.5625)) && $byName['16:9']['position'] > $byName['2:1']['position']);
check('with its crop and where that came from', $byName['1:1']['source'] === 'manual' && $near($byName['1:1']['ax'], 0.3) && $near($byName['1:1']['scale'], 2)
    && $byName['4:3']['source'] === 'interpolated' && $byName['9:16']['source'] === 'anchor' && $byName['16:9']['source'] === 'interpolated' && $byName['3:2']['source'] === 'centered');
check('beyond the last crop, the end of the slider is "fill in"', $near($byName['9:16']['scale'], 1) && $near($byName['9:16']['ax'], 0.3)
    && $byName['3:4']['source'] === 'interpolated' && $byName['3:4']['scale'] > 1 && $byName['3:4']['scale'] < 2);
check('the state is JSON', is_string(json_encode($state, JSON_THROW_ON_ERROR)) && $state['sizes'] === [0.5, 1.0, 2.0, 5.0, 10.0] && $state['pois'] === []);
check('a medium of unknown size has no master to edit', CropEdits::state(new CropSet($unknown, []))['master'] === null);
$vector = $medium();
$vector->mimetype = 'image/svg+xml';
check('an svg cannot be cut', CropEdits::state(new CropSet($vector, []))['cut'] === false && CropEdits::state(new CropSet($vector, []))['original']['url'] === null);

// ---------------------------------------------------------- what a request sends
check('a crop from a request', CropEdits::boxFrom('16:9', '0.4', '0.6', '1.5')?->ratio->name === '16:9' && $near(CropEdits::boxFrom('16:9', '0.4', '0.6', '1.5')->alignY, 0.6));
foreach ([['nonsense', 0.5, 0.5, 1], ['16:9', 'x', 0.5, 1], ['16:9', 0.5, 0.5, 0], ['16:9', 0.5, 0.5, -1], ['16:9', 99, 0.5, 1], ['16:9', 0.5, 0.5, 1000], [null, 0.5, 0.5, 1], ['16:9', NAN, 0.5, 1]] as $i => $bad) {
    check("a request that is no crop is refused - $i", CropEdits::boxFrom(...$bad) === null);
}
Variants::reset();
echo "media crop edits: all checks passed\n";
