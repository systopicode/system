<?php
/** The very same account dialogs, scoped to the row or user already open.
 * @var \Systopic\System\Tables\Users\Model $fixedUser
 * @var string $fixedMode scan | create | hidden
 * @var string|null $fixedClose existing AJAX route back, or hide an embedded dialog
 */
use Systopic\System\Auth\Session;
use Systopic\System\Auth\SignInGrant;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as Account;
$me = UserLookup::byId($this->scope, (int) Session::id());
if (!$me || !SignInGrant::account($this->scope, $me, $this, (int) $fixedUser->id)) return;
$kind = $fixedUser->type === Account::SCREEN ? 'screen' : ((int) $fixedUser->id === (int) $me->id ? 'self' : 'person');
$accountsOf = ['self'=>[], 'screen'=>[], 'person'=>[]];
$accountsOf[$kind] = [['id'=>(int) $fixedUser->id, 'label'=>trim((string) $fixedUser->fullname) ?: (string) $fixedUser->name]];
$fixedDialog = ['kind'=>$kind, 'mode'=>$fixedMode, 'base'=>\http::$root . 'admin/user/', 'close'=>$fixedClose ?? null, 'refresh'=>$fixedRefresh ?? (\http::$root . 'admin/user/refreshScreenDevices')];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$deviceCode = '';
$pending = null;
include __DIR__ . '/authDialogs.tpl.php';
unset($fixedDialog);
