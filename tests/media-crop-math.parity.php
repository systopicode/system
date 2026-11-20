<?php
declare(strict_types=1);
// Helper of tests/media-crop-math.mjs: the server's answers for a list of masters and crops, as JSON.
// Reads the list from STDIN, writes the results to STDOUT - the browser's arithmetic must give the same.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Media\CropBox;
use Systopic\System\Media\MasterGeometry;
use Systopic\System\Media\Ratio;

$out = [];
foreach (json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR) as $case) {
    $ratio = Ratio::of($case['ratio']);
    $crop = new CropBox($ratio, $case['ax'], $case['ay'], $case['scale'], basis: $case['basis'] ?? CropBox::FILL);
    $g = new MasterGeometry($case['width'], $case['height'], $crop, $case['angle'], $case['flip']);
    $largest = MasterGeometry::largest($case['width'], $case['height'], $case['angle'], $case['flip']);
    $out[] = [
        'width' => $g->width,
        'height' => $g->height,
        'reference' => [$g->referenceWidth, $g->referenceHeight],
        'toMaster' => $g->toMaster($case['px'], $case['py']),
        'toOriginal' => $g->toOriginal($case['px'], $case['py']),
        'inside' => $g->insideOriginal(),
        'fitScale' => $g->fitScale(),
        'largest' => [$largest->width, $largest->height, $largest->crop->ratio->name],
        'rect' => $crop->rect($case['width'], $case['height']),
        'cropInside' => $crop->inside($case['width'], $case['height']),
        'clamped' => (static fn(CropBox $c): array => [$c->alignX, $c->alignY, $c->scale])($crop->clamped($case['width'], $case['height'])),
        'centre' => [$g->centreFractionX, $g->centreFractionY],
        'cropFit' => CropBox::fitScale($ratio, $case['width'], $case['height']),
        'asFit' => (static fn(CropBox $c): array => [$c->alignX, $c->alignY, $c->scale])($crop->asFit($case['width'], $case['height'])),
    ];
}
echo json_encode($out);
