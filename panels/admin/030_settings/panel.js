// Register with the existing panel loader; behavior below uses shared DOM events.
if (window.sys?.lib?.add) {
    const setup = {route: 'panels.admin.settings', extends: 'panels.admin'};
    sys.lib.add(setup, function () { sys.lib.applyParentConstructor(this, setup, []); });
}
(function () {
    if (window.cmsSettingKeyImpactInstalled) return;
    window.cmsSettingKeyImpactInstalled = true;
    document.addEventListener('blur', event => {
        const field = event.target;
        if (!field.matches?.('input[data-key-impact]') || field.value === field.defaultValue || !field.checkValidity()) return;
        // Ask before the existing blur-save starts, so cancelling never looks
        // like a successful autosave and never sends a write request.
        if (window.confirm(field.dataset.keyImpact)) return;
        field.value = field.defaultValue;
        window.cmsFeedback?.cancel(field);
        field.cmsEditCancelled = true;
        event.stopImmediatePropagation();
        setTimeout(() => { delete field.cmsEditCancelled; }, 0);
    }, true);
})();
