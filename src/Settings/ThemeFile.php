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

    /** The file the editor shows. The new name wins; the old name is only a fallback. */
    public static function cssSource(): ?string
    {
        $root = rtrim((string) \fs::$siteRoot, '/\\');
        foreach ([
            '/overrides/css/system_overrides.css',
            '/overrides/css/cms_overrides.css',
            '/css/system_overrides.css',
            '/css/cms_overrides.css',
        ] as $name) {
            if (is_file($root . $name)) {
                return $root . $name;
            }
        }
        return null;
    }

    public static function cssPath(): string
    {
        $source = self::cssSource();
        $norm = $source === null ? '' : str_replace('\\', '/', $source);
        if (str_ends_with($norm, '/cms_overrides.css') || str_ends_with($norm, '/system_overrides.css')) {
            return dirname($source) . '/system_overrides.css';
        }
        return rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/css/system_overrides.css';
    }

    public static function readCss(): string
    {
        $path = self::cssSource();
        if ($path === null) {
            return '';
        }
        $text = file_get_contents($path);
        return is_string($text) ? $text : '';
    }

    public static function saveCss(string $css): bool
    {
        if (strlen($css) > 200000 || preg_match('/<\s*\/\s*style\b|<\s*script\b/i', $css) === 1) {
            return false;
        }
        $source = self::cssSource();
        $path = self::cssPath();
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
        if ($source !== null && $source !== $path && str_ends_with(str_replace('\\', '/', $source), '/cms_overrides.css')) {
            @unlink($source);
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
        echo '<section class="widget"><header><h2>Colours</h2></header><main>';
        echo '<div class="themeSwatches">';
        foreach (self::FIELDS as $name => $label) {
            $hex = '#' . Theme::color($name);
            echo '<label><input type="color" data-on_change="save" data-key="' . $e($name) . '" name="value" value="' . $e($hex) . '" aria-label="' . $e($label) . '">';
            echo '<i style="background-color:' . $e($hex) . '"></i><span>' . $e($label) . '<small>' . $e($hex) . '</small></span></label>';
        }
        echo '</div></main></section>';
        ThemeAssets::render($panel);
        $css = self::readCss();
        $where = self::cssSource();
        $shown = $where !== null && str_ends_with(str_replace('\\', '/', $where), 'cms_overrides.css')
            ? 'Showing cms_overrides.css. Saving renames it to system_overrides.css in the same folder.'
            : 'system_overrides.css. Fonts and anything the colour fields do not cover.';
        echo '<section class="widget"><header><h2>System overrides</h2></header>';
        echo '<form method="post" action="saveCss">';
        echo '<main><p class="tagsHint">' . $e($shown) . '</p>';
        echo '<textarea class="themeCss" name="css" spellcheck="false" autocomplete="off" autocapitalize="off">' . $e($css) . '</textarea></main>';
        echo '<footer><button type="submit" class="button submit">Save CSS</button></footer>';
        echo '</form></section></div>';
    }
}
