<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/** What a tool learns about the config shown (SettingsTool::render()). */
final readonly class ToolView
{
    /**
     * @param string               $file    the config file shown
     * @param bool                 $here    it is the one this server runs
     * @param bool                 $project it is config.json: read only
     * @param array<string, mixed> $data    what the file holds
     */
    public function __construct(
        public string $file,
        public bool $here,
        public bool $project,
        public array $data,
    ) {}

    /** Is it the file this server runs, config.local.json aside? */
    public static function running(string $file): bool
    {
        foreach (EnvironmentFile::files() as $info) {
            if ($info['file'] === $file && $info['current'] && $file !== EnvironmentFile::LOCAL) {
                return true;
            }
        }
        return false;
    }
}
