<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Sys\Theme;

/**
 * The four theme colours a project may set. Written to
 * `overrides/css/zz_system_theme.css`, which sorts after the other overrides,
 * so a save wins without touching the rest of that CSS.
 */
final class ThemeFile
{
    /** @var array<string, string> */
    public const FIELDS = [
        'cmsMainColor' => 'Main',
        'cmsHighlightColor' => 'Highlight',
        'cmsBgColor' => 'Background',
        'cmsTextColor' => 'Text',
    ];

    public static function path(): string
    {
        return rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/css/zz_system_theme.css';
    }

    public static function save(string $key, string $raw): bool
    {
        if (!isset(self::FIELDS[$key])) {
            return false;
        }
        $hex = Theme::normalize($raw);
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return false;
        }
        $values = [];
        foreach (self::FIELDS as $name => $label) {
            $values[$name] = $name === $key ? $hex : Theme::color($name);
        }
        return self::writeColors($values);
    }

    public static function resetColors(): bool
    {
        $values = [];
        foreach (self::FIELDS as $name => $label) {
            $values[$name] = Theme::defaultColor($name);
        }
        return self::writeColors($values);
    }

    private static function writeColors(array $values): bool
    {
        $css = "/* System theme, written from Settings → Theme. */\n:root {\n";
        foreach ($values as $name => $value) {
            $css .= "\t--$name: #$value;\n";
        }
        $css .= "}\n";
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $temp = $path . '.' . getmypid() . '.tmp';
        if (file_put_contents($temp, $css) === false) {
            return false;
        }
        if (is_file($path)) {
            $perms = fileperms($path);
            if ($perms !== false) {
                chmod($temp, $perms & 0777);
            }
        }
        if (!rename($temp, $path)) {
            @unlink($temp);
            return false;
        }
        $legacy = dirname($path) . '/zz_cms_theme.css';
        if (is_file($legacy)) {
            @unlink($legacy);
        }
        Theme::flush();
        return true;
    }

    /** The file the page preview parses. The old names are only a fallback. */
    public static function cssSource(): ?string
    {
        self::migratePagesFile();
        return self::pagesStylesheet();
    }

    public static function cssPath(): string
    {
        return self::overridesDir() . '/pages_overrides.css';
    }

    /** CSS for the CMS, loaded as a stylesheet. The public site does not get it, and it is not parsed into the page preview. */
    public static function systemPath(): string
    {
        return self::overridesDir() . '/system_overrides.css';
    }

    public static function pagesPanelPresent(): bool
    {
        return class_exists(\Systopic\Cms\Panels\Root\Cms\Pages\Panel::class)
            || is_dir(rtrim((string) \fs::$projectRoot, '/\\') . '/panels/cms/010_pages');
    }

    /**
     * The theme editor used to store page CSS as system_overrides.css or
     * cms_overrides.css. That file is the page preview stylesheet. Once
     * pages_overrides.css exists, system_overrides.css is the other file.
     */
    public static function migratePagesFile(): void
    {
        $pages = self::cssPath();
        if (is_file($pages)) {
            return;
        }
        $dir = self::overridesDir();
        foreach (['system_overrides.css', 'cms_overrides.css'] as $legacy) {
            $from = $dir . '/' . $legacy;
            if (!is_file($from)) {
                continue;
            }
            if (@rename($from, $pages)) {
                return;
            }
        }
    }

    public static function pagesStylesheet(): ?string
    {
        $pages = self::cssPath();
        if (is_file($pages)) {
            return $pages;
        }
        $dir = self::overridesDir();
        foreach (['cms_overrides.css', 'system_overrides.css'] as $legacy) {
            if (is_file($dir . '/' . $legacy)) {
                return $dir . '/' . $legacy;
            }
        }
        return null;
    }

    /** The unparsed file. Absent until it is saved. A leftover of the old name is the page file and is renamed first. */
    public static function systemStylesheet(): ?string
    {
        self::migratePagesFile();
        $path = self::systemPath();
        return is_file($path) ? $path : null;
    }

    public static function readCss(): string
    {
        return self::readFile(self::pagesStylesheet());
    }

    public static function readSystemCss(): string
    {
        return self::readFile(self::systemStylesheet());
    }

    public static function saveCss(string $css): bool
    {
        return self::writeCss(self::cssPath(), $css, true);
    }

    public static function saveSystemCss(string $css): bool
    {
        self::migratePagesFile();
        if (!is_file(self::cssPath())) {
            self::writeCss(self::cssPath(), "/* Page preview. Parsed into the page. */\n", false);
        }
        return self::writeCss(self::systemPath(), $css, false);
    }

    private static function overridesDir(): string
    {
        return rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/css';
    }

    private static function readFile(?string $path): string
    {
        if ($path === null || !is_file($path)) {
            return '';
        }
        $text = file_get_contents($path);
        return is_string($text) ? $text : '';
    }

    private static function writeCss(string $path, string $css, bool $dropLegacy): bool
    {
        if (strlen($css) > 200000 || preg_match('/<\s*\/\s*style\b|<\s*script\b/i', $css) === 1) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $text = rtrim(str_replace("\r\n", "\n", $css), "\n") . "\n";
        $temp = $path . '.' . getmypid() . '.tmp';
        if (file_put_contents($temp, $text) === false) {
            return false;
        }
        if (is_file($path)) {
            $perms = fileperms($path);
            if ($perms !== false) {
                chmod($temp, $perms & 0777);
            }
        }
        if (!rename($temp, $path)) {
            @unlink($temp);
            return false;
        }
        if ($dropLegacy) {
            $legacy = $dir . '/cms_overrides.css';
            if (is_file($legacy) && $legacy !== $path) {
                @unlink($legacy);
            }
        }
        Theme::flush();
        return true;
    }

    public static function render(\Systopic\System\Panels\PanelNode $panel): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $main = '#' . Theme::color('cmsMainColor');
        echo '<script>';
        foreach (self::FIELDS as $name => $label) {
            echo 'document.documentElement.style.setProperty(' . json_encode('--' . $name) . ',' . json_encode('#' . Theme::color($name)) . ');';
        }
        echo 'document.querySelectorAll("meta[name=theme-color]").forEach(function(n){n.setAttribute("content",' . json_encode($main) . ');});';
        echo 'var chrome=document.getElementById("cmsSafariChrome");if(chrome)chrome.textContent=' . json_encode('html{background-color:' . $main . '}body.cms>.cmsMobileHeader{background-color:' . $main . '}') . ';';
        echo 'document.querySelectorAll(".themeSwatches input").forEach(function(input){input.addEventListener("input",function(){var dot=input.parentElement.querySelector("i");if(dot)dot.style.backgroundColor=input.value;var hex=input.parentElement.querySelector("small");if(hex)hex.textContent=input.value;});});';
        echo '</script>';
        echo '<div class="projectFiles themeEditor">';
        echo '<h1>Theme</h1>';
        echo '<p class="tagsHint">Main, highlight, background and text. Everything else follows. The stage marker keeps its own colours.</p>';
        echo \message::flush();
        echo '<section class="widget"><header><h2>Colours</h2><button type="button" class="button" data-on_click="resetColors"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i>Reset to default</button></header><main>';
        echo '<div class="themeSwatches">';
        foreach (self::FIELDS as $name => $label) {
            $hex = '#' . Theme::color($name);
            echo '<label><input type="color" data-on_change="save" data-key="' . $e($name) . '" name="value" value="' . $e($hex) . '" aria-label="' . $e($label) . '">';
            echo '<i style="background-color:' . $e($hex) . '"></i><span>' . $e($label) . '<small>' . $e($hex) . '</small></span></label>';
        }
        echo '</div>';
        Contrast::render();
        echo '</main></section>';
        ThemeAssets::render($panel);
        echo '<section class="widget themeIcons"><header><h2>Icon sets</h2></header><main>';
        echo '<p>Tabler Outline is used for controls and content. Small information hints use the original Solar info-circle motif in one colour. Phosphor Duotone is used for main navigation, including Tools (Wrench), custom main-navigation panels and the passkey animation. Cleanup and log clearing use the Tabler eraser. All icons are served locally.</p>';
        echo '<nav class="cmsBoxActions" aria-label="Icon set search">';
        echo '<a class="button cmsExternalLink" href="https://tabler.io/icons?style=outline" target="_blank" rel="noopener noreferrer">Tabler Outline<i class="cmsIcon cmsIcon-arrow-up-right-from-square" aria-hidden="true"></i></a>';
        echo '<a class="button cmsExternalLink" href="https://phosphoricons.com/?weight=duotone" target="_blank" rel="noopener noreferrer">Phosphor Duotone<i class="cmsIcon cmsIcon-arrow-up-right-from-square" aria-hidden="true"></i></a>';
        echo '</nav>';
        echo '</main></section>';

        $pagesHere = self::pagesPanelPresent();
        if ($pagesHere) {
            echo '<section class="widget"><header><h2>Page overrides</h2></header>';
            echo '<form method="post" action="saveCss">';
            echo '<main><p class="tagsHint">pages_overrides.css. Only the page preview, and only while the pages panel is installed. Selectors are parsed into the page.</p>';
            echo '<textarea class="themeCss" name="css" aria-label="Public website CSS" spellcheck="false" autocomplete="off" autocapitalize="off">' . $e(self::readCss()) . '</textarea></main>';
            echo '<footer><button type="submit" class="button submit"><i class="cmsIcon cmsIcon-check"></i>Save CSS</button></footer>';
            echo '</form></section>';
        }
        echo '<section class="widget"><header><h2>System overrides</h2></header>';
        echo '<form method="post" action="saveSystemCss">';
        echo '<main><p class="tagsHint">system_overrides.css. Loaded in the CMS only, not on the public site, and not parsed into the page preview.</p>';
        echo '<textarea class="themeCss" name="css" aria-label="CMS system overrides CSS" spellcheck="false" autocomplete="off" autocapitalize="off">' . $e(self::readSystemCss()) . '</textarea></main>';
        echo '<footer><button type="submit" class="button submit"><i class="cmsIcon cmsIcon-check"></i>Save CSS</button></footer>';
        echo '</form></section></div>';
    }
}
