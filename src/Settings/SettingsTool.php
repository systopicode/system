<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Panels\PanelNode;

/**
 * What a block of the definitions brings beyond its values: a status, a
 * button that does more than write one value (a test mail, a new sign-in
 * key, the hub). The definition names its class with `x-tool`, on the node
 * of the block; the form shows it below the rows of that block and passes
 * on the calls it names. Nothing in EnvironmentForm knows a tool by name.
 */
interface SettingsTool
{
    /** what stands below the rows of its block */
    public static function render(ToolView $view): void;

    /**
     * The calls it answers, by action.
     *
     * @return list<string>
     */
    public static function actions(): array;

    /** One of its calls. The file is one of this project's config files. */
    public static function handle(PanelNode $panel, string $action, string $file): void;
}
