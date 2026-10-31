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
            $manifest['version'], $manifest['adaptation'], $manifest['packages']['core']['sha256'],
            $manifest['styles'][$style]['sha256'], $seed, [$colors['ink'], $colors['background'], $colors['paper']],
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
        if (!self::validSvg($svg)) throw new \RuntimeException('Local avatar output is invalid.');
        return $svg;
    }

    /** This is also the option surface used by the upstream comparison test. */
    public static function options(string $seed, bool $screen, array $colors): array
    {
        self::validate($seed, $colors);
        $options = ['seed' => $seed, 'size' => 128, 'idRandomization' => false, 'backgroundColor' => [$colors['background']]];
        if ($screen) $options['baseColor'] = [$colors['paper']];
        else {
            $options['inkColor'] = [$colors['ink']];
            $options['paperColor'] = [$colors['paper']];
        }
        return $options;
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
