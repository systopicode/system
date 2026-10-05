<?php

declare(strict_types=1);

namespace Systopic\System\Queries\MediaMaintenance;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Medias\Model as MediaRow;

/** The media cms/tools/maintenance/media repairs — `MediaMaintenance.sql`. */
final class MediaMaintenance
{
    /**
     * @param 'mimetype'|'size' $kind
     * @return list<MediaRow>
     */
    public static function lacking(Scope $scope, string $kind, int $limit): array
    {
        Registry::register(MediaRow::class);
        $rows = $scope->rows(__DIR__ . '/MediaMaintenance.sql', ['kind' => $kind, 'kind2' => $kind], cache: false)->all(MediaRow::class);
        return array_slice($rows, 0, $limit);
    }
}
