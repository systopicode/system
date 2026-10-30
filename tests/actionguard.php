<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Systopic\System\Panels\Action;
use Systopic\System\Panels\ActionGuard;
use Systopic\System\Panels\ActionRefused;
use Systopic\System\Panels\PanelNode;

// The file helpers ActionScanner reads actions.php with, without the legacy lib.
final class fs
{
    public static function isFile(string $path): bool { return is_file($path); }
    public static function toNative(string $path): string { return $path; }
    public static function cwd(): string { return (string) getcwd(); }
}

// A panel without the tree: only what the guard asks of it.
final class GuardTestPanel extends PanelNode
{
    public array $entryActions = ['open'];
    public bool $granted = true;

    public function actionGranted($action = null)
    {
        return $this->granted;
    }

    #[Action]
    public function refresh(): void {}

    public function helper(): void {}
}

$panel = (new ReflectionClass(GuardTestPanel::class))->newInstanceWithoutConstructor();
$panel->path = ['cms', 'test'];

$request = static function (string $method, array $headers = []): void {
    foreach (['HTTP_SEC_FETCH_SITE', 'HTTP_ORIGIN'] as $key) {
        unset($_SERVER[$key]);
    }
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_HOST'] = 'cms.example';
    $_SERVER += $headers;
};
$refused = static function (string $action) use ($panel): ?string {
    try {
        ActionGuard::check($panel, $action);
        return null;
    } catch (ActionRefused $e) {
        return $e->reason;
    }
};

$request('PUT', ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
check('from this site: runs', $refused('save') === null);
$request('PUT', ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
check('from another site: refused', $refused('save') === 'origin');
$request('POST', ['HTTP_SEC_FETCH_SITE' => 'same-site']);
check('from a sibling subdomain: refused', $refused('save') === 'origin');
$request('GET', ['HTTP_SEC_FETCH_SITE' => 'none']);
check('typed or bookmarked: refused', $refused('save') === 'origin');
$request('GET', ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
check('a link from another site: refused (cms/pages/publish?id=5)', $refused('save') === 'origin');
check('… unless it is an entry action', $refused('open') === null);
$request('PUT', ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
check('an entry action by PUT from outside: refused', $refused('open') === 'origin');

$request('PUT');
check('no browser (no header, no Origin): runs', $refused('save') === null);
$request('PUT', ['HTTP_ORIGIN' => 'https://evil.example']);
check('an old browser from another host: refused', $refused('save') === 'origin');
$request('PUT', ['HTTP_ORIGIN' => 'https://cms.example']);
check('an old browser from this host: runs', $refused('save') === null);

$request('PUT', ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
$panel->granted = false;
check('actionGranted() says no: refused', $refused('save') === 'granted');
$panel->granted = true;

$dir = sys_get_temp_dir() . '/actionguard-' . bin2hex(random_bytes(4));
mkdir($dir);
$why = static function (string $action, string $actionsDir) use ($panel): string {
    try {
        return ActionGuard::method($panel, $action, $actionsDir) === null ? 'not called' : 'called';
    } catch (ActionRefused $e) {
        return $e->getMessage();
    }
};
check('a method with #[Action] is an action', $why('refresh', $dir) === 'called');
check('a public helper nothing else runs: refused, with the hint', str_contains($why('helper', $dir), 'has no #[Action] attribute'));
check('a method of the framework: refused as such', str_contains($why('updateView', $dir), 'belongs to the panel framework'));
file_put_contents("$dir/actions.php", "<?php\nswitch (\$this->action) {\n    case 'helper':\n        break;\n}\n");
check('a helper whose name actions.php runs: not called, no complaint', $why('helper', $dir) === 'not called');
check('an action that is no method: nothing to say', $why('save', $dir) === 'not called');
unlink("$dir/actions.php");
rmdir($dir);

echo "all ActionGuard checks passed\n";
