/* global sys */
(function () {
    const setup = {info: 'CMS authentication', route: 'panels.cms.auth', extends: 'classes.panelNode'};
    let screenPollTimer = null;
    const boot = () => {
        if (window.sys && sys.passkeys) {
            sys.passkeys.attachAutofill();
            sys.passkeys.startIfAuto();
        }
        if (screenPollTimer || !document.querySelector('[data-screen-pairing]')) return;
        const url = location.pathname;
        screenPollTimer = setInterval(() => {
            const block = document.querySelector('[data-screen-pairing]');
            if (!block || location.pathname !== url) {
                clearInterval(screenPollTimer);
                screenPollTimer = null;
                return;
            }
            if (!block.offsetParent || document.visibilityState === 'hidden') return;
            fetch(url, {headers: {'X-Screen-Poll': '1'}, credentials: 'same-origin', cache: 'no-store'})
                .then(response => response.json())
                .then(answer => {
                    if (answer.paired) {
                        clearInterval(screenPollTimer);
                        location.href = answer.redirect;
                    } else if (answer.expired) {
                        clearInterval(screenPollTimer);
                        location.reload();
                    }
                })
                .catch(() => {});
        }, 3000);
    };
    sys.lib.add(setup, function () {
        sys.lib.applyParentConstructor(this, setup, []);
        this.onUpdateMain = boot;
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
        else boot();
    });
})();
