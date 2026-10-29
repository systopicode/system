<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
class http { public static string $root = '/example/'; }
use Systopic\System\Sys\Release;
$system = dirname(__DIR__);
$cms = dirname(__DIR__, 2) . '/cms';
foreach ([$system, $cms] as $root) {
    $package = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    check($package['name'] . ' declares the selected license', $package['license'] === 'proprietary');
    $readme = file_get_contents($root . '/README.md');
    check($package['name'] . ' README names the same license', str_contains($readme, '[systopic Software License](LICENSE)') && !str_contains($readme, 'MIT License'));
}
check('package license texts agree', file_get_contents($cms . '/LICENSE') === Release::license());
check('earlier license grants remain unaffected', str_contains(Release::license(), 'does not revoke rights already granted'));
$render = static function(string $file): string {ob_start(); try {include $file; return ob_get_contents();} finally {ob_end_clean();}};
$license = $render($system . '/panels/cms/auth/license.tpl.php');
check('GUI displays the actual selected license', str_contains($license, 'systopic Software License') && str_contains($license, 'Third-party software'));
$privacy = $render($system . '/panels/cms/auth/privacy.tpl.php');
check('privacy renders without project configuration', str_contains($privacy,'<main class="authText">') && str_contains($privacy,'<div class="authScroll">'));
check('privacy matches purpose-based log retention and actual cookie lifetime', str_contains($privacy,'login-log entries older than 365 days eligible for automatic deletion') && str_contains($privacy,'maximum lifetime of 400 days'));
check('retention criteria and installation duties are explicit', str_contains($privacy, 'detection and investigation window') && str_contains($privacy, 'define and document review intervals') && str_contains($privacy, 'not a scheduled daily deletion'));
check('consent and rights are not replaced by a notice', str_contains($privacy, 'requests must be prevented until it has been given') && str_contains($privacy, 'Signing in does not waive') && str_contains($privacy, 'particular situation'));
check('browser storage is covered', str_contains($privacy,'cmsDeviceMobile') && str_contains($privacy,'cmsTimezone') && str_contains($privacy,'cmsOpenSheet'));
check('external requests and hash limits are disclosed', str_contains($privacy,'exposes your IP address') && str_contains($privacy,'not an authentication secret or a guarantee of anonymity'));
check('CMS video processing has its own scope', str_contains($privacy,'only after you click its Load player button') && str_contains($privacy,"not the public website's player or consent settings"));
check('installation links respect the root', str_contains($privacy,'/example/privacy/') && str_contains($privacy,'/example/imprint/'));
if (($argv[1] ?? '') === '--render-privacy') file_put_contents(sys_get_temp_dir() . '/fluxo-privacy-review.html', $privacy);
echo "All legal notice checks passed.\n";

use Systopic\System\Settings\LegalLinks;
use Systopic\System\Settings\ProjectJson;
use Systopic\System\Settings\ProjectSettings;
foreach (['', '/legal/privacy/', 'https://example.org/privacy?cms=1'] as $url) {
    check('legal URL accepted: ' . $url, LegalLinks::valid($url));
}
foreach (['javascript:alert(1)', '//example.org', '/\\example.org', "https://example.org/\n", 'https://name:secret@example.org/'] as $url) {
    check('unsafe legal URL rejected', !LegalLinks::valid($url));
}
$dir = sys_get_temp_dir() . '/fluxo-legal-links-' . bin2hex(random_bytes(5));
mkdir($dir);
$path = $dir . '/global.settings.json';
try {
    ProjectJson::put($path, ['general' => ['PUBLIC_PAGE_NAME' => 'Fixture'], 'custom' => ['keep' => true]]);
    $groups = ProjectJson::groups($path);
    check('empty legal fields available in older JSON projects', count(array_filter($groups, fn($group) => $group->category === 'CMS Legal Links')) === 4);
    check('operator details save as plain multiline text', ProjectJson::replace($path, 'define:CMS_OPERATOR_DETAILS', "Example & Co\nExample Street 1\ncontact@example.org") === "Example & Co\nExample Street 1\ncontact@example.org");
    check('DPO details are optional', ProjectJson::replace($path, 'define:CMS_DPO_DETAILS', '') === '');
    check('oversized operator details refused', ProjectJson::replace($path, 'define:CMS_OPERATOR_DETAILS', str_repeat('x',5001)) === null);
    check('custom legal URL saved', ProjectJson::replace($path, 'define:CMS_PRIVACY_URL', ' https://example.org/cms-privacy/ ') === 'https://example.org/cms-privacy/');
    check('unsafe URL cannot be saved', ProjectJson::replace($path, 'define:CMS_PRIVACY_URL', 'javascript:alert(1)') === null);
    check('blank restores fallback setting', ProjectJson::replace($path, 'define:CMS_IMPRINT_URL', '') === '');
    $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    check('other project settings preserved', $data['custom']['keep'] === true && $data['general']['PUBLIC_PAGE_NAME'] === 'Fixture');
    ProjectSettings::apply('page', $data);
    check('project privacy URL loaded', LegalLinks::url('privacy') === 'https://example.org/cms-privacy/');
    check('empty imprint uses frontend root', LegalLinks::url('imprint') === '/example/imprint/');
    $customPrivacy = $render($system . '/panels/cms/auth/privacy.tpl.php');
    check('operator details rendered escaped with line breaks', str_contains($customPrivacy, 'Example &amp; Co<br') && str_contains($customPrivacy, 'contact@example.org'));
    check('no appointed DPO is implied for blank details', !str_contains($customPrivacy, '<b>Data protection officer:</b>'));
    check('privacy template uses project destination', str_contains($customPrivacy, 'href="https://example.org/cms-privacy/"'));
} finally {
    unlink($path);
    rmdir($dir);
}
echo "All project legal-link checks passed.\n";
