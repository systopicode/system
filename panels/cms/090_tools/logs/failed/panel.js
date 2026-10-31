// Register with the existing panel loader; behavior below uses shared DOM events.
if (window.sys?.lib?.add) {
    const setup = {route: 'panels.cms.tools.logs.failed', extends: 'panels.cms'};
    sys.lib.add(setup, function () { sys.lib.applyParentConstructor(this, setup, []); });
}
(function () {
    function renderFailures() {
        const body = document.querySelector('[data-request-failures]');
        if (!body) return;
        body.replaceChildren();
        const entries = window.cmsFeedback?.failures() || [];
        for (const entry of entries) {
            const row = body.insertRow();
            row.insertCell().textContent = new Date(entry.time).toLocaleString();
            row.insertCell().textContent = entry.kind;
            const link = document.createElement('a');
            link.className = 'button transparent'; link.href = entry.url;
            link.innerHTML = '<i class="cmsIcon cmsIcon-arrow-up-right-from-square"></i><span>Open page</span>';
            row.insertCell().append(link);
        }
        if (!entries.length) {
            const cell = body.insertRow().insertCell(); cell.colSpan = 3;
            cell.textContent = 'No failed requests recorded in this browser session.';
        }
    }
    if (!window.cmsFailuresViewInstalled) {
        window.cmsFailuresViewInstalled = true;
        document.addEventListener('cms:rendered', renderFailures);
        document.addEventListener('cms:failures', renderFailures);
    }
    renderFailures();
})();
