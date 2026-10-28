/* global sys */
(function () {
    sys.lib.add({info: 'Passkey ceremonies', route: 'singletons.passkeys', shorthand: 'passkeys'}, function () {
        let pending = null;
        let starting = false;
        let autofill = null;
        const decode = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
        const encode = value => btoa(String.fromCharCode(...new Uint8Array(value))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        const options = data => {
            data.challenge = decode(data.challenge);
            if (data.user) data.user.id = decode(data.user.id);
            for (const key of ['allowCredentials', 'excludeCredentials']) {
                for (const credential of data[key] || []) credential.id = decode(credential.id);
            }
            return data;
        };
        const request = async (box, action, data = {}, signal) => {
            const response = await fetch(box.dataset.passkeyEndpoint + action, {
                method: 'POST', mode: 'cors', redirect: 'error', credentials: 'same-origin', cache: 'no-store', signal,
                headers: {'Content-Type': 'application/json', 'X-Fluxo-Passkey': box.dataset.passkeyToken},
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (!response.ok || result.error) throw new Error(result.error || 'Please try again.');
            return result;
        };
        const credentialData = credential => {
            const response = credential.response;
            const data = {id: encode(credential.rawId), clientDataJSON: encode(response.clientDataJSON)};
            if (response.attestationObject) data.attestationObject = encode(response.attestationObject);
            else Object.assign(data, {authenticatorData: encode(response.authenticatorData), signature: encode(response.signature), userHandle: response.userHandle ? encode(response.userHandle) : ''});
            return data;
        };
        const statusTarget = box => {
            if (box.matches('.passkeyLogin, .passkeyOnboarding')) return box.closest('main').querySelector('[data-system-messages]');
            return box.closest('.profile')?.querySelector('[data-system-messages]') || box.querySelector('.passkeyStatus');
        };
        const setStatus = (target, text, type = 'notice') => {
            if (!target) return;
            target.replaceChildren();
            if (!text) return;
            const banner = document.createElement('div');
            banner.className = type;
            const paragraph = document.createElement('p');
            paragraph.className = 'message';
            paragraph.textContent = text;
            banner.append(paragraph);
            target.append(banner);
        };
        const loginFeedback = (box, step, message) => {
            box.dataset.step = step;
            if (box.classList.contains('passkeyLogin')) box.closest('main')?.classList.add('is-passkey-signing-in');
            const feedback = box.querySelector('.passkeyAuthFeedback');
            if (feedback) feedback.hidden = false;
            setStatus(statusTarget(box), message, step === 'success' ? 'confirm' : 'notice');
        };
        const restoreLogin = box => {
            box.closest('main')?.classList.remove('is-passkey-signing-in');
            box.dataset.step = 'ready';
            const feedback = box.querySelector('.passkeyAuthFeedback');
            if (feedback) feedback.hidden = true;
        };
        const revealSignedIn = box => {
            const card = box.closest('.authSignin');
            const done = card?.querySelector('[data-auth-success]');
            if (!done) return;
            [...card.children].forEach(child => { if (child !== done) child.hidden = true; });
            done.hidden = false;
        };
        const notifyRemoval = async signal => {
            if (!signal || !window.PublicKeyCredential?.signalUnknownCredential) return false;
            try { await PublicKeyCredential.signalUnknownCredential(signal); return true; }
            catch (_) { return false; } // Removal in the CMS has already succeeded.
        };
        const stopAutofill = async () => {
            const state = autofill;
            if (!state) return;
            clearTimeout(state.refresh);
            clearTimeout(state.timer);
            state.controller?.abort();
            await state.job;
        };
        const detachAutofill = () => {
            const state = autofill;
            if (!state) return;
            autofill = null;
            clearTimeout(state.refresh); clearTimeout(state.timer);
            state.controller?.abort(); state.observer.disconnect();
            state.input.removeEventListener('input', state.onInput);
            state.input.removeEventListener('change', state.onInput);
            state.form?.removeEventListener('submit', state.onSubmit);
        };
        const scheduleAutofill = (delay = 0) => {
            const state = autofill;
            if (!state?.supported) return;
            clearTimeout(state.timer);
            state.timer = setTimeout(async () => {
                await stopAutofill();
                if (autofill !== state || !state.box.isConnected || pending || state.submitted) return;
                const username = state.input.value.trim();
                if (username && !state.input.validity.valid) return;
                const controller = new AbortController();
                state.controller = controller;
                state.job = (async () => {
                    try {
                        const begin = await request(state.box, 'passkeyBeginLogin', {username, conditional: true}, controller.signal);
                        if (controller.signal.aborted || !state.box.isConnected) return;
                        // Conditional requests outlive the server challenge: replace it before expiry.
                        state.refresh = setTimeout(() => scheduleAutofill(), 105000);
                        const credential = await navigator.credentials.get({publicKey: options(begin.options), mediation: 'conditional', signal: controller.signal});
                        if (!credential || controller.signal.aborted || !state.box.isConnected) return;
                        clearTimeout(state.refresh);
                        pending = {box: state.box, controller};
                        state.box.setAttribute('aria-busy', 'true');
                        loginFeedback(state.box, 'working', 'Checking your passkey …');
                        const result = await request(state.box, 'passkeyFinishLogin', {...credentialData(credential), ceremony: begin.ceremony});
                        loginFeedback(state.box, 'success', 'Signed in.');
                        revealSignedIn(state.box);
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        if (state.box.isConnected && !controller.signal.aborted) window.location.assign(result.redirect);
                    } catch (error) {
                        // No selection/unsupported autofill is silent; the explicit button stays usable.
                        if (pending?.controller === controller && state.box.isConnected) {
                            restoreLogin(state.box);
                            setStatus(statusTarget(state.box), ['AbortError', 'NotAllowedError'].includes(error.name) ? 'Cancelled or timed out. You can try again.' : error.message, ['AbortError', 'NotAllowedError'].includes(error.name) ? 'notice' : 'error');
                        }
                    } finally {
                        if (pending?.controller === controller) pending = null;
                        state.box.removeAttribute('aria-busy');
                    }
                })();
            }, delay);
        };
        this.attachAutofill = () => {
            const box = document.querySelector('.passkeyLogin');
            if (autofill?.box === box) return;
            detachAutofill();
            if (!box || (box.dataset.passkeyOrigin && box.dataset.passkeyOrigin !== window.location.origin) || !window.isSecureContext || !window.PublicKeyCredential?.isConditionalMediationAvailable) return;
            const input = box.closest('main')?.querySelector('[name=username]');
            if (!input) return;
            const state = {box, input, form: input.closest('form'), job: Promise.resolve(), supported: false};
            autofill = state;
            state.onInput = () => { state.submitted = false; state.controller?.abort(); scheduleAutofill(350); };
            state.onSubmit = () => { state.submitted = true; stopAutofill(); };
            input.addEventListener('input', state.onInput);
            input.addEventListener('change', state.onInput);
            state.form?.addEventListener('submit', state.onSubmit);
            state.observer = new MutationObserver(() => { if (!box.isConnected && autofill === state) detachAutofill(); });
            state.observer.observe(document.body, {childList: true, subtree: true});
            Promise.resolve(PublicKeyCredential.isConditionalMediationAvailable()).then(available => {
                if (autofill === state && available) { state.supported = true; scheduleAutofill(); }
            }).catch(() => {});
        };
        const setStep = (dialog, step) => {
            if (!dialog) return;
            const content = {
                ready: ['Create a passkey', 'A simpler way to sign in. Use your fingerprint, face or device PIN.'],
                working: ['Confirm on your device', 'Follow the secure prompt from your browser or password manager.'],
                success: ['Your passkey is ready', 'You can now use it to sign in to this project.']
            }[step];
            dialog.dataset.step = step;
            dialog.querySelector('.passkeySetupTitle').textContent = content[0];
            dialog.querySelector('.passkeySetupLead').textContent = content[1];
            if (step === 'ready') {
                const host = dialog.closest('[data-passkey-count]');
                const count = Number(host?.dataset.passkeyCount || 0);
                const lead = count > 0
                    ? 'A passkey already on this account confirms the new one.'
                    : host?.dataset.passkeyGrant === '1' ? '' : 'Sign in again before adding a passkey.';
                if (lead) dialog.querySelector('.passkeySetupLead').textContent = lead;
            }
        };
        const confirmPasskey = async (box, data, controller, status) => {
            setStatus(status, 'Confirm with a passkey …');
            const begin = await request(box, 'passkeyBeginConfirm', {userId: data.userId}, controller.signal);
            if (controller.signal.aborted || !box.isConnected) throw new DOMException('Cancelled', 'AbortError');
            const credential = await navigator.credentials.get({publicKey: options(begin.options), signal: controller.signal});
            if (!credential || !box.isConnected) throw new DOMException('Cancelled', 'AbortError');
            return {...credentialData(credential), confirm: begin.ceremony};
        };
        this.run = async event => {
            const button = event.element.closest('[data-passkey-mode]');
            const box = button?.closest('[data-passkey-endpoint]');
            if (!box) return;
            const mode = button.dataset.passkeyMode;
            const dialog = box.querySelector('.passkeySetup');
            if (mode === 'cancel') {
                if (pending?.box === box) pending.controller.abort();
                if (dialog?.open) dialog.close();
                return;
            }
            if (pending || starting) return;
            starting = true;
            try {
                await stopAutofill();
                const cardStatus = statusTarget(box);
                if (box.dataset.passkeyOrigin && box.dataset.passkeyOrigin !== window.location.origin) {
                    setStatus(cardStatus, 'Passkeys require ' + box.dataset.passkeyOrigin + '. Open the CMS using that address, or sign in with your password.', 'notice');
                    return;
                }
                if (!window.isSecureContext || !window.PublicKeyCredential || !navigator.credentials) {
                    setStatus(cardStatus, 'This browser needs a secure connection and passkey support.', 'warning');
                    return;
                }
                if (mode === 'setup') {
                    setStep(dialog, 'ready');
                    setStatus(dialog.querySelector('.passkeyStatus'), '');
                    dialog.oncancel = event => {
                        event.preventDefault();
                        if (pending?.box === box) pending.controller.abort();
                        dialog.close();
                    };
                    dialog.onclose = () => {
                        if (pending?.dialog === dialog) pending.controller.abort();
                    };
                    dialog.showModal();
                    return;
                }
                const onboarding = box.classList.contains('passkeyOnboarding');
                const creating = mode === 'create';
                const status = creating && dialog ? dialog.querySelector('.passkeyStatus') : cardStatus;
                const row = button.closest('[data-passkey-row]') || (creating && dialog ? dialog.querySelector('[data-passkey-row]') : null);
                const username = box.closest('main')?.querySelector('[name=username]');
                const data = {userId: Number(box.dataset.passkeyUserId || 0) || undefined, username: username?.value.trim() || '', name: row?.querySelector('[data-passkey-name]')?.value || box.dataset.passkeyName || '', keyId: Number(button.dataset.key_id || 0)};
                if (mode === 'remove' && !window.confirm('Remove this passkey? Password sign-in stays available.')) return;
                const controller = new AbortController();
                pending = {box, controller, dialog: creating ? dialog : null};
                const observer = new MutationObserver(() => { if (!box.isConnected) controller.abort(); });
                observer.observe(document.body, {childList: true, subtree: true});
                box.setAttribute('aria-busy', 'true');
                box.classList.add('is-pending');
                if (creating) setStep(dialog, 'working');
                if (onboarding) box.dataset.step = 'working';
                if (mode === 'login') loginFeedback(box, 'ready', 'Preparing passkey sign-in …');
                setStatus(status, mode === 'login' ? 'Continue on your device …' : creating ? '' : 'Saving …');
                const controls = [...box.querySelectorAll('button:not([data-passkey-mode="cancel"]), input')].map(control => [control, control.disabled]);
                controls.forEach(([control]) => { control.disabled = true; });
                let completed = false;
                let ceremony = null;
                try {
                    let result;
                    if (mode === 'login' || creating) {
                        if (creating && !onboarding && Number(box.dataset.passkeyCount || 0) === 0 && box.dataset.passkeyGrant !== '1') {
                            throw new Error('Sign in again before adding a passkey.');
                        }
                        if (creating && !onboarding && Number(box.dataset.passkeyCount || 0) > 0) {
                            const proof = await confirmPasskey(box, data, controller, status);
                            ceremony = proof.confirm;
                            Object.assign(data, proof);
                        }
                        const begin = await request(box, creating ? 'passkeyBeginCreate' : 'passkeyBeginLogin', data, controller.signal);
                        ceremony = begin.ceremony;
                        if (controller.signal.aborted || !box.isConnected) throw new DOMException('Cancelled', 'AbortError');
                        if (mode === 'login') loginFeedback(box, 'working', 'Continue on your device …');
                        const args = {publicKey: options(begin.options), signal: controller.signal};
                        const credential = creating ? await navigator.credentials.create(args) : await navigator.credentials.get(args);
                        if (!credential || !box.isConnected) throw new DOMException('Cancelled', 'AbortError');
                        const payload = {...credentialData(credential), ceremony};
                        result = await request(box, creating ? 'passkeyFinishCreate' : 'passkeyFinishLogin', payload);
                    } else {
                        if (mode === 'remove' && box.dataset.passkeyOwn !== '0' && box.dataset.passkeyGrant !== '1') {
                            const proof = await confirmPasskey(box, data, controller, status);
                            ceremony = proof.confirm;
                            Object.assign(data, proof);
                        }
                        result = await request(box, mode === 'rename' ? 'passkeyRename' : 'passkeyRemove', data, controller.signal);
                    }
                    if (result.redirect) {
                        completed = true;
                        loginFeedback(box, 'success', onboarding ? 'Passkey added.' : 'Signed in.');
                        if (mode === 'login' && !onboarding) revealSignedIn(box);
                        await new Promise(resolve => setTimeout(resolve, mode === 'login' && !onboarding ? 2000 : 600));
                        if (box.isConnected && !controller.signal.aborted) window.location.assign(result.redirect);
                    } else if (result.html && box.isConnected) {
                        if (creating && dialog?.open) {
                            setStep(dialog, 'success');
                            await new Promise(resolve => window.setTimeout(resolve, 900));
                            dialog.close();
                        }
                        if (!box.isConnected) return;
                        const template = document.createElement('template');
                        template.innerHTML = result.html.trim();
                        const next = template.content.firstElementChild;
                        box.replaceWith(next);
                        const notified = mode === 'remove' ? await notifyRemoval(result.removedCredential) : false;
                        setStatus(statusTarget(next), mode === 'remove' ? (notified ? 'Passkey removed. Your password manager was notified.' : 'Passkey removed. Also remove it from your password manager if it is still listed.') : creating ? 'Passkey added.' : 'Name saved in this CMS. Rename the entry in your password manager separately.', 'confirm');
                    }
                } catch (error) {
                    if (creating) setStep(dialog, 'ready');
                    if (onboarding) box.dataset.step = 'ready';
                    if (mode === 'login') restoreLogin(box);
                    const target = creating && dialog && !dialog.open ? cardStatus : status;
                    setStatus(target, ['NotAllowedError', 'AbortError'].includes(error.name) ? 'Cancelled or timed out. You can try again.' : error.message, ['NotAllowedError', 'AbortError'].includes(error.name) ? 'notice' : 'error');
                    await request(box, 'passkeyCancel', {ceremony}).catch(() => {});
                } finally {
                    observer.disconnect();
                    controls.forEach(([control, disabled]) => { control.disabled = disabled; });
                    box.classList.remove('is-pending');
                    box.removeAttribute('aria-busy');
                    if (pending?.controller === controller) pending = null;
                    if (!completed && mode === 'login' && box.isConnected) scheduleAutofill();
                }
            } finally { starting = false; }
        };
        this.startIfAuto = () => {
            const box = document.querySelector('.passkeyLogin[data-passkey-auto]');
            if (!box || box.dataset.passkeyStarted) return;
            const button = box.querySelector('[data-passkey-mode="login"]');
            if (!button) return;
            box.dataset.passkeyStarted = '1';
            this.run({element: button});
        };
    });
})();
