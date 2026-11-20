<?php
declare(strict_types=1);
// Phase 3 of media-crop-ratios.plan.md: the renderer on drawn pictures - real Imagick, temporary files, no database.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\CropBox;
use Systopic\System\Media\MasterGeometry;
use Systopic\System\Media\Ratio;
use Systopic\System\Media\VariantRenderer;
use Systopic\System\Media\Variants;
use Systopic\System\Queries\MediaCrops\CropSet;
use Systopic\System\Tables\MediaCrops\Operator;
use Systopic\System\Tables\Medias\Files;
use Systopic\System\Tables\Medias\Model as MediaRow;

if (!class_exists('Imagick')) throw new RuntimeException('Run with the imagick extension: php -d extension=imagick tests/media-variant-render.php');
if (!class_exists('fs')) {
    class fs { public static $siteRoot; public static function toNative($path) { return $path; } }
}

// 300 x 200: left half red, right half blue, a green square in the top left corner.
$draw = static function (int $width = 300, int $height = 200): Imagick {
    $image = new Imagick();
    $image->newImage($width, $height, new ImagickPixel('#ff0000'), 'png');
    $d = new ImagickDraw();
    $d->setFillColor(new ImagickPixel('#0000ff'));
    $d->rectangle($width / 2, 0, $width, $height);
    $d->setFillColor(new ImagickPixel('#00ff00'));
    $d->rectangle(0, 0, $width / 10 - 1, $height / 10 - 1);
    $image->drawImage($d);
    return $image;
};
$at = static function (Imagick $image, int $x, int $y): string {
    $c = $image->getImagePixelColor($x, $y)->getColor(1);
    if ($c['a'] < 0.5) return 'none';
    $rgb = [$c['r'], $c['g'], $c['b']];
    return match (true) {
        $rgb[0] > 0.6 && $rgb[1] < 0.4 && $rgb[2] < 0.4 => 'red',
        $rgb[2] > 0.6 && $rgb[0] < 0.4 && $rgb[1] < 0.4 => 'blue',
        $rgb[1] > 0.6 && $rgb[0] < 0.4 && $rgb[2] < 0.4 => 'green',
        $rgb[0] > 0.9 && $rgb[1] > 0.9 && $rgb[2] > 0.9 => 'white',
        default => 'other(' . implode(',', array_map(static fn($v) => (int) round($v * 255), $rgb)) . ')',
    };
};
$size = static fn(Imagick $image): array => [$image->getImageWidth(), $image->getImageHeight()];
$look = Operator::LOOK;
$original = $draw();
$square = Ratio::of('1:1');

// ----------------------------------------------------------------- master
$whole = VariantRenderer::master($original, MasterGeometry::whole(300, 200), $look);
check('untouched: the master is the original', $size($whole) === [300, 200] && $at($whole, 10, 10) === 'green' && $at($whole, 100, 100) === 'red' && $at($whole, 290, 100) === 'blue');
check('the original itself is not changed by making a master', $size($original) === [300, 200] && $at($original, 10, 10) === 'green');
$turned = VariantRenderer::master($original, new MasterGeometry(300, 200, new CropBox(Ratio::of('2:3')), 90), $look);
check('turned by 90 degrees: upright, the top left corner is now top right', $size($turned) === [200, 300] && $at($turned, 190, 10) === 'green' && $at($turned, 100, 60) === 'red' && $at($turned, 100, 240) === 'blue');
$back = VariantRenderer::master($original, new MasterGeometry(300, 200, new CropBox(Ratio::of('2:3')), -90), $look);
check('turned by -90 degrees: the top left corner is now bottom left', $size($back) === [200, 300] && $at($back, 10, 290) === 'green');
$mirrored = VariantRenderer::master($original, new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2')), 0, true), $look);
check('mirrored: the corner and the halves change sides', $at($mirrored, 290, 10) === 'green' && $at($mirrored, 50, 100) === 'blue' && $at($mirrored, 250, 100) === 'red');
$cut = VariantRenderer::master($original, new MasterGeometry(300, 200, new CropBox($square, scale: 2)), $look);
check('master crop: the middle square, half red, half blue', $size($cut) === [100, 100] && $at($cut, 20, 50) === 'red' && $at($cut, 80, 50) === 'blue' && $at($cut, 2, 2) === 'red');
$left = VariantRenderer::master($original, new MasterGeometry(300, 200, new CropBox($square, 0.0, 0.0, 2)), $look);   // flush top left
check('master crop at the top left: the corner square is in it, all red beside', $size($left) === [100, 100] && $at($left, 5, 5) === 'green' && $at($left, 90, 90) === 'red');
foreach ([10.0, -25.0] as $angle) {
    $geometry = new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2')), $angle);
    $tilted = VariantRenderer::master($original, $geometry, $look);
    $corners = [$at($tilted, 1, 1), $at($tilted, $geometry->width - 2, 1), $at($tilted, 1, $geometry->height - 2), $at($tilted, $geometry->width - 2, $geometry->height - 2)];
    check("turned by $angle at scale 1: no background in any corner", $size($tilted) === [$geometry->width, $geometry->height] && !in_array('none', $corners, true) && $geometry->width < 300);
}
$touching = MasterGeometry::largest(300, 200, 10);
$framed = VariantRenderer::master($original, $touching, $look);
check('turned without a master crop: the largest frame, no background', $size($framed) === [$touching->width, $touching->height]
    && $at($framed, 2, 2) !== 'none' && $at($framed, $touching->width - 3, $touching->height - 3) !== 'none');
$over = VariantRenderer::master($original, new MasterGeometry(300, 200, new CropBox(Ratio::of('3:2'), -0.5, 0.0, 2)), $look);   // half its width over the left edge
check('a master pushed over the edge: transparent where there is no picture', $size($over) === [150, 100] && $at($over, 30, 50) === 'none' && $at($over, 140, 50) === 'red' && $at($over, 80, 5) === 'green');
$fit = VariantRenderer::master($original, new MasterGeometry(300, 200, new CropBox($square, scale: 2 / 3)), $look);
check('fit in: the whole picture in a square, background above and below', $size($fit) === [300, 300] && $at($fit, 150, 10) === 'none' && $at($fit, 100, 150) === 'red' && $at($fit, 200, 150) === 'blue' && $at($fit, 150, 290) === 'none');

// ------------------------------------------------------------------- look
$dark = VariantRenderer::master($original, MasterGeometry::whole(300, 200), ['b' => 50] + $look);
$c = $dark->getImagePixelColor(100, 100)->getColor();
check('brightness 50: red at half', abs($c['r'] - 128) <= 2 && $c['g'] <= 2 && $c['b'] <= 2);
$flat = VariantRenderer::master($original, MasterGeometry::whole(300, 200), ['c' => 50] + $look);
$c = $flat->getImagePixelColor(100, 100)->getColor();
check('contrast 50: towards middle grey, as the CSS filter does', abs($c['r'] - 191) <= 2 && abs($c['g'] - 64) <= 2 && abs($c['b'] - 64) <= 2);
$grey = VariantRenderer::master($original, MasterGeometry::whole(300, 200), ['s' => 0] + $look);
$c = $grey->getImagePixelColor(100, 100)->getColor();
check('saturation 0: no colour left', abs($c['r'] - $c['g']) <= 2 && abs($c['g'] - $c['b']) <= 2);

// -------------------------------------------------------------------- cut
$crop = VariantRenderer::cut($whole, new CropBox($square));
check('crop 1:1, own pixels: the middle square', $size($crop) === [200, 200] && $at($crop, 50, 100) === 'red' && $at($crop, 150, 100) === 'blue' && $at($crop, 2, 2) === 'red');
check('the master is not changed by cutting', $size($whole) === [300, 200]);
$small = VariantRenderer::cut($whole, new CropBox($square), 0.01);
check('crop 1:1 at 0.01 megapixels: 100 x 100', $size($small) === [100, 100] && $at($small, 25, 50) === 'red' && $at($small, 75, 50) === 'blue');
$big = VariantRenderer::cut($whole, new CropBox($square), 5);
check('a size the crop has no pixels for: its own pixels, not blown up', $size($big) === [200, 200]);
$zoom = VariantRenderer::cut($whole, new CropBox(Ratio::of('16:9'), 0.0, 0.5, 2));   // flush left
check('crop 16:9 at scale 2 on the left: all red', $size($zoom) === [150, 84] && $at($zoom, 5, 5) === 'red' && $at($zoom, 145, 80) === 'red');
$beyond = VariantRenderer::cut($whole, new CropBox($square, 2.0, 0.5));   // half of it past the right edge
check('a crop pushed over the edge: transparent where there is no master', $size($beyond) === [200, 200] && $at($beyond, 50, 100) === 'blue' && $at($beyond, 150, 100) === 'none');
$fitIn = VariantRenderer::cut($whole, new CropBox($square, basis: CropBox::FIT));
check('"fit in": the whole master in the square, transparent above and below', $size($fitIn) === [300, 300] && $at($fitIn, 50, 150) === 'red' && $at($fitIn, 250, 150) === 'blue' && $at($fitIn, 150, 20) === 'none');
$aside = VariantRenderer::cut($whole, new CropBox(Ratio::of('3:2'), 0.25, 0.5, basis: CropBox::FIT));
check('"fit" moves where "fill" cannot: the master\'s own ratio, a quarter of the way', $size($aside) === [300, 200] && $at($aside, 50, 100) === 'blue' && $at($aside, 250, 100) === 'none');
$scaled = VariantRenderer::scaled($whole, 0.015);
check('the master at 0.015 megapixels keeps its ratio', $size($scaled) === [150, 100] && $at($scaled, 3, 3) === 'green');
check('scaling never goes up', $size(VariantRenderer::scaled($whole, 10)) === [300, 200]);

// ----------------------------------------------------------------- encode
foreach (['webp' => IMAGETYPE_WEBP, 'jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG] as $type => $expected) {
    $info = getimagesizefromstring(VariantRenderer::encode($crop, $type, 84));
    check("written as $type", $info !== false && $info[2] === $expected && $info[0] === 200 && $info[1] === 200);
}
$jpg = new Imagick();
$jpg->readImageBlob(VariantRenderer::encode($beyond, 'jpg', 90));
check('a jpg has no transparency: white instead', $at($jpg, 150, 100) === 'white' && $at($jpg, 50, 100) === 'blue');
$webp = new Imagick();
$webp->readImageBlob(VariantRenderer::encode($beyond, 'webp', 90));
check('a webp keeps it', $at($webp, 150, 100) === 'none');
rejects('an unknown type is refused', static fn() => VariantRenderer::encode($crop, 'bmp', 80));

// ------------------------------------------------------------ make: files
Variants::configure(['16:9', '1:1', '9:16'], [0.005, 0.02, 1]);
$root = strtr(sys_get_temp_dir(), DIRECTORY_SEPARATOR, '/') . '/fluxo-variant-render-' . bin2hex(random_bytes(5));
mkdir($root . '/var/original/000/42', 0777, true);
fs::$siteRoot = $root . '/';
$originalPath = $root . '/var/original/000/42/bild.png';
$draw(600, 400)->writeImage($originalPath);
$media = new MediaRow();
$media->id = 42;
$media->filename = 'bild.png';
$media->mimetype = 'image/png';
$remove = static function (string $dir) use (&$remove): void {
    foreach (glob($dir . '/*') ?: [] as $file) is_dir($file) ? $remove($file) : unlink($file);
    @rmdir($dir);
};
try {
    check('nothing made yet', Files::variants(42) === []);
    $renderer = new VariantRenderer(new CropSet($media, []), $originalPath);
    $path = $renderer->make(Variants::parse('0.02mp1by1'), 'webp');
    check('a variant is written into the medium\'s one folder, named by its variant', str_replace('\\', '/', $path) === $root . '/var/formats/000/42/bild--0.02mp1by1.webp' && is_file($path));
    check('the master was made on the way and kept', array_keys(Files::variants(42)) === ['bild--0.02mp1by1.webp', 'bild--master.webp']);
    $made = new Imagick($path);
    check('1:1 at 0.02 megapixels: 141 x 141, the middle of the picture', $size($made) === [141, 141] && $at($made, 30, 70) === 'red' && $at($made, 110, 70) === 'blue');
    check('the row learns the original\'s and the master\'s size', $renderer->sizeChanged && $media->width === 600 && $media->height === 400 && $media->masterWidth === 600 && $media->masterHeight === 400);

    $second = new VariantRenderer(new CropSet($media, []), $root . '/var/original/000/42/gone.png');
    $path = $second->make(Variants::parse('16by9'), 'jpg');
    check('the next variant is cut from the master file - the original is not read again', $size(new Imagick($path)) === [600, 338] && !$second->sizeChanged);
    check('the master at a size', $size(new Imagick($second->make(Variants::parse('0.005mp'), 'webp'))) === [87, 58]);
    check('the master itself, as asked', basename($second->make(Variants::parse('master'), 'webp')) === 'bild--master.webp' && $size(new Imagick($root . '/var/formats/000/42/bild--master.webp')) === [600, 400]);
    check('a size above the picture gives its own pixels', $size(new Imagick($second->make(Variants::parse('1mp9by16'), 'webp'))) === [225, 400]);
    $editor = new VariantRenderer(new CropSet($media, []), $originalPath);
    check('the editor\'s copy of the untouched original', $size(new Imagick($editor->make(Variants::parse('original'), 'webp'))) === [600, 400]
        && isset(Files::variants(42)['bild--original.webp']));

    // A crop set by hand and a look, as the editor will store them.
    $hand = Operator::put(null, $media, new CropBox($square, 0.0, 0.5, 2));   // flush left in the upright master
    $master = Operator::withLook(Operator::put(null, $media, new CropBox(Ratio::of('2:3')), master: true), ['r' => 90]);
    Files::removeVariants(42);
    check('a new crop or look removes everything made of the medium', Files::variants(42) === [] && !is_dir($root . '/var/formats/000/42'));
    $edited = new VariantRenderer(new CropSet($media, [$hand, $master]), $originalPath);
    $turnedFile = new Imagick($edited->make(Variants::parse('master'), 'png'));
    check('master turned by 90 degrees: upright, corner top right', $size($turnedFile) === [400, 600] && $at($turnedFile, 380, 20) === 'green' && $media->masterWidth === 400 && $media->masterHeight === 600);
    $handFile = new Imagick($edited->make(Variants::parse('1by1'), 'png'));
    check('the crop set by hand is cut from the turned master: red above, blue below', $size($handFile) === [200, 200] && $at($handFile, 100, 50) === 'red' && $at($handFile, 100, 150) === 'blue');
    $auto = new Imagick($edited->make(Variants::parse('9by16'), 'png'));
    check('a ratio narrower than the master takes its whole height', $size($auto) === [338, 600] && $at($auto, 5, 5) === 'red' && $at($auto, 5, 595) === 'blue');

    Files::rename($media, 'foto.png');
    check('renaming the medium renames what was made of it', isset(Files::variants(42)['foto--master.webp']) && isset(Files::variants(42)['foto--1by1.png']) && !isset(Files::variants(42)['bild--1by1.png']));
    Files::removeFormats($media);
    check('removing the formats of a medium removes its folder', Files::variants(42) === []);
} finally {
    $remove($root);
    Variants::reset();
}
// The look: the arithmetic of the CSS filters the editor previews with - brightness(), contrast(), saturate().
$css = static function (array $rgb, array $look): array {
    [$b, $c, $s] = [$look['b'] / 100, $look['c'] / 100, $look['s'] / 100];
    $clip = static fn(float $v): float => max(0.0, min(1.0, $v));
    $rgb = array_map(static fn($v) => $clip($v / 255 * $b), $rgb);
    $rgb = array_map(static fn($v) => $clip($v * $c + (1 - $c) * 0.5), $rgb);
    [$r, $g, $bl] = $rgb;
    return array_map(static fn($v) => (int) round($clip($v) * 255), [
        (0.213 + 0.787 * $s) * $r + (0.715 - 0.715 * $s) * $g + (0.072 - 0.072 * $s) * $bl,
        (0.213 - 0.213 * $s) * $r + (0.715 + 0.285 * $s) * $g + (0.072 - 0.072 * $s) * $bl,
        (0.213 - 0.213 * $s) * $r + (0.715 - 0.715 * $s) * $g + (0.072 + 0.928 * $s) * $bl,
    ]);
};
foreach ([['b' => 100, 'c' => 161, 's' => 100], ['b' => 97, 'c' => 100, 's' => 0], ['b' => 120, 'c' => 80, 's' => 150], ['b' => 60, 'c' => 140, 's' => 40]] as $look) {
    foreach ([[200, 100, 50], [250, 240, 230], [20, 60, 200]] as $rgb) {
        $pixel = new \Imagick();
        $pixel->newImage(1, 1, new \ImagickPixel('rgb(' . implode(',', $rgb) . ')'));
        VariantRenderer::levels($pixel, $look);
        $got = $pixel->getImagePixelColor(0, 0)->getColor();
        $want = $css($rgb, $look);
        check('the look as the editor shows it: ' . json_encode($look) . ' on ' . implode(',', $rgb) . ' -> ' . implode(',', $want),
            abs($got['r'] - $want[0]) <= 1 && abs($got['g'] - $want[1]) <= 1 && abs($got['b'] - $want[2]) <= 1);
    }
}
echo "media variant render: all checks passed\n";
