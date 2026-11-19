<?php
declare(strict_types=1);
// Phase 1 of media-crop-ratios.plan.md: the pure classes, no database, no files, no Imagick.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\CropBox;
use Systopic\System\Media\CropEdits;
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
check('a crop pushed over the edge does not', !(new CropBox($square, -0.1, 0.5))->inside(3000, 2000));
check('a crop below scale 1 does not', !(new CropBox($square, scale: 0.9))->inside(3000, 2000));
// The alignment: 0 flush left / top, 1 flush right / bottom, 0.5 centred - like background-position in per cent.
$flushLeft = (new CropBox($square, 0.0, 0.5, 2))->rect(3000, 2000);
$flushRight = (new CropBox($square, 1.0, 1.0, 2))->rect(3000, 2000);
check('align 0: flush left', $near($flushLeft['x'], 0) && $near($flushLeft['y'], 0.25));
check('align 1: flush right and bottom', $near($flushRight['x'] + $flushRight['w'], 1) && $near($flushRight['y'] + $flushRight['h'], 1));
check('align 0.25: the quarter of the crop lies on the quarter of the picture',
    $near(((new CropBox($square, 0.25, 0.5, 2))->rect(3000, 2000)['x']) + 0.25 / 3, 0.25));
check('from the middle and back', $near(CropBox::at($square, 0.3, 0.5, 2, 3000, 2000)->centre(3000, 2000)[0], 0.3)
    && $near(CropBox::alignOf(CropBox::centreOf(0.8, 0.4), 0.4), 0.8));
check('an axis the crop fills: any alignment is the same there, the one given is kept',
    $near(CropBox::at($square, 0.3, 0.5, 1, 3000, 2000, new CropBox($square, 0.4, 0.9))->alignY, 0.9));
// Inside 0-1 at scale 1 or more is inside whatever the reference - why the alignment is stored, not the middle.
$always = true;
foreach ([[3000, 2000], [2000, 3000], [1000, 1000], [6000, 1000], [923, 614]] as [$w, $h]) {
    foreach ([[0.0, 0.0, 1.0], [1.0, 0.3, 1.4], [0.5, 1.0, 2.5], [0.12, 0.98, 1.0]] as [$x, $y, $scale]) {
        foreach (['2:1', '1:1', '9:16'] as $name) {
            $always = $always && (new CropBox(Ratio::of($name), $x, $y, $scale))->inside($w, $h);
        }
    }
}
check('aligned within 0-1 at scale 1 or more: inside any picture', $always);
$clamped = (new CropBox($square, -0.2, 1.4, 0.9, CropBox::INTERPOLATED))->clamped(3000, 2000);
check('clamping brings the alignment into 0-1 and lifts the scale to 1',
    $near($clamped->alignX, 0) && $near($clamped->alignY, 1) && $near($clamped->scale, 1) && $clamped->inside(3000, 2000));
check('clamping keeps the origin', $clamped->source === CropBox::INTERPOLATED);
rejects('scale 0 is no crop', static fn() => new CropBox($square, scale: 0));
rejects('a basis is fill or fit', static fn() => new CropBox($square, basis: 'cover'));

// The basis "fit": scale 1 is "fit in", the alignment from touching on one side to touching on the other.
$fit = static fn(float $ax, float $ay, float $scale = 1.0, ?Ratio $ratio = null): CropBox => new CropBox($ratio ?? $square, $ax, $ay, $scale, CropBox::MANUAL, CropBox::FIT);
$rect = $fit(0.5, 0.5)->rect(3000, 2000);
check('"fit in": the square holds the whole picture, centred', $near($rect['w'], 1) && $near($rect['h'], 1.5) && $near($rect['x'], 0) && $near($rect['y'], -0.25));
$rect = $fit(0.0, 0.5)->rect(3000, 2000);
check('alignment 0: the picture lies left of the crop, its right edge on the crop\'s left edge', $near($rect['x'], 1));
$rect = $fit(1.0, 0.0)->rect(3000, 2000);
check('alignment 1: on the right; 0 upright: above, touching', $near($rect['x'] + $rect['w'], 0) && $near($rect['y'], 1));
$rect = $fit(0.5, 0.5, 2, Ratio::of('3:2'))->rect(3000, 2000);
check('the picture\'s own ratio: "fit in" and "fill in" are the same frame', $near($fit(0.5, 0.5, 1, Ratio::of('3:2'))->rect(3000, 2000)['w'], 1)
    && $near($rect['w'], 0.5) && $near($rect['x'], 0.25));
$moved = [];
foreach ([0.0, 0.25, 0.5, 0.75, 1.0] as $a) {
    $moved[] = $fit($a, 0.5, 1, Ratio::of('3:2'))->rect(3000, 2000)['x'];
}
check('and there - as long as the picture - every alignment is another place: the crop can be moved', count(array_unique(array_map(static fn(float $x): string => sprintf('%.9f', $x), $moved))) === 5);
foreach ([[0.2, 0.7, 1.3], [0.0, 1.0, 0.6], [0.55, 0.45, 3.0]] as [$ax, $ay, $scale]) {
    $crop = $fit($ax, $ay, $scale);
    [$cx, $cy] = $crop->centre(3000, 2000);
    [$w, $h] = $crop->size(3000, 2000);
    check("fit: from the alignment to the middle and back - $ax / $ay", $near(CropBox::alignOf($cx, $w, basis: CropBox::FIT), $ax) && $near(CropBox::alignOf($cy, $h, basis: CropBox::FIT), $ay));
    $fill = $crop->asFill(3000, 2000);
    $a = $crop->rect(3000, 2000);
    $b = $fill->rect(3000, 2000);
    check("fit: the same frame in 'fill' - $ax / $ay", $fill->basis === CropBox::FILL && $near($a['w'], $b['w']) && $near($a['h'], $b['h'])
        && (!$crop->inside(3000, 2000) || ($near($a['x'], $b['x']) && $near($a['y'], $b['y']))));
}
check('an alignment from the middle is within 0-1', CropBox::alignOf(-3.0, 0.5) === 0.0 && CropBox::alignOf(5.0, 0.5, basis: CropBox::FIT) === 0.0);
$clamped = $fit(1.4, -0.2, 0.5)->clamped();
check('fit: clamped keeps the scale, brings the alignment in', $near($clamped->alignX, 1) && $near($clamped->alignY, 0) && $near($clamped->scale, 0.5) && $clamped->basis === CropBox::FIT);
check('the basis stays with every copy', $fit(0.3, 0.5)->as(CropBox::INTERPOLATED)->basis === CropBox::FIT);
rejects('an alignment that is no number is no crop', static fn() => new CropBox($square, NAN));

// ----------------------------------------------------------- CropResolver
$fixture = json_decode((string) file_get_contents(__DIR__ . '/media-crop-resolver.cases.json'), true, flags: JSON_THROW_ON_ERROR);
check('resolver cases are there', count($fixture['cases']) >= 14);
foreach ($fixture['cases'] as $case) {
    $manual = array_map(static fn(array $c): CropBox => new CropBox(Ratio::of($c['ratio']), $c['ax'], $c['ay'], $c['scale'], basis: $c['basis'] ?? CropBox::FILL), $case['manual']);
    $declared = array_map(static fn(string $name): Ratio => Ratio::of($name), $case['declared'] ?? []);
    $crop = CropResolver::resolve(Ratio::of($case['ratio']), $case['master'][0], $case['master'][1], $manual, $case['pois'], $declared);
    $expect = $case['expect'];
    check('resolver: ' . $case['name'], $near($crop->alignX, $expect['ax']) && $near($crop->alignY, $expect['ay'])
        && $near($crop->scale, $expect['scale']) && $crop->source === $expect['source'] && $crop->ratio->name === $case['ratio']
        && $crop->basis === ($expect['basis'] ?? CropBox::FILL));
    if ($crop->source !== CropBox::MANUAL && $crop->basis === CropBox::FILL) {
        check('resolver: computed crop lies inside the master - ' . $case['name'], $crop->inside($case['master'][0], $case['master'][1]));
    }
}
$all = CropResolver::resolveAll([Ratio::of('16:9'), $square, Ratio::of('3:4')], 3000, 2000, [new CropBox($square, 0.3, 0.5, 2)]);
check('all ratios at once, by name - the ends automatic', array_keys($all) === ['16:9', '1:1', '3:4']
    && $all['1:1']->source === CropBox::MANUAL && $all['3:4']->source === CropBox::CENTERED && $all['16:9']->source === CropBox::CENTERED
    && $all['3:4']->alignX === 0.5 && $all['3:4']->scale === 1.0);
// A crop sliding from 1:1 to the whole master must not jump anywhere on the way.
$previous = null;
foreach (['1:1', '11:10', '6:5', '5:4', '4:3', '7:5', '3:2'] as $name) {
    $crop = CropResolver::resolve(Ratio::of($name), 3000, 2000, [new CropBox($square, 0.3, 0.5, 2)]);
    check("resolver: towards the master the crop only opens up - $name",
        $previous === null || ($crop->alignX >= $previous->alignX - 1e-9 && $crop->scale <= $previous->scale + 1e-9));
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
$left = new MasterGeometry(300, 200, new CropBox($square, 0.125, 0.5, 2));
check('master crop: aligned at an eighth, its middle at a quarter of the picture', $nearPoint($left->toMaster(75, 100), [50, 50]));
$turnedLeft = new MasterGeometry(300, 200, new CropBox($square, 0.0, 0.5, 2), 90);
check('turned master crop: flush left on the screen is down in the original', $nearPoint($turnedLeft->toMaster(150, 150), [50, 50]));
check('a master flush left at full height stays inside', (new MasterGeometry(300, 200, new CropBox($square, 0.0, 0.5)))->insideOriginal());
check('a master pushed over the edge shows background', !(new MasterGeometry(300, 200, new CropBox($square, -0.5, 0.5)))->insideOriginal());
check('a master below scale 1 shows background', !(new MasterGeometry(300, 200, new CropBox($square, scale: 0.8)))->insideOriginal());
$whole = new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2'), 0.5, 0.5, 1, basis: CropBox::FIT));
check('a master in "fit" at scale 1, the picture\'s ratio: the whole picture', $whole->width === 300 && $whole->height === 200 && $whole->insideOriginal());
$aside = new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2'), 0.25, 0.5, 1, basis: CropBox::FIT));
check('and it moves: a quarter of the way, half its width to the right', $near($aside->toMaster(150, 100)[0], 0) && !$aside->insideOriginal());
$fitIn = new MasterGeometry(300, 200, new CropBox($square, 0.5, 0.5, 1, basis: CropBox::FIT), 30);
check('a turned master in "fit" at scale 1: the box around the turned picture fits in', $near($fitIn->width, $fitIn->referenceWidth, 0.5) && $fitIn->height >= $fitIn->referenceHeight - 0.5);
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
// Turned without a master crop: the frame of the largest area inside the turned picture.
foreach ([5.0, -12.0, 20.0, 80.0, 95.0, 185.0] as $angle) {
    $largest = MasterGeometry::largest(3000, 2000, $angle);
    check("turned by $angle, near a right angle: all four corners touch an edge", $onEdge($largest, 1.5) === 4 && $largest->insideOriginal(1.5));
}
foreach ([30.0, 45.0, 60.0, -40.0, 135.0] as $angle) {
    $largest = MasterGeometry::largest(3000, 2000, $angle);
    check("turned by $angle, near the diagonal: two corners on the long edges, nothing outside", $onEdge($largest, 1.5) >= 2 && $largest->insideOriginal(1.5));
}
check('turned by a right angle: the whole picture',
    MasterGeometry::largest(3000, 2000, 90)->crop->ratio->name === '2:3' && MasterGeometry::largest(3000, 2000, 0)->crop->ratio->name === '3:2'
    && MasterGeometry::largest(3000, 2000, 180)->width === 3000 && MasterGeometry::largest(2000, 3000, -90)->height === 2000);
check('at 45 degrees: a square', MasterGeometry::largest(3000, 2000, 45)->crop->ratio->name === '1:1' && MasterGeometry::largest(2000, 3000, 45)->crop->ratio->name === '1:1');
// No other ratio gives more area at that angle - the closed form against a search over ratios.
foreach ([[3000, 2000], [2000, 3000], [4000, 3000], [1000, 1000], [6000, 1000]] as [$w, $h]) {
    foreach ([3.0, 17.0, 24.0, 31.0, 45.0, 52.0, 77.0] as $angle) {
        $largest = MasterGeometry::largest($w, $h, $angle);
        $best = 0.0;
        for ($i = -400; $i <= 400; $i++) {
            $probe = new MasterGeometry($w, $h, new CropBox(Ratio::ofPixels((int) round(1000 * exp($i / 100)), 1000)), $angle);
            $best = max($best, $probe->width * $probe->height);
        }
        check("turned by $angle, $w x $h: the largest area there is", $largest->width * $largest->height >= $best * 0.996);   // whole pixels: a frame of 700 px rounds by up to 0.15 %
    }
}
// Turning on, in tenths of a degree: the frame changes in small steps, never jumps.
foreach ([[3000, 2000], [2000, 3000], [1600, 1000], [1000, 1000]] as [$w, $h]) {
    $previous = null;
    $steady = true;
    for ($tenth = -1800; $tenth <= 1800; $tenth++) {
        $g = MasterGeometry::largest($w, $h, $tenth / 10);
        if ($previous !== null) {
            $steady = $steady && abs($g->width - $previous->width) <= 0.01 * max($w, $h) + 2 && abs($g->height - $previous->height) <= 0.01 * max($w, $h) + 2;
        }
        $previous = $g;
    }
    check("turning $w x $h all the way round: no jump", $steady);
}

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
check('points of interest reach the resolver through the master', $viaMaster->source === CropBox::POI && $viaMaster->alignX > 0.5);

// A new master under the crops: they keep alignment and scale (CropEdits::master); by quarter turns and
// mirroring the alignment goes with the picture.
$onTheLeft = new CropBox($square, 0.0, 0.5, 2, CropBox::MANUAL);
$quarter = CropEdits::reoriented($onTheLeft, 0, false, 90, false);
check('master turned a quarter: a crop on the left of the picture is on top', $near($quarter->alignX, 0.5) && $near($quarter->alignY, 0.0)
    && $near($quarter->scale, 2) && $quarter->source === CropBox::MANUAL && $quarter->ratio === $square);
check('master turned by 180 degrees: on the right', $near(CropEdits::reoriented($onTheLeft, 0, false, 180, false)->alignX, 1.0));
check('master mirrored: on the right', $near(CropEdits::reoriented($onTheLeft, 0, false, 0, true)->alignX, 1.0));
$roundTrip = CropEdits::reoriented(CropEdits::reoriented($onTheLeft, 0, false, 90, true), 90, true, -90, false);
$back = CropEdits::reoriented($roundTrip, -90, false, 0, false);
check('turned and mirrored there and back', $near($back->alignX, 0.0) && $near($back->alignY, 0.5));
check('a tilt counts as the nearest quarter', $near(CropEdits::reoriented($onTheLeft, 3, false, 84, false)->alignY, 0.0));
echo "media crop geometry: all checks passed\n";
