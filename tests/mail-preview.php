<?php
require __DIR__.'/bootstrap.php';
class fs {public static string $root=''; public static string $log; static function file_get_contents($path){return self::$log;}}
class http {public static string $root='/'; static function get($key,$default){return $default;}}
$data=['htmlContent'=>'<b>Message body</b><script>parent.compromised=true</script><img src="https://tracking.example/pixel">','recipients'=>['alice@example.org']];
fs::$log='[2026-10-29 12:00:00] Status: success | From: sender@example.org | To: alice@example.org | Subject: Test'."\nJSON_DATA: ".json_encode($data)."\n".str_repeat('-',80)."\n";
$panel=new class {public object $parent;function render(){ob_start();include dirname(__DIR__).'/panels/admin/090_tools/logs/email/view/lightbox.tpl.php';return ob_get_clean();}};
$panel->parent=new class {function getHref($root){return '/logs/email/';}};
$html=$panel->render();
check('HTML email remains formatted in an isolated frame',str_contains($html,'sandbox=""')&&str_contains($html,'Content-Security-Policy'));
file_put_contents('/private/tmp/fluxo-mail-preview.html',$html);
