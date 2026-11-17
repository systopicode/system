<?php
declare(strict_types=1);
// Phase 5 of media-crop-ratios.plan.md: what a page writes for a picture - addresses, srcset, sizes, width and height,
// the preview of a picture whose files are still to be made, and the picture that fits its box.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\CropBox;
use Systopic\System\Media\Ratio;
use Systopic\System\Media\Variants;
use Systopic\System\Queries\MediaCrops\CropSet;
use Systopic\System\Tables\MediaCrops\Operator;
use Systopic\System\Tables\Medias\Model as MediaRow;
use Systopic\System\Tables\Medias\Picture;

if (!class_exists('fs')) {
    class fs { public static $siteRoot; public static function toNative($path) { return $path; } }
}
if (!class_exists('http')) {
    class http { public static $root = '/site/'; }
}
$root = strtr(sys_get_temp_dir(), DIRECTORY_SEPARATOR, '/') . '/fluxo-picture-' . bin2hex(random_bytes(5));
$folder = $root . '/var/formats/000/42';
mkdir($folder, 0777, true);
fs::$siteRoot = $root . '/';
Variants::reset();

$media = new MediaRow();
$media->id = 42;
$media->filename = 'bild.jpg';
$media->mimetype = 'image/jpeg';
$media->label = 'Ein "Bild" <b>';
$media->width = 3000;
$media->height = 2000;
$attr = static function (string $html, string $name): ?string {
    return preg_match('~\s' . preg_quote($name, '~') . '="([^"]*)"~', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : null;
};
// Addresses without their version, to compare them as written.
$bare = static fn(?string $text): string => (string) preg_replace('~\?v=[0-9a-f]+~', '', (string) $text);
$make = static function (string ...$names) use ($folder): void {
    foreach ($names as $name) file_put_contents("$folder/$name", 'x');
    Picture::forgetFiles();
};
$remove = static function (string $dir) use (&$remove): void {
    foreach (glob($dir . '/*') ?: [] as $file) is_dir($file) ? $remove($file) : unlink($file);
    @rmdir($dir);
};
try {
    $set = new CropSet($media, []);
    $v = $set->version();
    check('a medium has a version for its addresses', preg_match('~^[0-9a-f]+$~', $v) === 1);

    // ------------------------------------------------------------- sizes
    $wide = new Picture($set, Ratio::of('16:9'));
    check('16:9 of a 3000 x 2000 picture: its own pixels', $wide->width === 3000 && $wide->height === 1688);
    $sources = $wide->sources();
    check('every declared size the crop has pixels for, small to large', array_column($sources, 'megapixels') === [0.5, 1.0, 2.0, 5.0]);
    check('with the width each will have - the renderer\'s own arithmetic', array_column($sources, 'width') === [943, 1333, 1886, 2981] && $sources[0]['height'] === 530);
    check('addresses in the medium\'s one folder, with its version', $sources[0]['url'] === "/site/var/formats/000/42/bild--0.5mp16by9.webp?v=$v" && $bare($sources[3]['url']) === '/site/var/formats/000/42/bild--5mp16by9.webp');
    check('none of them is made yet', array_column($sources, 'made') === [false, false, false, false]);
    $square = new Picture($set, Ratio::of('1:1'));
    check('1:1 has 4 megapixels: sizes up to 2, and its own pixels on top', array_column($square->sources(), 'megapixels') === [0.5, 1.0, 2.0, null]
        && $square->sources()[3]['width'] === 2000 && $bare($square->sources()[3]['url']) === '/site/var/formats/000/42/bild--1by1.webp');
    $master = new Picture($set);
    check('no ratio: the master', $master->width === 3000 && $master->height === 2000 && $bare($master->sources()[1]['url']) === '/site/var/formats/000/42/bild--1mp.webp'
        && $master->sources()[1]['width'] === 1225 && $master->sources()[1]['height'] === 816);
    check('the master\'s own pixels are not listed when a size is close to them', array_column($master->sources(), 'megapixels') === [0.5, 1.0, 2.0, 5.0]);

    // ------------------------------------------------------------ getUrl
    check('one address at a size', $bare($wide->getUrl(2)) === '/site/var/formats/000/42/bild--2mp16by9.webp');
    check('a size that is not declared goes to the next that holds it', $bare($wide->getUrl(1.5)) === '/site/var/formats/000/42/bild--2mp16by9.webp' && $bare($wide->getUrl(40)) === '/site/var/formats/000/42/bild--10mp16by9.webp');
    check('no size: about one megapixel', $bare($wide->getUrl()) === '/site/var/formats/000/42/bild--1mp16by9.webp');
    check('another type', $bare($wide->getUrl(1, 'jpg')) === '/site/var/formats/000/42/bild--1mp16by9.jpg');

    // ------------------------------------------------ a finished picture
    $make('bild--1mp16by9.webp', 'bild--1mp.webp', 'bild--1mp1by1.webp', 'bild--1mp4by3.webp');
    $html = $wide->toImgHtml();
    check('an <img> with a fallback of about one megapixel', str_starts_with($html, '<img ') && $attr($html, 'src') === "/site/var/formats/000/42/bild--1mp16by9.webp?v=$v");
    check('srcset lists every size with its width', $bare($attr($html, 'srcset')) === implode(', ', [
        '/site/var/formats/000/42/bild--0.5mp16by9.webp 943w', '/site/var/formats/000/42/bild--1mp16by9.webp 1333w',
        '/site/var/formats/000/42/bild--2mp16by9.webp 1886w', '/site/var/formats/000/42/bild--5mp16by9.webp 2981w']));
    check('the browser measures the box itself', $attr($html, 'sizes') === 'auto, 100vw' && $attr($html, 'loading') === 'lazy');
    check('width and height of the crop, so the page does not jump', $attr($html, 'width') === '3000' && $attr($html, 'height') === '1688');
    check('the label is the alt text, escaped', $attr($html, 'alt') === 'Ein "Bild" <b>' && !str_contains($html, '<b>') && str_contains($html, '&quot;Bild&quot;'));
    check('the ratio is a class', $attr($html, 'class') === 'ratio-16by9' && $attr($master->toImgHtml(), 'class') === 'master');
    check('a finished picture needs no loader', !str_contains($html, 'data-media') && !str_contains($html, 'style='));
    $own = $wide->toImgHtml(['class' => 'hero', 'sizes' => '(min-width: 900px) 50vw, 100vw', 'loading' => 'eager', 'data-x' => '1', 'alt' => '']);
    check('a template adds classes and says what it knows about its layout', $attr($own, 'class') === 'ratio-16by9 hero' && $attr($own, 'sizes') === '(min-width: 900px) 50vw, 100vw'
        && $attr($own, 'loading') === 'eager' && $attr($own, 'data-x') === '1');
    check('an empty alt stays in the tag (decorative picture)', str_contains($own, ' alt=""'));
    check('null leaves an attribute out', !str_contains($wide->toImgHtml(['loading' => null]), 'loading='));

    // ----------------------------------------------------------- <picture>
    $picture = $wide->toPictureHtml(['(max-width: 600px)' => '1:1', '(max-width: 900px)' => '4:3'], ['class' => 'hero']);
    check('<picture>: a source per media query, then the <img>', preg_match('~^<picture><source [^>]+><source [^>]+><img [^>]+></picture>$~', $picture) === 1);
    preg_match_all('~<source [^>]+>~', $picture, $m);
    check('the first source is the square for a phone', $attr($m[0][0], 'media') === '(max-width: 600px)' && str_contains($bare($attr($m[0][0], 'srcset')), 'bild--0.5mp1by1.webp 707w')
        && $attr($m[0][0], 'width') === '2000' && $attr($m[0][0], 'height') === '2000');
    check('the second is 4:3', str_contains((string) $attr($m[0][1], 'srcset'), 'bild--1mp4by3.webp'));
    check('the <img> is the wide crop, with the template\'s class', str_contains($picture, 'bild--1mp16by9.webp') && str_contains($picture, 'class="ratio-16by9 hero"'));

    // ------------------------------------------- a picture still to be made
    $tall = new Picture($set, Ratio::of('9:16'));
    $pending = $tall->toImgHtml(['class' => 'teaser']);
    check('not made yet: a placeholder of the right shape instead of an address', str_starts_with((string) $attr($pending, 'src'), 'data:image/svg+xml,') && str_contains((string) $attr($pending, 'src'), "viewBox='0 0 1125 2000'")
        && $attr($pending, 'width') === '1125' && $attr($pending, 'height') === '2000');
    check('the real addresses wait for the loader', $bare($attr($pending, 'data-media-src')) === '/site/var/formats/000/42/bild--1mp9by16.webp'
        && str_contains($bare($attr($pending, 'data-media-srcset')), 'bild--0.5mp9by16.webp 530w') && !str_contains($pending, ' srcset='));
    check('no small master yet: no preview either', !str_contains($pending, 'style=') && $attr($pending, 'class') === 'ratio-9by16 teaser');
    $make('bild--0.5mp.webp');
    $pending = $tall->toImgHtml(['style' => 'border:0']);
    check('with the small master: it is the preview, cut to the crop by CSS', $bare($attr($pending, 'style')) === "background:url('/site/var/formats/000/42/bild--0.5mp.webp') 50% 50% / 266.667% 100% no-repeat;border:0");
    $left = Operator::put(null, $media, new CropBox(Ratio::of('9:16'), 0.25, 0.5, 2));
    $moved = new Picture(new CropSet($media, [$left]), Ratio::of('9:16'));
    // The crop is 0.1875 wide and 0.5 high, its left edge at 0.15625, its top at 0.25.
    check('a crop set by hand moves and sizes the preview', str_contains($bare($attr($moved->toImgHtml(), 'style')), ') 19.231% 50% / 533.333% 200% no-repeat'));
    $unmade = new Picture(new CropSet($media, []), Ratio::of('2:1'));
    check('the preview is the same file for every ratio - nothing is rendered for it', str_contains((string) $attr($unmade->toImgHtml(), 'style'), 'bild--0.5mp.webp'));

    // ----------------------------------------------------- version changes
    check('a crop set by hand is a new version: new addresses', (new CropSet($media, [$left]))->version() !== $v);
    $again = Operator::put($left, $media, new CropBox(Ratio::of('9:16'), 0.3, 0.5, 2));
    check('saving it again is another one', (new CropSet($media, [$again]))->version() !== $v);
    $media->pois = new \Systopic\Db\Type\Text('[{"x":0.2,"y":0.2}]');
    check('a point of interest too', (new CropSet($media, []))->version() !== $v);
    $media->pois = null;
    check('and nothing changed is the same version', (new CropSet($media, []))->version() === $v);

    // ---------------------------------------------------- crops and master
    $hand = Operator::put(null, $media, new CropBox(Ratio::of('16:9'), 0.5, 0.5, 2));
    $zoomed = new Picture(new CropSet($media, [$hand]), Ratio::of('16:9'));
    check('a crop set by hand at scale 2 has a quarter of the pixels - fewer sizes', $zoomed->width === 1500 && $zoomed->height === 844
        && array_column($zoomed->sources(), 'megapixels') === [0.5, 1.0] && $attr($zoomed->toImgHtml(), 'width') === '1500');
    $media->masterWidth = 2000;
    $media->masterHeight = 3000;
    $turned = new Picture(new CropSet($media, []), Ratio::of('1:1'));
    check('the master as it was last made decides (here: turned upright)', $turned->width === 2000 && $turned->height === 2000 && (new Picture(new CropSet($media, [])))->height === 3000);
    $media->masterWidth = $media->masterHeight = 0;

    // ------------------------------------------------- the picture that fits
    $auto = (new Picture($set))->toAutoHtml(['class' => 'cover', 'style' => 'width:100%;height:40vh']);
    $data = json_decode((string) $attr($auto, 'data-media-auto'), true);
    check('fits its box: shows the small master until the loader has measured', $bare($attr($auto, 'src')) === '/site/var/formats/000/42/bild--0.5mp.webp'
        && $attr($auto, 'class') === 'media-auto cover' && $attr($auto, 'style') === 'object-fit:cover;width:100%;height:40vh');
    check('the loader gets where the files are, their version, the sizes', $data['base'] === '/site/var/formats/000/42/bild--' && $data['type'] === 'webp' && $data['v'] === $v && $data['sizes'] === [0.5, 1, 2, 5, 10]);
    check('and every declared ratio with the pixels its crop has', array_keys($data['ratios']) === array_map(static fn($r) => $r->suffix, array_values(Variants::ratios()))
        && $data['ratios']['16by9'] === [16, 9, 3000] && $data['ratios']['1by1'] === [1, 1, 2000] && $data['ratios']['9by16'] === [9, 16, 1125]);

    // -------------------------------------------------------- special media
    $tiny = new MediaRow();
    $tiny->id = 7; $tiny->filename = 'icon.png'; $tiny->mimetype = 'image/png'; $tiny->label = 'Icon'; $tiny->width = 300; $tiny->height = 200;
    $small = new Picture(new CropSet($tiny, []), Ratio::of('1:1'));
    check('a tiny picture: one source, its own pixels, never blown up', count($small->sources()) === 1 && $small->sources()[0]['width'] === 200 && $small->sources()[0]['megapixels'] === 0.5
        && $bare($small->sources()[0]['url']) === '/site/var/formats/000/07/icon--0.5mp1by1.webp' && $attr($small->toImgHtml(), 'width') === '200');
    $unknown = new MediaRow();
    $unknown->id = 8; $unknown->filename = 'doc.pdf'; $unknown->mimetype = 'application/pdf'; $unknown->label = 'Doc';
    $pdf = new Picture(new CropSet($unknown, []), Ratio::of('3:4'));
    check('size not known yet: the preview size on request, no width and height, no placeholder', $pdf->width === 0 && $bare($attr($pdf->toImgHtml(), 'src')) === '/site/var/formats/000/08/doc--0.5mp3by4.webp'
        && !str_contains($pdf->toImgHtml(), 'width=') && !str_contains($pdf->toImgHtml(), 'srcset=') && !str_contains($pdf->toImgHtml(), 'data-media'));
    check('size not known yet: fitting the box is not possible, the master instead', !str_contains($pdf->toAutoHtml(), 'data-media-auto'));
    $vector = new MediaRow();
    $vector->id = 9; $vector->filename = 'logo.svg'; $vector->mimetype = 'image/svg+xml'; $vector->label = 'Logo';
    $svg = new Picture(new CropSet($vector, []), Ratio::of('1:1'));
    check('an svg is its original: no variants', !$svg->cut && $svg->sources() === [] && $svg->getUrl(2) === '/site/var/original/000/09/logo.svg'
        && $attr($svg->toImgHtml(), 'src') === '/site/var/original/000/09/logo.svg' && !str_contains($svg->toImgHtml(), 'srcset='));
    check('<picture> of an svg is just its <img>', $svg->toPictureHtml(['(max-width: 600px)' => '1:1']) === '<picture>' . $svg->toImgHtml() . '</picture>');
} finally {
    $remove($root);
    Variants::reset();
    Picture::forgetFiles();
}
echo "media picture: all checks passed\n";
