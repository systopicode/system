<?php

declare(strict_types=1);
namespace Systopic\System\Settings;

final class Impact
{
    public static function group(string $key, array $allowed = []): array
    {
        if (!class_exists(\Systopic\Db\Connection::class) || !\Systopic\Db\Connection::isOpen()) return ['files'=>null,'outside'=>null];
        try { $files = \Systopic\Db\Scope::default()->connection->all('SELECT media_filename FROM cms_medias WHERE media_group = ?', [$key]); } catch (\PDOException) { return ['files'=>null,'outside'=>null]; }
        $outside = 0;
        foreach ($files as $file) if ($allowed && !in_array(\Systopic\System\Media\GroupPolicy::extension((string) $file['media_filename']), $allowed, true)) $outside++;
        return ['files'=>count($files),'outside'=>$outside];
    }
}
