<?php
namespace Systopic\System\Sys { class Theme {static function color($key){return ['cmsMainColor'=>'1f3a45','cmsHighlightColor'=>'fcb60c','cmsBgColor'=>'f0f3f4','cmsTextColor'=>'161c1f'][$key];}static function textOn($key){return '161c1f';}} }
namespace Systopic\System\Settings {class ThemeAssets {public static bool $logos=false;static function path($key){return null;}static function href($key){return self::$logos ? '/logo-horizontal-'.($key==='wordLight'?'light':'dark').'.png' : '';}}}
namespace {class http {public static $hostUrl='https://fixture.test';public static $siteUrl='https://fixture.test/';} class fs {public static $siteRoot='/fixture';static function toNative($p){return $p;}} }
