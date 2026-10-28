/** UI-state checks against HTML rendered by tests/passkeys.php; no real authenticator. */
import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';

const {Window} = await import(pathToFileURL(process.env.FLUXO_DOM_MODULE || 'node_modules/happy-dom/lib/index.js').href);
const fixture = await readFile(process.env.FLUXO_TEST_HTML, 'utf8');
const source = await readFile(new URL('../src/Auth/passkeys.js', import.meta.url), 'utf8');
const window = new Window({url:'https://cms.example.test/'});
const document = window.document;
window.sys = {lib:{add(setup, construct) { const instance = {}; construct.call(instance); window.sys[setup.shorthand] = instance; }}};
window.PublicKeyCredential = function () {};
Object.defineProperty(window, 'isSecureContext', {value:true});
window.confirm = () => true;
document.body.innerHTML = fixture;
const bytes = Uint8Array.from([1,2,3,4]);
let complete, completeGet, lastSignal, calls = [], rejectCreate = false;
const credential = () => ({rawId:bytes.buffer,response:{clientDataJSON:bytes.buffer,attestationObject:bytes.buffer,authenticatorData:bytes.buffer,signature:bytes.buffer,userHandle:null}});
Object.defineProperty(window.navigator, 'credentials', {value:{
    create({publicKey,signal}) {
        assert.ok(publicKey.challenge instanceof window.Uint8Array || publicKey.challenge instanceof Uint8Array);
        lastSignal = signal;
        return new Promise((resolve,reject) => {
            complete = () => resolve(credential());
            signal.addEventListener('abort', () => reject(new window.DOMException('Cancelled','AbortError')), {once:true});
        });
    },
    get({publicKey,signal}) {
        assert.ok(publicKey.challenge instanceof window.Uint8Array || publicKey.challenge instanceof Uint8Array);
        lastSignal = signal;
        return new Promise((resolve,reject) => {
            completeGet = () => resolve(credential());
            signal.addEventListener('abort', () => reject(new window.DOMException('Cancelled','AbortError')), {once:true});
        });
    }
}});
window.fetch = async (url, init) => {
    const data = JSON.parse(init.body); calls.push({url,data});
    if (rejectCreate && (url.endsWith('passkeyBeginCreate') || url.endsWith('passkeyBeginConfirm'))) return {ok:false,json:async()=>({error:'Sign in again before adding a passkey.'})};
    if (url.endsWith('passkeyBeginCreate') || url.endsWith('passkeyBeginConfirm')) return {ok:true,json:async()=>({options:{challenge:'AQIDBA',user:{id:'AQIDBA'}},ceremony:'a'.repeat(32)})};
    if (url.endsWith('passkeyFinishCreate') || url.endsWith('passkeyRename') || url.endsWith('passkeyRemove')) return {ok:true,json:async()=>({html:fixture})};
    return {ok:true,json:async()=>({ok:true})};
};
window.eval(source);
const run = selector => window.sys.passkeys.run({element:document.querySelector(selector)});
const dialog = () => document.querySelector('.passkeySetup');
const turn = () => new Promise(resolve=>setTimeout(resolve,0));
const check = (label, condition) => {assert.ok(condition,label);console.log('ok: '+label);};

await run('[data-passkey-mode=setup]');
check('setup opens a modal with ready artwork', dialog().open && dialog().dataset.step === 'ready');
check('the setup dialog has no password field', !dialog().querySelector('[data-passkey-password]'));
rejectCreate=true;
await run('[data-passkey-mode=create]');
check('server error restores setup', dialog().dataset.step === 'ready' && !document.querySelector('.is-pending') && dialog().querySelector('.passkeyStatus').textContent.includes('Sign in again'));
rejectCreate=false;
const creating=run('[data-passkey-mode=create]');
await turn();
if (calls.some(c => c.url.endsWith('passkeyBeginConfirm'))) { completeGet(); await turn(); }
check('scan artwork appears only in the setup ceremony', dialog().dataset.step === 'working' && !document.querySelector('.passkeyHeadingIcon .passkeyIcon-scan'));
check('adding a passkey does not send a password', calls.some(c => c.url.endsWith('passkeyBeginCreate') && c.data.password === undefined));
complete();
await turn();
check('registration sends the ceremony identifier', calls.some(c=>c.url.endsWith('passkeyFinishCreate')&&c.data.ceremony==='a'.repeat(32)));
check('success artwork waits for server verification', dialog().dataset.step === 'success' && calls.some(c=>c.url.endsWith('passkeyFinishCreate')));
await creating;
check('successful setup replaces the component and removes the busy state', !dialog().open && !document.querySelector('.is-pending') && document.querySelector('.passkeyStatus').textContent === 'Passkey added.');

await run('[data-passkey-mode=setup]');
const cancelled=run('[data-passkey-mode=create]');
await turn();
if (calls.at(-1)?.url.endsWith('passkeyBeginConfirm')) { completeGet(); await turn(); }
await run('[data-passkey-mode=cancel]');
await cancelled;
check('cancel closes the modal, aborts authentication and restores controls', !dialog().open && lastSignal.aborted && !document.querySelector('[data-passkey-mode=setup]').disabled);
check('cancel consumes the server challenge', calls.at(-1).url.endsWith('passkeyCancel'));

const item=document.querySelector('.passkeyItem');item.open=true;
item.querySelector('[data-passkey-name]').value='Renamed key';
await run('[data-passkey-mode=rename]');
check('rename sends the name without a password', calls.at(-1).data.name === 'Renamed key' && calls.at(-1).data.password === undefined);
let removalSignals=[];
window.PublicKeyCredential.signalUnknownCredential=async signal=>removalSignals.push(signal);
window.fetch=async(url,init)=>{
 const data=JSON.parse(init.body); calls.push({url,data});
 if(url.endsWith('passkeyBeginCreate') || url.endsWith('passkeyBeginConfirm')) return {ok:true,json:async()=>({options:{challenge:'AQIDBA',user:{id:'AQIDBA'}},ceremony:'a'.repeat(32)})};
 return {ok:true,json:async()=>({html:fixture,removedCredential:{rpId:'cms.example.test',credentialId:'AQIDBA'}})};
};
const removing = run('[data-passkey-mode=remove]');
await turn();
if (calls.at(-1)?.url.endsWith('passkeyBeginConfirm')) { completeGet(); await turn(); }
await removing;
check('removal does not ask for a password', calls.at(-1).url.endsWith('passkeyRemove') && calls.at(-1).data.password === undefined);
check('successful removal signals only the revoked credential to the manager',removalSignals.length===1&&removalSignals[0].credentialId==='AQIDBA'&&document.querySelector('.passkeyStatus').textContent.includes('was notified'));
window.PublicKeyCredential.signalUnknownCredential=async()=>{throw new Error('Unsupported provider');};
const removingAgain = run('[data-passkey-mode=remove]');
await turn();
if (calls.at(-1)?.url.endsWith('passkeyBeginConfirm')) { completeGet(); await turn(); }
await removingAgain;
check('manager failure preserves CMS removal and explains manual cleanup',document.querySelector('.passkeyStatus').textContent.includes('Also remove it'));
await run('[data-passkey-mode=setup]');
const detached=run('[data-passkey-mode=create]');
await turn();
if (calls.at(-1)?.url.endsWith('passkeyBeginConfirm')) { completeGet(); await turn(); }
document.querySelector('.passkeys').remove();await turn();await detached;
check('panel removal aborts the outstanding ceremony',lastSignal.aborted);
// Both login paths share verification; automatic attempts must never cancel a button attempt.
const loginFixture = await readFile(process.env.FLUXO_TEST_LOGIN_HTML, 'utf8');
let gets = [], redirects = [], finishLogin;
window.location.assign = path => redirects.push(path);
window.PublicKeyCredential.isConditionalMediationAvailable = async () => true;
window.navigator.credentials.get = args => new Promise((resolve,reject) => {
    gets.push({...args, resolve});
    args.signal.addEventListener('abort',()=>reject(new window.DOMException('Cancelled','AbortError')),{once:true});
});
window.fetch = async (url,init) => {
    calls.push({url,data:JSON.parse(init.body)});
    if(url.endsWith('passkeyBeginLogin')) return {ok:true,json:async()=>({options:{challenge:'AQIDBA',rpId:'cms.example.test'},ceremony:'b'.repeat(32)})};
    if(url.endsWith('passkeyFinishLogin')) return new Promise(resolve=>{finishLogin=()=>resolve({ok:true,json:async()=>({redirect:'/cms/'})});});
    return {ok:true,json:async()=>({ok:true})};
};
document.body.innerHTML=loginFixture;
window.sys.passkeys.attachAutofill();window.sys.passkeys.attachAutofill();
await turn();await turn();await turn();
check('conditional autofill starts once without showing login feedback',gets.length===1&&gets[0].mediation==='conditional'&&document.querySelector('.passkeyAuthFeedback').hidden);
check('login identifier is annotated for WebAuthn autofill',document.querySelector('[name=username]').autocomplete==='username webauthn');
document.querySelector('[name=username]').value='';
const buttonLogin=run('[data-passkey-mode=login]');
const duplicateLogin=run('[data-passkey-mode=login]'); await duplicateLogin;
await turn();await turn();
check('explicit button works without email and cancels autofill before starting a modal request',gets[0].signal.aborted&&gets.length===2&&!gets[1].mediation);
check('login switches to a dedicated animation view',document.querySelector('main').classList.contains('is-passkey-signing-in'));
check('real login request activates auth animation',document.querySelector('.passkeyLogin').dataset.step==='working'&&!document.querySelector('.passkeyAuthFeedback').hidden);
await run('[data-passkey-mode=cancel]');await buttonLogin;await turn();await turn();
check('auth uses the shared top message container without a second component banner',document.querySelector('.authMessages [data-system-messages]')&&!document.querySelector('.passkeyLogin .passkeyStatus'));
check('cancel restores the ordinary login view and uses the message banner',!document.querySelector('main').classList.contains('is-passkey-signing-in')&&document.querySelector('[data-system-messages] > .notice'));
check('cancelled button login restores conditional autofill',gets.length===3&&gets[2].mediation==='conditional');
gets[2].resolve({rawId:bytes.buffer,response:{clientDataJSON:bytes.buffer,authenticatorData:bytes.buffer,signature:bytes.buffer,userHandle:bytes.buffer}});
await turn();
check('autofill selection shows working feedback during verification',document.querySelector('.passkeyLogin').dataset.step==='working');
finishLogin();await turn();
check('autofill sends its own ceremony identifier', calls.some(c=>c.url.endsWith('passkeyFinishLogin')&&c.data.ceremony==='b'.repeat(32)));
check('verified autofill selection shows success animation',document.querySelector('.passkeyLogin').dataset.step==='success');
await new Promise(resolve=>setTimeout(resolve,650));
check('autofill uses the existing login redirect',redirects[0]==='/cms/');
document.body.innerHTML='';await turn();
const before=gets.length;
window.PublicKeyCredential.isConditionalMediationAvailable=async()=>false;
document.body.innerHTML=loginFixture;window.sys.passkeys.attachAutofill();await turn();await turn();
check('unsupported autofill leaves a usable visible login button',gets.length===before&&!document.querySelector('[data-passkey-mode=login]').disabled);
document.body.innerHTML='';await turn();
const onboardingFixture=await readFile(process.env.FLUXO_TEST_ONBOARDING_HTML,'utf8');
document.body.innerHTML=onboardingFixture;
window.fetch=async(url,init)=>{
 const data=JSON.parse(init.body); calls.push({url,data});
 if(url.endsWith('passkeyBeginCreate')) return {ok:true,json:async()=>({options:{challenge:'AQIDBA',user:{id:'AQIDBA'}},ceremony:'a'.repeat(32)})};
 return {ok:true,json:async()=>({redirect:'/cms/'})};
};
const onboard=run('[data-passkey-mode=create]'); await turn();
check('onboarding starts registration without another password field', document.querySelector('.passkeyOnboarding').dataset.step==='working' && !calls.at(-1).data.password);
complete(); await turn();
check('onboarding shows verified success',document.querySelector('.passkeyOnboarding').dataset.step==='success');
await onboard;
check('onboarding follows the server redirect',redirects.at(-1)==='/cms/');
document.body.innerHTML=onboardingFixture;
window.PublicKeyCredential=undefined;
const skip=document.querySelector('.passkeySkip a');
check('skip is ordinary navigation independent of WebAuthn and Origin validation',skip&&skip.getAttribute('href')==='/cms/auth/continueWithoutPasskey'&&!skip.dataset.on_click&&!document.querySelector('[data-passkey-mode=skip]'));
document.body.innerHTML=loginFixture;
window.PublicKeyCredential=function(){};
window.navigator.credentials.get=async()=>{throw new window.DOMException('No matching passkey','NotAllowedError');};
window.fetch=async(url,init)=>{
 calls.push({url,data:JSON.parse(init.body)});
 return {ok:true,json:async()=>url.endsWith('passkeyBeginLogin')?{options:{challenge:'AQIDBA',rpId:'cms.example.test'},ceremony:'b'.repeat(32)}:{ok:true}};
};
const retryStart=calls.filter(c=>c.url.endsWith('passkeyBeginLogin')).length;
await run('[data-passkey-mode=login]');
await run('[data-passkey-mode=login]');
check('no matching passkey can be retried immediately with a new ceremony',calls.filter(c=>c.url.endsWith('passkeyBeginLogin')).length===retryStart+2&&!document.querySelector('main').classList.contains('is-passkey-signing-in')&&document.querySelector('[data-system-messages] > .notice'));
document.body.innerHTML=loginFixture;
const mismatch=document.querySelector('.passkeyLogin');mismatch.dataset.passkeyOrigin='https://different.example.test';
const previous=calls.length;
await run('[data-passkey-mode=login]');
check('IP or alternate origin shows a useful message without a failing API request',calls.length===previous&&document.querySelector('[data-system-messages]').textContent.includes('https://different.example.test')&&!document.querySelector('main').classList.contains('is-passkey-signing-in'));
await run('[data-passkey-mode=login]');
check('origin warning remains recoverable on retry',!mismatch.getAttribute('aria-busy')&&!document.querySelector('[data-passkey-mode=login]').disabled);
document.body.innerHTML=''; await turn();
await window.happyDOM.abort();
console.log('All passkey UI-state checks passed.');
