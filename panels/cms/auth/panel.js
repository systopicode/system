/* global sys */
(function () {
    const setup = {info: 'CMS authentication', route: 'panels.cms.auth', extends: 'classes.panelNode'};
    sys.lib.add(setup, function () {
        sys.lib.applyParentConstructor(this, setup, []);
        this.onUpdateMain = () => sys.passkeys.attachAutofill();
    });
})();
