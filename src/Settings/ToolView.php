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

    /** Shared information line; callers escape any dynamic values in the trusted HTML. */
    public static function hint(string $html, string $class = ''): string
    {
        return '<p class="tagsHint cmsHint ' . htmlspecialchars($class, ENT_QUOTES) . '"><i class="cmsIcon cmsIcon-circle-info" aria-hidden="true"></i><span>' . $html . '</span></p>';
    }

    /** Is it the file of the installation that runs here? */
    public static function running(string $file): bool
    {
        return $file === EnvironmentFile::LOCAL && EnvironmentFile::activeFile() !== null;
    }
}
