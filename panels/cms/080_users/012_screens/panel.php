<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users\Screens;

use Systopic\Db\Schema\Schema;
use Systopic\System\Panels\Root\Cms\Users\Users\Panel as UsersPanel;
use Systopic\System\Tables\Users\Model as User;

/**
 * The same panel as Users. This folder only selects screens, so a change
 * to the user list, the lightbox or an action is made once.
 */
class Panel extends UsersPanel
{
    public $icon = 'cmsIcon cmsIcon-tv';

    public string $accountKind = User::SCREEN;

    public function searchHits(\Systopic\System\Sys\Search $search): void
    {
        $users = Schema::tableNameOf(User::class);
        $rows = $search->scope->connection->all(
            "SELECT user_id, user_name, user_fullname, user_email FROM $users
             WHERE LOWER(CONCAT_WS(' ', user_name, user_fullname, user_email)) LIKE ?
             AND user_type = ?
             ORDER BY user_name LIMIT " . $search::PER_GROUP,
            [$search->like(), User::SCREEN],
        );
        foreach ($rows as $row) {
            $name = trim((string) $row['user_name']) !== '' ? (string) $row['user_name'] : (string) $row['user_fullname'];
            $search->add('Screens', 'cmsIcon-tv', $name, (string) $row['user_fullname'], $this->getHref(\http::$root) . 'edit/?user_id=' . (int) $row['user_id']);
        }
    }
}
