<?php
declare(strict_types=1);
// Phase 1 of media-crop-ratios.plan.md: the pure classes, no database, no files, no Imagick.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\CropBox;
use Systopic\System\Media\CropResolver;
use Systopic\System\Media\MasterGeometry;
use Systopic\System\Media\Ratio;

$near = static fn(float $a, float $b, float $tolerance = 1e-6): bool => abs($a - $b) <= $tolerance;
$nearPoint = static fn(array $a, array $b, float $tolerance = 1e-6): bool => $near($a[0], $b[0], $tolerance) && $near($a[1], $b[1], $tolerance);

// ------------------------------------------------------------------ Ratio
foreach (['16:9' => '16:9', '32/18' => '16:9', '16by9' => '16:9', ' 1.91:1 ' => '191:100', '1.5' => '3:2',
    '1' => '1:1', '2.0:1.0' => '2:1', '1920:1080' => '16:9', '3 : 4' => '3:4'] as $input => $name) {
    check("ratio '$input' reads as $name", Ratio::of((string) $input)?->name === $name);
}
foreach (['', '0:1', '16:0', '-1:2', '1:2:3', 'abc', '16:', ':9', '1.23456:1', '16x9', '1e3:1'] as $input) {
    check("ratio '$input' is refused", Ratio::of($input) === null);
}
check('a freely dragged master gets the fraction of its pixels', Ratio::ofPixels(3012, 5456)->name === '753:1364');
check('pixel sizes reduce', Ratio::ofPixels(1920, 1080)->name === '16:9');
rejects('a size of zero is no ratio', static fn() => Ratio::ofPixels(0, 100));
check('file name part has no colon', Ratio::of('191:100')->suffix === '191by100');
check('the file name part reads back', Ratio::of(Ratio::of('16:9')->suffix)->equals(Ratio::of('16:9')));
check('same name, same ratio', Ratio::of('1.7777:1') !== null && !Ratio::of('1.7777:1')->equals(Ratio::of('16:9')));
check('ratios compare exactly', Ratio::compare(Ratio::of('16:9'), Ratio::of('1777:1000')) > 0
    && Ratio::compare(Ratio::of('3:4'), Ratio::of('1:1')) < 0 && Ratio::compare(Ratio::of('2:1'), Ratio::of('4:2')) === 0);
check('1:1 lies in the middle between a ratio and its upright twin',
    $near(Ratio::of('1:1')->position(Ratio::of('9:16'), Ratio::of('16:9')), 0.5));
check('2:1 lies halfway between 1:1 and 4:1', $near(Ratio::of('2:1')->position(Ratio::of('1:1'), Ratio::of('4:1')), 0.5));
check('a position between a ratio and itself is 0', Ratio::of('1:1')->position(Ratio::of('1:1'), Ratio::of('1:1')) === 0.0);

// ---------------------------------------------------------------- CropBox
$square = Ratio::of('1:1');
[$w, $h] = (new CropBox($square))->size(3000, 2000);
check('scale 1 fills the narrow axis: 1:1 in 3000 x 2000', $near($w, 2 / 3) && $near($h, 1));
[$w, $h] = (new CropBox(Ratio::of('16:9')))->size(3000, 2000);
check('scale 1 fills the narrow axis: 16:9 in 3000 x 2000', $near($w, 1) && $near($h, 0.84375));
[$w, $h] = (new CropBox($square, scale: 2))->size(3000, 2000);
check('scale 2 has half the edge length', $near($w, 1 / 3) && $near($h, 0.5));
check('the legacy 1 / crop_height is the scale', $near($h, 0.5));
check('crop in pixels', (new CropBox(Ratio::of('16:9')))->pixels(3000, 2000) === ['x' => 0, 'y' => 156, 'w' => 3000, 'h' => 1688]);
foreach ([['16:9', 1.0], ['3:4', 1.7], ['191:100', 0.6], ['753:1364', 2.3]] as [$name, $scale]) {
    $rect = (new CropBox(Ratio::of($name), 0.31, 0.62, $scale))->rect(4000, 3000);
    check("no stretching: $name at scale $scale keeps its ratio",
        $near($rect['w'] * 4000 / ($rect['h'] * 3000), Ratio::of($name)->value));
}
check('fit in: the whole picture inside a square', $near(CropBox::fitScale($square, 3000, 2000), 2 / 3));
[$w, $h] = (new CropBox($square, scale: CropBox::fitScale($square, 3000, 2000)))->size(3000, 2000);
check('at fit-in scale the wide axis is exactly filled, the other shows background', $near($w, 1) && $near($h, 1.5));
check('fit in equals fill in when the ratios match', $near(CropBox::fitScale(Ratio::of('3:2'), 3000, 2000), 1));
check('centred crop lies inside', (new CropBox($square))->inside(3000, 2000));
check('a crop pushed over the edge does not', !(new CropBox($square, 0.1, 0.5))->inside(3000, 2000));
check('a crop below scale 1 does not', !(new CropBox($square, scale: 0.9))->inside(3000, 2000));
$clamped = (new CropBox($square, 0.1, 0.5, 0.9, CropBox::INTERPOLATED))->clamped(3000, 2000);
check('clamping moves the centre in and lifts the scale to 1',
    $near($clamped->cx, 1 / 3) && $near($clamped->cy, 0.5) && $near($clamped->scale, 1) && $clamped->inside(3000, 2000));
check('clamping keeps the origin', $clamped->source === CropBox::INTERPOLATED);
rejects('scale 0 is no crop', static fn() => new CropBox($square, scale: 0));
rejects('a centre that is no number is no crop', static fn() => new CropBox($square, NAN));

// ----------------------------------------------------------- CropResolver
$fixture = json_decode((string) file_get_contents(__DIR__ . '/media-crop-resolver.cases.json'), true, flags: JSON_THROW_ON_ERROR);
check('resolver cases are there', count($fixture['cases']) >= 14);
foreach ($fixture['cases'] as $case) {
    $manual = array_map(static fn(array $c): CropBox => new CropBox(Ratio::of($c['ratio']), $c['cx'], $c['cy'], $c['scale']), $case['manual']);
    $crop = CropResolver::resolve(Ratio::of($case['ratio']), $case['master'][0], $case['master'][1], $manual, $case['pois']);
    $expect = $case['expect'];
    check('resolver: ' . $case['name'], $near($crop->cx, $expect['cx']) && $near($crop->cy, $expect['cy'])
        && $near($crop->scale, $expect['scale']) && $crop->source === $expect['source'] && $crop->ratio->name === $case['ratio']);
    if ($crop->source !== CropBox::MANUAL) {
        check('resolver: computed crop lies inside the master - ' . $case['name'], $crop->inside($case['master'][0], $case['master'][1]));
    }
}
$all = CropResolver::resolveAll([Ratio::of('16:9'), $square, Ratio::of('3:4')], 3000, 2000, [new CropBox($square, 0.3, 0.5, 2)]);
check('all ratios at once, by name', array_keys($all) === ['16:9', '1:1', '3:4']
    && $all['1:1']->source === CropBox::MANUAL && $all['3:4']->source === CropBox::ANCHOR && $all['16:9']->source === CropBox::ANCHOR);
// A crop sliding from 1:1 to the whole master must not jump anywhere on the way.
$previous = null;
foreach (['1:1', '11:10', '6:5', '5:4', '4:3', '7:5', '3:2'] as $name) {
    $crop = CropResolver::resolve(Ratio::of($name), 3000, 2000, [new CropBox($square, 0.3, 0.5, 2)]);
    check("resolver: towards the master the crop only opens up - $name",
        $previous === null || ($crop->cx >= $previous->cx - 1e-9 && $crop->scale <= $previous->scale + 1e-9));
    $previous = $crop;
}

// --------------------------------------------------------- MasterGeometry
$whole = MasterGeometry::whole(300, 200);
check('untouched: the master is the original', $whole->width === 300 && $whole->height === 200 && $whole->crop->ratio->name === '3:2');
check('untouched: a point stays where it is', $nearPoint($whole->toMaster(40, 70), [40, 70]) && $whole->insideOriginal());
$turn = static fn(float $angle, bool $flip = false): MasterGeometry
    => new MasterGeometry(300, 200, new CropBox(Ratio::ofPixels(...(abs(fmod($angle, 180)) === 90.0 ? [200, 300] : [300, 200]))), $angle, $flip);
check('90 degrees: upright master', $turn(90)->width === 200 && $turn(90)->height === 300);
check('90 degrees turns clockwise: top left goes to top right', $nearPoint($turn(90)->toMaster(0, 0), [200, 0]));
check('90 degrees: top right goes to bottom right', $nearPoint($turn(90)->toMaster(300, 0), [200, 300]));
check('180 degrees: top left goes to bottom right', $nearPoint($turn(180)->toMaster(0, 0), [300, 200]));
check('-90 degrees: top left goes to bottom left', $nearPoint($turn(-90)->toMaster(0, 0), [0, 300]));
check('270 degrees is -90', $nearPoint($turn(270)->toMaster(0, 0), [0, 300]));
check('right angles are exact', $turn(90)->toMaster(0, 0) === [200.0, 0.0] && $turn(90)->insideOriginal(0.0));
check('mirrored: top left goes to top right', $nearPoint($turn(0, true)->toMaster(0, 0), [300, 0]));
check('mirrored, then turned', $nearPoint($turn(90, true)->toMaster(0, 0), [200, 300]));
foreach ([[0.0, false], [90.0, true], [10.0, false], [-33.5, true], [180.0, false], [47.0, true]] as [$angle, $flip]) {
    $g = new MasterGeometry(300, 200, new CropBox(Ratio::of('4:3'), 0.4, 0.55, 1.3), $angle, $flip);
    check("there and back: angle $angle" . ($flip ? ', mirrored' : ''), $nearPoint($g->toOriginal(...$g->toMaster(123.4, 56.7)), [123.4, 56.7]));
}

$cut = new MasterGeometry(300, 200, new CropBox($square, scale: 2));
check('master crop: a square of half the height, in the middle', $cut->width === 100 && $cut->height === 100
    && $nearPoint($cut->toMaster(100, 50), [0, 0]) && $cut->insideOriginal());
$left = new MasterGeometry(300, 200, new CropBox($square, 0.25, 0.5, 2));
check('master crop: the centre is a fraction of the picture', $nearPoint($left->toMaster(75, 100), [50, 50]));
$turnedLeft = new MasterGeometry(300, 200, new CropBox($square, 0.25, 0.5, 2), 90);
check('turned master crop: left on the screen is down in the original', $nearPoint($turnedLeft->toMaster(150, 150), [50, 50]));
check('a master pushed over the edge shows background', !(new MasterGeometry(300, 200, new CropBox($square, 0.0, 0.5)))->insideOriginal());
check('a master below scale 1 shows background', !(new MasterGeometry(300, 200, new CropBox($square, scale: 0.8)))->insideOriginal());
$free = new MasterGeometry(6000, 8000, new CropBox(Ratio::ofPixels(3012, 5456), 0.5, 0.5, 8000 / 5456));
check('a freely dragged master keeps its pixels exactly', $free->width === 3012 && $free->height === 5456 && $free->crop->ratio->name === '753:1364');

// Scale 1 is "fill in" as the picture stands: the largest frame inside the turned picture.
$onEdge = static function (MasterGeometry $g, float $tolerance = 1e-6): int {
    $touching = 0;
    foreach ($g->corners() as [$x, $y]) {
        $touching += (int) (min(abs($x), abs($y), abs($x - $g->originalWidth), abs($y - $g->originalHeight)) <= $tolerance);
    }
    return $touching;
};
foreach ([10.0, -10.0, 33.0, 45.0, 60.0, 84.0, 170.0] as $angle) {
    foreach (['3:2', '1:1', '9:16'] as $name) {
        $tilted = new MasterGeometry(300, 200, new CropBox(Ratio::of($name)), $angle);
        check("turned by $angle, $name: scale 1 shows no background and touches the edges",
            $tilted->insideOriginal(1e-6) && $onEdge($tilted) >= 2);
        $wider = new MasterGeometry(300, 200, new CropBox(Ratio::of($name), scale: 0.98), $angle);
        check("turned by $angle, $name: scale 1 is the largest such frame", !$wider->insideOriginal(1e-6));
    }
}
check('turned: the frame gets smaller the further the picture is tilted',
    (new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2')), 10))->width < 300
    && (new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2')), 20))->width < (new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2')), 10))->width);
check('turned: the centre is a fraction of the box around the turned picture',
    $near($turn(90)->referenceWidth, 200) && $near($turn(90)->referenceHeight, 300)
    && $near((new MasterGeometry(300, 200, new CropBox($square), 45))->referenceWidth, 500 * M_SQRT1_2));
check('fit in at a right angle is the plain fit in', $near($whole->fitScale(), 1)
    && $near((new MasterGeometry(300, 200, new CropBox($square)))->fitScale(), 2 / 3));
$fitted = new MasterGeometry(300, 200, new CropBox($square, scale: (new MasterGeometry(300, 200, new CropBox($square), 20))->fitScale()), 20);
$allInside = true;
foreach ([[0, 0], [300, 0], [300, 200], [0, 200]] as [$x, $y]) {
    [$mx, $my] = $fitted->toMaster($x, $y);
    $allInside = $allInside && $mx >= -1e-6 && $my >= -1e-6 && $mx <= $fitted->width + 1 && $my <= $fitted->height + 1;
}
check('fit in, turned: the whole picture lies in the frame', $allInside && $fitted->fitScale() < 1);
// Turning a picture in the editor: the one frame whose four corners touch an edge each.
foreach ([5.0, -12.0, 20.0, 80.0, 95.0, 185.0] as $angle) {
    $touching = MasterGeometry::touching(3000, 2000, $angle);
    check("turned by $angle: all four corners touch an edge", $touching !== null && $onEdge($touching, 1.5) === 4 && $touching->insideOriginal(1.5));
}
check('turned by a right angle: the touching frame is the whole picture',
    MasterGeometry::touching(3000, 2000, 90)->crop->ratio->name === '2:3' && MasterGeometry::touching(3000, 2000, 0)->crop->ratio->name === '3:2');
check('near the diagonal there is no such frame', MasterGeometry::touching(3000, 2000, 45) === null && MasterGeometry::touching(3000, 2000, 40) === null);

$pois = [['x' => 0.5, 'y' => 0.5, 'weight' => 2, 'type' => 'face'], ['x' => 0.1, 'y' => 0.5], ['x' => 0.5, 'y' => 0.5, 'w' => 0.5, 'h' => 0.1]];
$same = $whole->poisToMaster($pois);
$expected = [[0.5, 0.5, 0.0, 0.0], [0.1, 0.5, 0.0, 0.0], [0.5, 0.5, 0.5, 0.1]];
foreach ($expected as $i => [$x, $y, $w, $h]) {
    check("points of interest: unchanged in an untouched master - $i", array_keys($same[$i]) === array_keys(['x' => 0, 'y' => 0, 'w' => 0, 'h' => 0] + $pois[$i])
        && $near($same[$i]['x'], $x) && $near($same[$i]['y'], $y) && $near($same[$i]['w'], $w) && $near($same[$i]['h'], $h));
}
$inCut = $cut->poisToMaster($pois);
check('points of interest: one outside the master crop is left out', count($inCut) === 2);
check('points of interest: the middle stays the middle, weight and type stay', $near($inCut[0]['x'], 0.5) && $near($inCut[0]['y'], 0.5)
    && $inCut[0]['weight'] === 2 && $inCut[0]['type'] === 'face');
check('points of interest: one reaching over the edge is cut', $near($inCut[1]['w'], 1) && $near($inCut[1]['h'], 0.2) && $near($inCut[1]['x'], 0.5));
$mirrored = $turn(0, true)->poisToMaster([['x' => 0.4, 'y' => 0.3]]);
check('points of interest: mirrored with the picture', $near($mirrored[0]['x'], 0.6) && $near($mirrored[0]['y'], 0.3));
$turned = $turn(90)->poisToMaster([['x' => 0.1, 'y' => 0.5, 'w' => 0.1, 'h' => 0.2]]);
check('points of interest: turned with the picture, width and height swap',
    $near($turned[0]['x'], 0.5) && $near($turned[0]['y'], 0.1) && $near($turned[0]['w'], 0.2) && $near($turned[0]['h'], 0.1));
// The resolver takes what the geometry hands over.
$viaMaster = CropResolver::resolve(Ratio::of('9:16'), $whole->width, $whole->height, [], $whole->poisToMaster([['x' => 0.9, 'y' => 0.5]]));
check('points of interest reach the resolver through the master', $viaMaster->source === CropBox::POI && $viaMaster->cx > 0.5);

$moved = $whole->rebase(new CropBox($square, 0.5, 0.5, 4), $cut);
check('new master crop: a crop keeps its place and its pixels', $near($moved->cx, 0.5) && $near($moved->cy, 0.5) && $near($moved->scale, 2) && $moved->inside($cut->width, $cut->height));
$lost = $whole->rebase(new CropBox($square, 0.25, 0.5, 4, CropBox::MANUAL), $cut);
check('new master crop: a crop that fell outside is not hidden', $near($lost->cx, -0.25) && !$lost->inside($cut->width, $cut->height) && $lost->source === CropBox::MANUAL);
$back = $cut->rebase($moved, $whole);
check('new master crop: there and back', $near($back->cx, 0.5) && $near($back->cy, 0.5) && $near($back->scale, 4));
$afterTurn = $whole->rebase(new CropBox($square, 0.25, 0.5, 2), $turn(90));
check('master turned: the crop follows the picture', $near($afterTurn->cx, 0.5) && $near($afterTurn->cy, 0.25) && $near($afterTurn->scale, 2));
echo "media crop geometry: all checks passed\n";
