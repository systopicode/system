<?php
require __DIR__ . '/bootstrap.php';
use Systopic\System\Settings\EnvironmentForm;
use Systopic\System\Config\Schema;

$control = new ReflectionMethod(EnvironmentForm::class, 'control');
$render = static function (array $field, string $value = '') use ($control): DOMDocument {
    ob_start();
    $control->invoke(null, 'config.json', 'system', 'legal.test', $field, $value, true, ['source'=>'none','value'=>null], ['value'=>$value]);
    $html = ob_get_clean();
    $doc = new DOMDocument();
    $doc->loadHTML('<meta charset="utf-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    return $doc;
};
$schema = Schema::load('system')['properties']['legal']['properties'];
foreach (['operator', 'dpo'] as $key) {
    $text = "Line one\nSecond line & <tag>";
    $doc = $render($schema[$key], $text);
    $field = $doc->getElementsByTagName('textarea')->item(0);
    check($key . ' uses the multiline annotation', $field !== null && $field->getAttribute('rows') === '4' && $field->getAttribute('data-on_blur') === 'save');
    check($key . ' keeps line breaks and escapes markup', $field->textContent === $text && $doc->getElementsByTagName('tag')->length === 0);
}
foreach (['imprintUrl', 'privacyUrl'] as $key) {
    $doc = $render($schema[$key], '/privacy/');
    check($key . ' stays a single-line input with accessible link help', $doc->getElementsByTagName('textarea')->length === 0 && $doc->getElementsByTagName('input')->item(0)->getAttribute('aria-describedby') === 'env-hint-system-legal-test' && str_starts_with($schema[$key]['x-hint'], 'Link:'));
}
check('ordinary string fields remain single-line', $render(['title'=>'Plain', 'type'=>'string'])->getElementsByTagName('input')->length === 1);
check('email validation remains native', $render(['title'=>'Mail', 'type'=>'string','format'=>'email'])->getElementsByTagName('input')->item(0)->getAttribute('type') === 'email');

$merge = new ReflectionMethod(EnvironmentForm::class, 'mergeFolds');
$stored = ['system.general', 'packages.system.legal', 'packages.cms.languages', 'db.general', 'relay.tool'];
$changed = $merge->invoke(null, $stored, ['packages.cms.meta', 'system.general'], 'cms');
check('folds of other config packages are preserved', $changed === ['system.general', 'packages.system.legal', 'db.general', 'relay.tool', 'packages.cms.meta']);
$closed = $merge->invoke(null, $changed, [], 'system');
check('close all affects only the current package', $closed === ['db.general', 'relay.tool', 'packages.cms.meta']);
check('one package cannot inject another package fold', $merge->invoke(null, ['db.general'], ['relay.tool', 'relay.tool', 'system.general'], 'relay') === ['db.general', 'relay.tool']);
