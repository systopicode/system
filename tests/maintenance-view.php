<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
class fs {public static string $projectRoot; public static function toNative(string $s): string {return $s;}}
class http {public static string $root = '/project/'; public static string $siteUrl = 'https://example.test/project/';}
class message {public static function flush(): string {return '';}}
fs::$projectRoot = sys_get_temp_dir() . '/fluxo-unconfigured-maintenance-view/';
\Systopic\System\Maintenance\Runner::register('example', 'Example expiry rule', 'Only expired data; active records are retained.', static fn()=>['count'=>0]);
\Systopic\System\Maintenance\Runner::register('email-log', 'Email delivery log', '90 days', static fn()=>['count'=>0]);
\Systopic\System\Maintenance\Runner::register('login-log', 'Login and security log', '365 days', static fn()=>['count'=>0]);
ob_start();
(function () { include dirname(__DIR__) . '/panels/cms/090_tools/040_cleanup/040_automatic/main.tpl.php'; })->call(new class { public array $cleanupPreview=['busy'=>false,'results'=>['example'=>['ok'=>true,'count'=>2]],'remaining'=>[]]; function withView($view) { $GLOBALS['state'] = (object) []; } });
$html = ob_get_clean();
check('manual actions include task-specific previews and cleanup', str_contains($html, 'data-job="example"') && str_contains($html, 'data-action="run"'));
check('agreed login retention is shown separately from other logs', str_contains($html, 'After 365 days') && str_contains($html, 'After 90 days'));
check('webcron starts disabled', str_contains($html, 'Access: <strong>Disabled</strong>'));
file_put_contents('/private/tmp/fluxo-maintenance-view.html', $html);

check('preview markup is part of the updated main view', str_contains($html, 'id="cleanupPreview"') && str_contains($html, 'data-auto-open') && str_contains($html, '2 expired records'));
