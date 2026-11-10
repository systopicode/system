<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/** What a tool learns about the config shown (SettingsTool::render()). */
final readonly class ToolView
{
    /**
     * @param string               $file    the config file shown
     * @param bool                 $here    it is config.local.json of this installation
     * @param bool                 $project it is config.json: read only
     * @param array<string, mixed> $data    what the file holds
     */
    public function __construct(
        public string $file,
        public bool $here,
        public bool $project,
        public array $data,
    ) {}

    /** Is it the file of the installation that runs here? */
    public static function running(string $file): bool
    {
        return $file === EnvironmentFile::LOCAL && EnvironmentFile::activeFile() !== null;
    }
}
