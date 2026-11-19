<?php
declare(strict_types=1);
// Ratios, sizes and format names come from config.json (packages.system.media), the media groups of
// cms from packages.cms.media - Media Settings (media.settings.json) and their panel are gone.
require __DIR__ . '/bootstrap.php';
use Systopic\System\Config\Config;
use Systopic\System\Config\Schema;
use Systopic\System\Media\Variants;
require_once dirname(__DIR__) . '/lib/class/media/format/mediaFormats.php';

$system = json_decode((string) file_get_contents(dirname(__DIR__) . '/config/config.schema.json'), true, 512, JSON_THROW_ON_ERROR);
$media = $system['properties']['media'];
check('the schema has the lists of the code as its defaults', $media['properties']['ratios']['default'] === Variants::RATIOS
    && $media['properties']['sizes']['default'] === Variants::SIZES);
check('ratios, sizes and format names belong to the project', ($media['x-project'] ?? false) === true);
check('video formats and providers are the constants they were', $media['properties']['videoFormats']['x-constant'] === 'VIDEO_FORMATS'
    && $media['properties']['external']['x-constant'] === 'EXTERNAL_MEDIAS');
$cms = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/cms/config/config.schema.json'), true, 512, JSON_THROW_ON_ERROR);
check('the media groups are a block of cms', isset($cms['properties']['media']['properties']['groups']['additionalProperties']['properties']['file_extension']));

$dir = sys_get_temp_dir() . '/media-variants-' . bin2hex(random_bytes(4));
mkdir($dir);
$write = static fn(string $name, array $data) => file_put_contents("$dir/$name", json_encode($data));
try {
    // without a config: the lists of the code
    Variants::configure([], []);
    check('without a config the lists of the code', array_keys(Variants::ratios()) === Variants::RATIOS && Variants::sizes() === array_map('floatval', Variants::SIZES));

    $write('config.json', ['packages' => [
        'system' => ['media' => ['ratios' => ['9:16', '32:18', '1:1', 'nonsense'], 'sizes' => '2', 'aliases' => [
            'hero' => ['megapixels' => 2, 'aspect_ratio' => '16:9'],
            'portrait' => ['width' => 10, 'height' => 10],
        ]]],
        'cms' => ['media' => ['groups' => ['main' => ['label' => 'Main', 'file_extension' => 'jpg, png']]]],
    ]]);
    $write('config.local.json', ['packages' => ['system' => ['instance' => 'DEV.test']]]);
    $config = Config::boot($dir, [], $dir);
    Variants::configure([], []);
    check('the ratios of config.json, wide to narrow, reduced, what does not read left out', array_keys(Variants::ratios()) === ['16:9', '1:1', '9:16']);
    check('one size typed alone is a list of one', Variants::sizes() === [2.0]);
    Variants::addAliases();
    $formats = mediaFormats::get();
    check('a format name of config.json is an alias for the templates, offered in the page editor', ($formats['hero']['aspect_ratio'] ?? null) === '16:9'
        && ($formats['hero']['cms_display'] ?? null) === 'image');
    check('a name of the system is not taken over', $formats['portrait']['width'] === 500);
    $alias = Variants::alias($formats['hero']);
    check('the alias stands for the nearest declared ratio and size', $alias['ratio']?->name === '16:9' && $alias['megapixels'] === 2.0);
    check('the media groups of cms', $config->package('cms')['media']['groups']['main']['file_extension'] === 'jpg, png');

    $write('config.json', ['packages' => []]);
    Config::boot($dir, [], $dir);
    Variants::configure([], []);
    check('config.json without media: the defaults of the schema', array_keys(Variants::ratios()) === Variants::RATIOS && Variants::sizes() === array_map('floatval', Variants::SIZES));
    check('the schema fills them in', Schema::withDefaults('system', [], 'DEV')['media']['ratios'] === Variants::RATIOS);
} finally {
    array_map('unlink', glob("$dir/*") ?: []);
    rmdir($dir);
    Variants::configure([], []);
}
echo "media variants settings: all checks passed\n";
