<?php

declare(strict_types=1);

namespace Systopic\System\Settings\Tools;

use Systopic\System\Auth\AuthConfig;
use Systopic\System\Auth\Session;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Settings\EnvironmentFile;
use Systopic\System\Settings\SettingsTool;
use Systopic\System\Settings\ToolView;

/**
 * packages.system.auth (`x-tool` in config.schema.json): the sign-in key,
 * made, replaced or deleted on its machine.
 */
final class AuthTool implements SettingsTool
{
    public static function actions(): array
    {
        return ['createAuth', 'replaceAuth', 'removeAuth'];
    }

    public static function render(ToolView $view): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $file = $view->file;
        if ($view->project) {
            return;
        }
        echo '<div class="envToolBox">';
        if (strlen(EnvironmentFile::authSecret()) < 32) {
            echo '<p class="tagsHint">A sign-in key enables one-time codes for this environment.</p>';
            echo '<button type="button" class="button" data-on_click="createAuth" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-key-skeleton"></i> Create sign-in key</button></div>';
            return;
        }
        echo '<p class="tagsHint">Replacing the key makes codes already sent stop working.</p><div class="envToolActions">';
        echo '<button type="button" class="button" data-on_click="userConfirm" data-action="replaceAuth" data-confirm="Replace the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-arrows-rotate"></i> Replace sign-in key</button>';
        if (Session::isSuperuser()) {
            echo '<button type="button" class="button" data-on_click="userConfirm" data-action="removeAuth" data-confirm="Delete the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-trash"></i> Delete sign-in key</button>';
        }
        echo '</div></div>';
    }

    public static function handle(PanelNode $panel, string $action, string $file): void
    {
        if ($action === 'removeAuth' && !Session::isSuperuser()) {
            \message::error('Only a superuser can delete the sign-in key.');
            return;
        }
        $done = match ($action) {
            'createAuth' => ToolView::running($file) && EnvironmentFile::createAuth(),
            'replaceAuth' => ToolView::running($file) && EnvironmentFile::createAuth(true),
            default => ToolView::running($file) && EnvironmentFile::removeAuth(),
        };
        if (!$done) {
            \message::error('That change was not saved.');
        } else {
            AuthConfig::rememberSecret(EnvironmentFile::authSecret());
            \message::confirm(match ($action) {
                'createAuth' => 'Sign-in key created.',
                'replaceAuth' => 'Sign-in key replaced. Codes already sent stop working.',
                default => 'Sign-in key deleted. Codes already sent stop working.',
            });
        }
        $panel->updateView('main');
    }
}
