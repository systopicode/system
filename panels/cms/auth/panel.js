/* global sys */
(function () {
    const setup = {info: 'CMS authentication', route: 'panels.cms.auth', extends: 'classes.panelNode'};
    let screenPollTimer = null;
    let authSlide = null;
    let resetEmail = '';
    const slideEase = 'transform 0.32s cubic-bezier(0.32, 0.72, 0, 1)';

    const captureSlide = (event) => {
        const link = event.target && event.target.closest && event.target.closest('.authCard a.ajax');
        if (!link) return;
        if (link.matches('.authLinks a[href*="resetPassword"]')) {
            const username = link.closest('.authCard').querySelector('input[name="username"]')?.value.trim() || '';
            resetEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(username) ? username : '';
        }
        const dir = link.classList.contains('authMethod') ? 'forward'
            : (link.classList.contains('authSwitch') || link.classList.contains('authBack') ? 'back' : null);
        const main = dir && link.closest('.authCard')?.querySelector(':scope > main');
        if (!main) return;
        const ghost = main.cloneNode(true);
        ghost.classList.add('authSlideGhost');
        ghost.setAttribute('aria-hidden', 'true');
        authSlide = {dir, ghost};
    };

    const runSlide = () => {
        const pending = authSlide;
        authSlide = null;
        const card = document.querySelector('.authCard');
        const main = card && card.querySelector(':scope > main');
        if (!card || !main || !pending || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;

        // Gap = card padding, so the panes don’t stick (authFoot overhangs that far).
        const forward = pending.dir === 'forward';
        const from = forward ? 'calc(100% + var(--authPadX))' : 'calc(-100% - var(--authPadX))';
        const out = forward ? 'calc(-100% - var(--authPadX))' : 'calc(100% + var(--authPadX))';
        const ghost = pending.ghost;
        const stage = document.createElement('div');
        stage.className = 'authSlideStage';

        ghost.style.transform = 'translateX(0)';
        ghost.style.transition = 'none';
        main.style.transform = 'translateX(' + from + ')';
        main.style.transition = 'none';

        card.insertBefore(stage, main);
        stage.append(ghost, main);
        void stage.offsetWidth;

        let finished = false;
        const done = () => {
            if (finished) return;
            finished = true;
            main.style.transform = '';
            main.style.transition = '';
            if (stage.parentNode === card) {
                card.insertBefore(main, stage);
                stage.remove();
            }
        };

        ghost.style.transition = slideEase;
        main.style.transition = slideEase;
        ghost.style.transform = 'translateX(' + out + ')';
        main.style.transform = 'translateX(0)';

        main.addEventListener('transitionend', (event) => {
            if (event.target === main && event.propertyName === 'transform') done();
        });
        setTimeout(done, 450);
    };

    const boot = () => {
        const email = document.querySelector('.authCard input[name="email_reset"]');
        if (email && resetEmail) { if (!email.value) email.value = resetEmail; resetEmail = ''; }
        runSlide();
        if (window.sys && sys.passkeys) {
            sys.passkeys.checkSupport();
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
                        sys.passkeys.showSignedIn(block, answer.profile, answer.redirect);
                        setTimeout(() => { location.href = answer.redirect; }, 2000);
                    } else if (answer.expired) {
                        clearInterval(screenPollTimer);
                        location.reload();
                    }
                })
                .catch(() => {});
        }, 3000);
    };

    document.addEventListener('click', captureSlide, true);

    sys.lib.add(setup, function () {
        sys.lib.applyParentConstructor(this, setup, []);
        this.onUpdateMain = boot;
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
        else boot();
    });
})();
