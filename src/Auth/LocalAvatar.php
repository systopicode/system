<?php

declare(strict_types=1);
namespace Systopic\System\Auth;

use Systopic\System\Vendor\DiceBear\Avatar;
use Systopic\System\Vendor\DiceBear\Style;

/** Restricted local renderer; never accepts uploaded styles or arbitrary options. */
final class LocalAvatar
{
    private const DATA = __DIR__ . '/../../resources/avatars/dicebear/';
    private static ?array $manifest = null;
    /** @var array<string, Style> */
    private static array $styles = [];

    public static function seed(int $userId, ?string $email, bool $screen): string
    {
        $email = strtolower(trim((string) $email));
        return hash('sha256', $screen ? 'screen:' . $userId : ($email !== '' ? $email : 'user:' . $userId));
    }

    /** Changes with seed, style build, renderer adaptation or theme. */
    public static function fingerprint(string $seed, bool $screen, array $colors): string
    {
        self::validate($seed, $colors);
        $manifest = self::manifest();
        $style = $screen ? 'bottts' : 'notionists';
        return substr(hash('sha256', json_encode([
            $manifest['version'], $manifest['adaptation'], ($screen ? 'screen-theme-8' : 'person-theme-4'), $manifest['packages']['core']['sha256'],
            $manifest['styles'][$style]['sha256'], $seed, [$colors['ink'], $colors['background'], $colors['paper']],
            self::options($seed, $screen, $colors),
        ], JSON_THROW_ON_ERROR)), 0, 24);
    }

    /** Only a SHA-256 seed and three six-digit theme colours enter the renderer. */
    public static function render(string $seed, bool $screen, array $colors): string
    {
        self::validate($seed, $colors);
        $name = $screen ? 'bottts' : 'notionists';
        if (!isset(self::$styles[$name])) {
            $spec = self::manifest()['styles'][$name];
            $compressed = @file_get_contents(self::DATA . $name . '.json.gz', false, null, 0, 1_000_001);
            if (!is_string($compressed) || !hash_equals($spec['sha256'], hash('sha256', $compressed))) {
                throw new \RuntimeException('Local avatar definition is missing or damaged.');
            }
            $definition = @gzdecode($compressed, 1_000_000);
            if (!is_string($definition) || !hash_equals($spec['decodedSha256'], hash('sha256', $definition))) {
                throw new \RuntimeException('Local avatar definition could not be decoded.');
            }
            self::$styles[$name] = Style::fromJson($definition);
        }
        $options = self::options($seed, $screen, $colors);
        $svg = (string) new Avatar(self::$styles[$name], $options);
        if ($screen) {
            $colors = array_replace($colors, self::screenPalette($colors));
            // Keep the light robot shell; use dark theme details for legible faces.
            // Keep luminance masks white; recolouring a mask would fade the shell.
            $svg = preg_replace_callback('~<mask\b[^>]*>.*?</mask>(*SKIP)(*F)|\b(fill|stroke)="(?:black|white)"~s', static fn(array $m): string => $m[1] . '="#' . $colors['ink'] . '"', $svg);
            $svg = preg_replace_callback('/(?<=["\'])#(?:[a-fA-F0-9]{6}|[a-fA-F0-9]{3})(?=["\'])/', static function (array $match) use ($colors): string {
                $hex = strtolower(substr($match[0], 1));
                if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                if (in_array($hex, array_map('strtolower', $colors), true)) return '#' . $hex;
                return '#' . (hexdec(substr($hex,0,2))*.2126 + hexdec(substr($hex,2,2))*.7152 + hexdec(substr($hex,4,2))*.0722 > 180 ? $colors['paper'] : $colors['ink']);
            }, $svg);
        }
        if ($screen) {
            // Colour the original antenna component with the theme accent.
            $xml = new \DOMDocument();
            $xml->loadXML($svg, LIBXML_NONET);
            $xpath = new \DOMXPath($xml);
            foreach ($xpath->query('//*[local-name()="g" and starts-with(@id,"top-")]//*[@fill]') as $element) {
                if ($element->getAttribute('fill') === '#' . $colors['body']) $element->setAttribute('fill', '#' . $colors['paper']);
            }
            $svg = $xml->saveXML($xml->documentElement);
        }
        if (!self::validSvg($svg)) throw new \RuntimeException('Local avatar output is invalid.');
        return $svg;
    }

    /** This is also the option surface used by the upstream comparison test. */
    public static function options(string $seed, bool $screen, array $colors): array
    {
        self::validate($seed, $colors);
        $options = ['seed' => $seed, 'size' => 128, 'idRandomization' => false, 'backgroundColor' => [$colors['background']]];
        if ($screen) {
            $options['backgroundColor'] = [self::screenPalette($colors)['background']];
            $options['baseColor'] = [self::screenPalette($colors)['body']];
            // DiceBear 10 reads component choices through the Variant option keys.
            $options['eyesVariant'] = ['happy', 'round'];
            $options['mouthVariant'] = ['smile01'];
            $options['topVariant'] = ['antenna', 'antennaCrooked', 'bulb01', 'horns', 'pyramid', 'radar'];
            $options['textureProbability'] = 0;
        }
        else {
            $options['scale'] = [1.12];
            $options['translateY'] = [-2];
            $options['inkColor'] = [$colors['ink']];
            $options['paperColor'] = [$colors['paper']];
        }
        return $options;
    }

    /** Same HSL rule as --cmsFrameText: main hue, 55% saturation, 77% lightness. */
    private static function screenPalette(array $colors): array
    {
        static $cache = [];
        $key = strtolower($colors['ink'] . $colors['paper'] . $colors['background']);
        if (isset($cache[$key])) return $cache[$key];
        [$r, $g, $b] = array_map(static fn(string $v): float => hexdec($v) / 255, str_split($colors['ink'], 2));
        $max = max($r, $g, $b); $min = min($r, $g, $b); $delta = $max - $min;
        $lightness = ($max + $min) / 2;
        $saturation = $delta == 0 ? 0 : $delta / (1 - abs(2 * $lightness - 1));
        $hue = $delta == 0 ? 0 : 60 * match ($max) {
            $r => fmod(($g - $b) / $delta, 6),
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };
        $hue = fmod($hue + 360, 360);
        $chroma = (1 - abs(2 * .77 - 1)) * $saturation * .55;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        [$r, $g, $b] = match ((int) floor($hue / 60)) {
            0 => [$chroma, $x, 0], 1 => [$x, $chroma, 0], 2 => [0, $chroma, $x],
            3 => [0, $x, $chroma], 4 => [$x, 0, $chroma], default => [$chroma, 0, $x],
        };
        $offset = .77 - $chroma / 2;
        $body = implode('', array_map(static fn(float $v): string => sprintf('%02x', (int) round(($v + $offset) * 255)), [$r, $g, $b]));
        $contrast = \Systopic\System\Settings\Contrast::ratio(...);
        $ink = $colors['ink'];
        if ($contrast($ink, $body) < 4.5) $ink = $contrast('000000', $body) >= $contrast('ffffff', $body) ? '000000' : 'ffffff';
        return $cache[$key] = ['body' => $body, 'ink' => $ink, 'background' => $colors['background']];
    }

    public static function validSvg(string $svg): bool
    {
        if ($svg === '' || strlen($svg) > 1_000_000 || stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) return false;
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new \DOMDocument();
            return $doc->loadXML($svg, LIBXML_NONET)
                && $doc->documentElement?->localName === 'svg'
                && $doc->documentElement?->namespaceURI === 'http://www.w3.org/2000/svg'
                && $doc->getElementsByTagName('script')->length === 0;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private static function validate(string $seed, array $colors): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $seed)) throw new \InvalidArgumentException('Expected a local avatar digest.');
        if (count($colors) !== 3 || !isset($colors['ink'], $colors['background'], $colors['paper'])) throw new \InvalidArgumentException('Expected the three theme colours.');
        foreach ($colors as $value) if (!is_string($value) || !preg_match('/^[a-f0-9]{6}$/iD', $value)) throw new \InvalidArgumentException('Invalid avatar colour.');
    }

    private static function manifest(): array
    {
        if (self::$manifest !== null) return self::$manifest;
        $raw = @file_get_contents(self::DATA . 'manifest.json');
        if (!is_string($raw)) throw new \RuntimeException('Local avatar manifest is unavailable.');
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['version'], $data['adaptation'], $data['packages']['core']['sha256'])) {
            throw new \RuntimeException('Invalid local avatar manifest.');
        }
        foreach (['notionists', 'bottts'] as $name) {
            if (!isset($data['styles'][$name]['sha256'], $data['styles'][$name]['decodedSha256'])) throw new \RuntimeException('Invalid local avatar style manifest.');
        }
        return self::$manifest = $data;
    }
}
