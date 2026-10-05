<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/**
 * A package that serves requests besides the panels — systopic/cms with the
 * website. Registered with `Packages::addFrontend()`; loader.php asks it at
 * three points and includes what it returns in the global scope (the entry
 * files rely on the globals of the bootstrap, as app.php does).
 */
interface Frontend
{
    /** The dispatch mode it stands for ('site'), as `beforeDispatch` sees it. */
    public function name(): string;

    /** Before the panel tree: a request it answers alone (sitemap.xml) — file to include, then exit. */
    public function early(): ?string;

    /** After the panel tree is built: file that sets up what it needs (route, renderer). */
    public function boot(): ?string;

    /** No panel was selected by the URL: does it serve this request? */
    public function claims(): bool;

    /** It serves the request: last preparations, returns the entry file to include. */
    public function dispatch(): string;
}
