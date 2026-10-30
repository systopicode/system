<?php

declare(strict_types=1);

namespace Systopic\System\Panels;

use Systopic\System\Controlmap\ActionScanner;

/**
 * What has to hold before an action runs (app.php). Each rule throws
 * `ActionRefused`; app.php catches it, shows it in the debug output and
 * renders the panel without the action.
 *
 * 1. **The request comes from this site** — against CSRF. The session
 *    cookie (SameSite=Lax) travels along with a link clicked on another
 *    site, and the action is the last segment of the address, for GET too:
 *    `cms/pages/publish?id=5` in a mail would publish. A current browser
 *    says where a request comes from in `Sec-Fetch-Site`, which no page can
 *    forge: only `same-origin` passes. Without that header (an old browser,
 *    curl, a webhook) an `Origin` header has to name this host; with
 *    neither the request is no browser's and no CSRF — so the CLI agent's
 *    requests pass, unless a payload sets the headers to test the rule.
 *    The panel's `$entryActions` pass by GET from anywhere.
 * 2. **The panel grants it** (`actionGranted()`).
 * 3. **A method is marked** — `method()`: a request calls a method of the
 *    panel by the action's name only when it carries `#[Action]`. A public
 *    method without it is a helper of the templates. When nothing else
 *    runs the action either (actions.php does not name it), the request
 *    asked for a method that is no action: that throws as well, with the
 *    hint to add the attribute — in a migrated panel whose methods were
 *    actions before, this is the place that says so.
 */
final class ActionGuard
{
    /** The actions `fsUploader::handleAction()` answers before actions.php. */
    private const UPLOADER_ACTIONS = ['storeSpan', 'fetchFromUrl'];

    /** @throws ActionRefused */
    public static function check(PanelNode $panel, string $action): void
    {
        self::origin($panel, $action);
        if (!$panel->actionGranted($action)) {
            throw ActionRefused::notGranted($panel, $action);
        }
    }

    /**
     * The method of the panel a request names, when it is meant as one
     * (`#[Action]`); null when there is none — or when there is one without
     * the attribute that actions.php runs instead (`case 'loginMethod'`).
     *
     * @param string|null $actionsDir the folder of the panel's actions.php; the current one, as for runActions()
     * @throws ActionRefused a public method of that name without `#[Action]`, and nothing else runs the action
     */
    public static function method(PanelNode $panel, string $action, ?string $actionsDir = null): ?\ReflectionMethod
    {
        if ($action === '' || !method_exists($panel, $action)) {
            return null;
        }
        $method = new \ReflectionMethod($panel, $action);
        if ($method->getAttributes(Action::class) !== []) {
            return $method->isPublic() ? $method : null;
        }
        if (!$method->isPublic() || in_array($action, self::UPLOADER_ACTIONS, true)) {
            return null;
        }
        if (isset(ActionScanner::scan($actionsDir ?? \fs::cwd())[$action])) {
            return null;
        }
        $declaring = $method->getDeclaringClass()->getName();
        throw ActionRefused::notAnAction($panel, $action, $method, is_a(PanelNode::class, $declaring, true));
    }

    /** Tell the user and the log; the request goes on without the action. */
    public static function refused(ActionRefused $refused): void
    {
        error_log($refused->getMessage() . ' — ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''));
        if (!headers_sent()) {
            http_response_code(403);
        }
        \message::error($refused->userMessage());
    }

    /** @throws ActionRefused */
    private static function origin(PanelNode $panel, string $action): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['GET', 'HEAD'], true) && in_array($action, $panel->entryActions, true)) {
            return;
        }
        $site = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($site === 'same-origin') {
            return;
        }
        if ($site !== '') {
            throw ActionRefused::foreign($panel, $action, "Sec-Fetch-Site: $site");
        }
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin !== '' && !self::isThisHost($origin)) {
            throw ActionRefused::foreign($panel, $action, "Origin: $origin");
        }
    }

    /** Does an Origin header (`https://host:port`) name the host of this request? */
    private static function isThisHost(string $origin): bool
    {
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $port = parse_url($origin, PHP_URL_PORT);
        $named = $host . ($port !== null && $port !== false ? ':' . $port : '');
        return $host !== '' && $named === strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    }
}
