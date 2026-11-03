<?php

declare(strict_types=1);
namespace Systopic\System\Logs;

final class Filters
{
    public static function controls(): string
    {
        return '<div class="splitApply logFilterMenu"><button type="button" class="button splitApplyMenu" data-menu-selected aria-expanded="false" title="Filter log"><i class="cmsIcon cmsIcon-sliders"></i><span>Filters</span></button><div hidden class="splitApplyList logFilters" data-menu-stay data-feedback="parameters"><label class="field"><span>From</span><input type="date" data-log-from></label><label class="field"><span>Until</span><input type="date" data-log-until></label><label class="field"><span>User</span><input type="search" data-log-user placeholder="Name or email" autocomplete="off"></label><label class="field"><span>Result</span><select data-log-result><option value="">All results</option><option value="success">Successful</option><option value="error">Failed</option><option value="warning">Warning</option><option value="other">Other</option></select></label><div class="logFilterFooter"><small data-log-count aria-live="polite"></small><button type="button" class="button transparent" title="Reset filters" aria-label="Reset filters" data-log-reset><i class="cmsIcon cmsIcon-filter-circle-xmark"></i><span>Reset filters</span></button></div></div></div>';
    }
    public static function attributes(string $date, string $user, string $result): string
    {
        $timestamp = strtotime($date);
        $outcome = preg_match('/fail|error|fatal|denied|wrong/i',$result) ? 'error' : (preg_match('/warn/i',$result) ? 'warning' : (preg_match('/success|created|restored|complete|logged in/i',$result) ? 'success' : 'other'));
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES);
        return ' data-log-time="' . ($timestamp === false ? '' : $timestamp) . '" data-log-user="' . $e($user) . '" data-log-result="' . $outcome . '"';
    }
    public static function userLink(string $label): string
    {
        static $users=null;
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES);
        if ($label==='') return '—';
        if ($users === null) {
            $users=[];
            foreach (\Systopic\Db\Scope::default()->connection->all('SELECT user_id, user_name, user_email FROM usm_users WHERE user_id > 1') as $user) {
                $users[(string)$user['user_id']] = (int)$user['user_id'];
                foreach (['user_name','user_email'] as $key) if ((string)$user[$key] !== '') $users[(string)$user[$key]] = (int)$user['user_id'];
            }
        }
        $id=(int)($users[$label]??0);
        return $id ? '<a href="' . $e(\http::$root.'cms/access/users/edit/?user_id='.$id) . '">' . $e($label) . '</a>' : $e($label);
    }
}
