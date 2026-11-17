<?php
declare(strict_types=1);
// Phase 2 of media-crop-ratios.plan.md: declared ratios and sizes, file names, the crop row in its new form.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\CropBox;
use Systopic\System\Media\Ratio;
use Systopic\System\Media\Variants;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\System\Tables\MediaCrops\Operator;
use Systopic\System\Tables\Medias\Model as MediaRow;

$near = static fn(float $a, float $b): bool => abs($a - $b) <= 1e-9;

// --------------------------------------------------------------- defaults
Variants::reset();
check('a project that says nothing has ratios', array_keys(Variants::ratios()) === Variants::RATIOS);
check('and sizes, small to large', Variants::sizes() === [0.5, 1.0, 2.0, 5.0, 10.0] && Variants::smallest() === 0.5);
check('a declared ratio is found in any spelling', Variants::ratio('32/18')?->name === '16:9' && Variants::ratio('16by9')?->name === '16:9');
check('an undeclared ratio is not', Variants::ratio('753:1364') === null && Variants::ratio('nonsense') === null);

// -------------------------------------------------------------- configure
Variants::configure(['1:1', '16:9', '1.91:1', 'nonsense', '0:1', '32:18', ['x']], [5, '0.5', 2, 0, -1, 'big', 500, 2.0]);
check('ratios are reduced, sorted wide to narrow, doubles and nonsense dropped', array_keys(Variants::ratios()) === ['191:100', '16:9', '1:1']);
check('sizes are sorted, doubles and nonsense dropped', Variants::sizes() === [0.5, 2.0, 5.0]);
Variants::configure(null, [1, 3]);
check('null keeps what is set', array_keys(Variants::ratios()) === ['191:100', '16:9', '1:1'] && Variants::sizes() === [1.0, 3.0]);
Variants::configure([], []);
check('an empty list goes back to the default', array_keys(Variants::ratios()) === Variants::RATIOS && Variants::sizes() === [0.5, 1.0, 2.0, 5.0, 10.0]);
Variants::configure(['nonsense'], ['nonsense']);
check('a list with nothing readable goes back to the default too', count(Variants::ratios()) === count(Variants::RATIOS) && Variants::smallest() === 0.5);

// ------------------------------------------------------------------ sizes
Variants::reset();
check('2 megapixels at 16:9', Variants::pixels(Ratio::of('16:9'), 2) === [1886, 1061]);
check('0.5 megapixels at 1:1', Variants::pixels(Ratio::of('1:1'), 0.5) === [707, 707]);
foreach (['16:9', '1:1', '9:16', '191:100'] as $name) {
    [$w, $h] = Variants::pixels(Ratio::of($name), 5);
    check("every ratio has the same number of pixels at a size: $name", abs($w * $h - 5e6) < 5e6 * 0.002);
}
check('no size larger than the picture itself', Variants::sizesWithin(3000, 2000) === [0.5, 1.0, 2.0, 5.0] && Variants::sizesWithin(1600, 1000) === [0.5, 1.0]);
check('a tiny picture still has its preview', Variants::sizesWithin(300, 200) === [0.5]);

// ------------------------------------------------------------- file names
foreach ([['master', null, null], ['2mp', null, 2.0], ['16by9', '16:9', null], ['2mp16by9', '16:9', 2.0], ['0.5mp1by1', '1:1', 0.5], ['10mp9by16', '9:16', 10.0]] as [$suffix, $ratio, $megapixels]) {
    check("file name part '$suffix' is written", Variants::suffix($ratio === null ? null : Ratio::of($ratio), $megapixels) === $suffix);
    $read = Variants::parse($suffix);
    check("file name part '$suffix' reads back", $read !== null && $read['ratio']?->name === $ratio && $read['megapixels'] === $megapixels && $read['original'] === false);
}
check("'original' is the editor's copy of the untouched picture", Variants::parse('original') === ['ratio' => null, 'megapixels' => null, 'original' => true]);
foreach (['', '3mp', '753by1364', '2mp753by1364', '32by18', '2.0mp', '2mp16by9x', 'fullWidth', '16:9', '2mp-16by9', '../x', '2mp16by', 'mp', '0mp'] as $suffix) {
    check("file name part '$suffix' is refused - nothing undeclared is rendered", Variants::parse($suffix) === null);
}
Variants::configure(['191:100', '1:1'], [0.5, 2]);
check('a project\'s own ratio is rendered, the default ones no longer', Variants::parse('2mp191by100') !== null && Variants::parse('16by9') === null && Variants::parse('1mp1by1') === null);
Variants::reset();

// ----------------------------------------------------------------- aliases
$alias = static function (array $format): string {
    $a = Variants::alias($format);
    return Variants::suffix($a['ratio'], $a['megapixels']);
};
check('named format 1920 x 1920: the square, the size that holds its pixels', $alias(['width' => 1920, 'height' => 1920]) === '5mp1by1');
check('named format 2880 x 1620: 16:9 at 5 megapixels', $alias(['width' => 2880, 'height' => 1620]) === '5mp16by9');
check('an odd size goes to the nearest declared ratio', $alias(['width' => 1788, 'height' => 2160]) === '5mp3by4' && $alias(['width' => 2160, 'height' => 1788]) === '5mp4by3');
check('the share image 1200 x 630 is 191:100', $alias(['width' => 1200, 'height' => 630]) === '1mp191by100');
check('a thumbnail takes the smallest size', $alias(['width' => 200, 'height' => 200]) === '0.5mp1by1');
check('megapixels alone: the master at that size', $alias(['megapixels' => 4]) === '5mp' && $alias(['megapixels' => 2]) === '2mp');
check('megapixels with a ratio', $alias(['megapixels' => 3, 'aspect_ratio' => '16:9']) === '5mp16by9' && $alias(['megapixels' => 1, 'aspect_ratio' => '5:6']) === '1mp3by4');
check('more than the largest size: the largest', $alias(['megapixels' => 40]) === '10mp');
check('a width alone: the master, sized for a usual picture', $alias(['width' => 1200]) === '1mp' && $alias(['height' => 800]) === '1mp');
check('no size at all: the master in its own pixels', $alias(['megapixels' => null, 'cms_display' => 'none']) === 'master');
check('every alias is an address that is rendered', Variants::parse($alias(['width' => 1788, 'height' => 2160])) !== null && Variants::parse($alias(['megapixels' => 4])) !== null);

// -------------------------------------------------------------- crop rows
$media = new MediaRow();
$crop = Operator::put(null, $media, new CropBox(Ratio::of('16:9'), 0.3, 0.4, 1.5));
check('a crop of a ratio is found by the ratio\'s name', $crop->format === '16:9' && $crop->ratio === '16:9' && $crop->media === $media);
check('centre and scale are stored, the row is no longer automatic', (string) $crop->centerX === '0.300000000000' && (string) $crop->centerY === '0.400000000000'
    && (string) $crop->scale === '1.500000000000' && $crop->auto === false && $crop->version === 1);
$box = Operator::box($crop);
check('and read back as the same crop, set by hand', $box !== null && $box->ratio->name === '16:9' && $near($box->cx, 0.3) && $near($box->cy, 0.4) && $near($box->scale, 1.5) && $box->source === CropBox::MANUAL);
$again = Operator::put($crop, $media, new CropBox(Ratio::of('16:9'), 0.5, 0.5, 1));
check('saving again keeps the row and bumps the version', $again === $crop && $crop->version === 2 && $near(Operator::box($crop)->scale, 1));
$master = Operator::put(null, $media, new CropBox(Ratio::ofPixels(3012, 5456), 0.5, 0.45, 1.2), master: true);
check('the master crop is the row named original, with its own free ratio', $master->format === Variants::MASTER && $master->ratio === '753:1364' && Operator::box($master)->ratio->name === '753:1364');
check('a crop may show background: nothing is clamped on the way in', $near(Operator::box(Operator::put(null, $media, new CropBox(Ratio::of('1:1'), -0.2, 0.5, 0.6)))->cx, -0.2));
$legacy = Operator::cropped(null, $media, 'fullWidth', 0.5, 0.5, 1.0, 0.8);
check('a row with only the legacy columns has no crop in the new form', Operator::box($legacy) === null && Operator::box(null) === null);
$broken = new Crop();
$broken->ratio = 'nonsense';
check('a row with an unreadable ratio has none either', Operator::box($broken) === null);

// ------------------------------------------------------------------- look
check('an untouched master has the neutral look', Operator::look(null) === Operator::LOOK && Operator::look($master) === Operator::LOOK);
Operator::withLook($master, ['b' => 110, 'c' => '95', 's' => 250, 'r' => 12.5, 'f' => true, 'x' => 'ignored']);
check('the look is stored as JSON in the master\'s row', (string) $master->edit === '{"b":110,"c":95,"s":200,"r":12.5,"f":1}' && $master->version === 2);
check('and read back with every key', Operator::look($master) === ['b' => 110, 'c' => 95, 's' => 200, 'r' => 12.5, 'f' => 1]);
Operator::withLook($master, ['r' => 270]);
check('an angle is kept between -180 and 180', Operator::look($master)['r'] === -90.0);
Operator::withLook($master, ['r' => 360, 'b' => 100]);
check('a look that changes nothing is stored as null', $master->edit === null && Operator::look($master) === Operator::LOOK);
$master->edit = new \Systopic\Db\Type\Text('not json');
check('an unreadable look is the neutral one', Operator::look($master) === Operator::LOOK);
echo "media variants: all checks passed\n";
