<?php

declare(strict_types=1);

namespace Systopic\System\Settings\Tools;

use Systopic\System\Auth\Session;
use Systopic\System\Config\Tree;
use Systopic\System\Mail\Delivery;
use Systopic\System\Mail\Failure;
use Systopic\System\Mail\TestDelivery;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Settings\EnvironmentFile;
use Systopic\System\Settings\SettingsTool;
use Systopic\System\Settings\ToolView;

/**
 * packages.system.mail (`x-tool` in config.schema.json): the delivery that
 * counts, a test mail, and the fluxo account in one go.
 */
final class MailTool implements SettingsTool
{
    /** the fields the fluxo account fills in; the rest of the block stays */
    public const FIELDS = ['host', 'port', 'secure', 'user', 'from', 'fromName', 'testMode', 'testRecipient'];

    public static function actions(): array
    {
        return ['testMail', 'resetMail'];
    }

    public static function render(ToolView $view): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        if ($view->project) {
            return;
        }
        // what test mode and the test recipient do is in their descriptions (config.schema.json)
        echo '<div class="envToolBox">';
        $actions = '';
        if ($view->here && Session::isSuperuser()) {
            $effective = Delivery::settings();
            $transport = trim((string) ($effective['host'] ?? '')) ?: 'Local mail catcher (127.0.0.1:1025)';
            try {
                $recipient = Delivery::testRecipient();
            } catch (\RuntimeException) {
                $recipient = 'Invalid recipient — delivery blocked';
            }
            echo '<p class="tagsHint">Effective delivery: <strong>' . $e($transport) . '</strong> → <strong>' . $e($recipient) . '</strong>. Local overrides apply. SMTP acceptance confirms handover to this server, not delivery to the inbox.</p>';
            $actions = '<button type="button" class="button" data-action-feedback data-loading data-on_click="testMail" data-file="' . $e($view->file) . '"><i class="cmsIcon cmsIcon-paper-plane"></i>Send test email</button><a class="button outline" href="' . $e(\http::$root . 'admin/tools/logs/email/') . '"><i class="cmsIcon cmsIcon-envelope"></i>Email log</a>';
        }
        if (Session::isSuperuser()) {
            echo '<div class="envToolActions"><button type="button" class="button outline" data-on_click="userConfirm" data-action="resetMail" data-confirm="Set the mail settings of this installation to the fluxo account? This clears SMTP credentials and test routing overrides." data-file="' . $e($view->file) . '"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i>Use the fluxo account</button>' . $actions . '</div>';
        }
        echo '</div>';
    }

    public static function handle(PanelNode $panel, string $action, string $file): void
    {
        if ($action === 'resetMail') {
            Session::isSuperuser() && ToolView::running($file) && self::reset()
                ? \message::confirm('Mail settings set to the fluxo account.')
                : \message::error('That change was not saved.');
            $panel->updateViews(['main', 'controls']);
            return;
        }
        if (!Session::isSuperuser() || !ToolView::running($file)) {
            \message::error('Only a superuser can test mail for the active environment.');
            return;
        }
        try {
            $result = TestDelivery::send();
            $text = ($result['ok'] ? 'SMTP accepted the test message' : 'SMTP did not accept the test message') . ' for ' . $result['recipient'] . '. Test routing: ' . ($result['testMode'] ? 'on' : 'off') . '. SMTP acceptance does not confirm inbox delivery.';
            $result['ok'] ? \message::confirm($text) : \message::error($text . ' Check the email log and mail settings.');
        } catch (\Throwable $error) {
            $detail = Failure::detail($error->getMessage(), Delivery::settings());
            \message::error('Test mail failed. ' . Failure::hint($detail) . ' Server response: ' . htmlspecialchars($detail, ENT_QUOTES));
        }
        $panel->updateViews(['main', 'controls']);
    }

    /** The fluxo account for this installation: its fields replaced, test routing off. */
    public static function reset(): bool
    {
        return EnvironmentFile::updateLocal(static function (array $data): array {
            $mail = (array) Tree::get($data, ['packages', 'system', 'mail']);
            Tree::put($data, ['packages', 'system', 'mail'], array_diff_key($mail, array_flip(self::FIELDS)) + [
                'host' => Delivery::DEFAULT_HOST,
                'port' => 587,
                'secure' => 'tls',
                'user' => Delivery::DEFAULT_SENDER,
                'from' => Delivery::DEFAULT_SENDER,
                'fromName' => 'fluxo',
            ]);
            return $data;
        });
    }
}
