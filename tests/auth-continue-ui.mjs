import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const render=name=>execFileSync('php',[new URL('auth-screen-fixture.php',import.meta.url).pathname,name],{encoding:'utf8'});
const read=p=>readFile(new URL('../'+p,import.meta.url),'utf8');
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage({colorScheme:'dark'});
 const css=await read('public/css/50_forms.css')+await read('panels/cms/auth/auth.css');
 await page.setContent(`<style>${css}</style><body class="cms"><div class="authCard"><main><p class="authFoot"><a class="authSkip">Continue →</a></p><p class="authFoot"><a class="authBack">← Back</a></p></main></div></body>`);
 assert.equal(await page.locator('.authFoot').first().evaluate(e=>getComputedStyle(e).justifyContent),'flex-end');
 assert.equal(await page.locator('.authFoot').last().evaluate(e=>getComputedStyle(e).justifyContent),'flex-start');
 const head=await read('panels/cms/head.tpl.php');
 const start=head.indexOf('(function () {',head.indexOf('var choice')-100);
 const script=head.slice(start,head.indexOf('</script>',start)).replace('<?= json_encode($appearance) ?>','"system"').replace('<?= json_encode($darkGround) ?>','"#111111"').replace('<?= json_encode($canvasColor) ?>','"#ffffff"');
 await page.addScriptTag({content:script});
 assert.equal(await page.locator('html').getAttribute('data-mode'),'dark','system appearance applies on first CMS document');
 await page.emulateMedia({colorScheme:'light'});await page.waitForTimeout(100);
 assert.equal(await page.locator('html').getAttribute('data-mode'),null);
 // Render the real reset form without a project or sending email.
 const reset=render('resetPassword');
 await page.setContent(`<style>${css}</style><body class="cms"><div class="authCard">${reset}</div></body>`);
 assert.equal(await page.locator('.authLinks a').getAttribute('href'),'?method=password');
 assert.ok((await page.locator('.authFoot a').getAttribute('href')).endsWith('?method=choose'));
 // Force Chromium's autofill pseudo-state; author palette must cover its fill.
 await page.addStyleTag({content:':root{--cmsCanvas:#ffffff;--cmsTextColor:#172023}'});
 const cdp=await page.context().newCDPSession(page);await cdp.send('DOM.enable');await cdp.send('CSS.enable');
 const doc=await cdp.send('DOM.getDocument');const node=await cdp.send('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'input'});
 await cdp.send('CSS.forcePseudoState',{nodeId:node.nodeId,forcedPseudoClasses:['autofill']});await page.waitForTimeout(200);
 const fillStyle=await page.locator('input').evaluate(e=>({shadow:getComputedStyle(e).boxShadow,bg:getComputedStyle(e).backgroundColor,autofill:e.matches(':autofill')}));
 assert.ok(fillStyle.shadow.includes('rgb(255, 255, 255)'),JSON.stringify(fillStyle));
 assert.equal(await page.locator('input').evaluate(e=>getComputedStyle(e).webkitTextFillColor),'rgb(23, 32, 35)');
 // Auth controls use global font size; both inline-return links share spacing.
 await page.setContent(`<style>${css}</style><body class="cms"><div class="authCard cms"><main><button class="button">Type the code</button><button class="submit">Sign in</button><p class="authLinks"><a>Back to sign in</a></p><div class="authSuccess"><h1>Success</h1><p class="authLead">Welcome back!</p><p class="authWait">Redirecting</p></div></main></div></body>`);
 assert.equal(await page.locator('button').first().evaluate(e=>getComputedStyle(e).fontSize),await page.locator('button').last().evaluate(e=>getComputedStyle(e).fontSize));
 assert.equal(await page.locator('.authLinks').evaluate(e=>getComputedStyle(e).marginTop),'16px');
 for (const selector of ['h1','.authLead','.authWait']) assert.equal(await page.locator('.authSuccess '+selector).evaluate(e=>getComputedStyle(e).textAlign),'center');
 await page.setContent(`<div><div data-qr-host="login"><button data-qr-fields>Type the code</button><div data-qr-dim hidden><strong data-qr-dim-title></strong><p data-qr-dim-text></p><button data-qr-again>Allow access</button></div></div><form data-code-form hidden><input><button data-qr-start>Scan QR code</button></form></div>`);
 await page.evaluate(()=>{Object.defineProperty(navigator,'mediaDevices',{value:undefined,configurable:true});});
 await page.addScriptTag({content:await read('public/js/gui/qr-scan.js')});
 await page.evaluate(()=>QrScan.start(document.querySelector('[data-qr-host]')));
 assert.equal(await page.locator('[data-code-form]').evaluate(e=>e.hidden),false);
 assert.equal(await page.locator('[data-qr-fields]').evaluate(e=>e.hidden),true);
 assert.equal(await page.locator('[data-qr-start]').evaluate(e=>e.hidden),true);
 assert.equal(await page.locator('[data-qr-again]').isVisible(),true);
 // Compare the real surrounding form structures, including base margins.
 const password=render('password');
 const fullCss=await read('public/css/20_base.css')+css;
 const gaps=[];
 for(const markup of [password,reset]) {
  await page.setContent(`<style>${fullCss}</style><body class="cms"><div class="authCard cms">${markup}</div></body>`);
  gaps.push(await page.evaluate(()=>document.querySelector('.authLinks').getBoundingClientRect().top-document.querySelector('button.submit').getBoundingClientRect().bottom));
 }
 assert.ok(Math.abs(gaps[0]-gaps[1])<=1,'actual button-to-link gaps match: '+gaps);
 // Remote pairing uses the same success renderer and waits before navigation.
 await page.setContent('<div class="authCard"><main class="authSignin"><div class="authPane"><div data-screen-pairing>Remote code</div></div><div data-auth-success hidden>Successfully signed in</div></main></div>');
 await page.evaluate(()=>{
  window.sys={lib:{add(setup,ctor){if(setup.shorthand){const instance={};ctor.call(instance);sys[setup.shorthand]=instance;}else window.authCtor=ctor;},applyParentConstructor(){}}};
  window.fetch=async()=>({json:async()=>({paired:true,redirect:'/cms/'})});
  window.setInterval=fn=>{window.poll=fn;return 1;};
  const timeout=window.setTimeout;
  window.setTimeout=(fn,ms)=>{if(ms===2000){window.successDelay=ms;return 1;}return timeout(fn,ms);};
 });
 await page.addScriptTag({content:await read('src/Auth/passkeys.js')});
 await page.evaluate(()=>{document.querySelector('.authCard').insertAdjacentHTML('beforeend','<a class="authMethod" href="?method=passkey">Passkey</a>');});
 assert.equal(await page.evaluate(()=>sys.passkeys.checkSupport()),false,'unsupported context detected before ceremony');
 assert.equal(await page.locator('.authMethod').evaluate(e=>e.hidden),true);
 await page.evaluate(()=>{Object.defineProperty(window,'isSecureContext',{value:true,configurable:true});window.PublicKeyCredential=function(){};PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable=async()=>false;Object.defineProperty(navigator,'credentials',{value:{get:async()=>null},configurable:true});});
 assert.equal(await page.evaluate(()=>sys.passkeys.checkSupport()),true,'lack of platform biometrics does not block external authenticators');
 await page.addScriptTag({content:await read('panels/cms/auth/panel.js')});
 await page.evaluate(()=>{
  document.querySelector('.authCard').insertAdjacentHTML('beforeend','<input name="username" value="test@example.test"><p class="authLinks"><a class="ajax" href="?view=resetPassword">Forgot</a></p>');
  const link=document.querySelector('.authLinks a');link.addEventListener('click',e=>e.preventDefault());link.click();
 });

 await page.evaluate(()=>{window.authPanel={};authCtor.call(authPanel);poll();});await page.waitForTimeout(50);
 assert.equal(await page.locator('[data-auth-success]').evaluate(e=>e.hidden),false);
 assert.equal(await page.locator('.authPane').evaluate(e=>e.hidden),true);
 assert.equal(await page.evaluate(()=>successDelay),2000);
 await page.locator('.authCard').evaluate((e,html)=>{e.innerHTML=html;},reset);
 await page.evaluate(()=>authPanel.onUpdateMain());
 assert.equal(await page.locator('input[name="email_reset"]').evaluate(e=>e.value),'test@example.test','email carries into the rendered reset field without a URL or storage');
 // Full form layouts share the same footer offset and title placement.
 for(const width of [390,768,1440]) {
 await page.setViewportSize({width,height:900});
 const footers=[];const headings=[];
 for(const view of ['password','resetPassword','resetCode','activationCode','setPassword','register']) {
  await page.setContent(`<style>${fullCss}</style><body class="cms"><div class="authCard cms"><a class="logo">fluxo</a>${render(view)}</div></body>`);
  footers.push(await page.locator('.authFoot').evaluate(e=>e.getBoundingClientRect().bottom-e.closest('.authCard').getBoundingClientRect().bottom));
  headings.push(await page.locator('h1:visible').evaluate(e=>e.getBoundingClientRect().top-e.closest('.authCard').getBoundingClientRect().top));
 }
 assert.ok(footers.every(v=>Math.abs(v-footers[0])<=1),'stable footer across forms: '+footers);
 assert.ok(headings.every(v=>Math.abs(v-headings[0])<=1),'stable headline across forms: '+headings);
 }
 const timerGaps=[];
 for(const view of ['passkey','signedIn','loggedOut']) {
  await page.setContent(`<style>${fullCss}</style><body class="cms"><div class="authCard cms">${render(view)}</div></body>`);
  assert.ok((await page.locator('h1,.authLead,.authWait').evaluateAll(es=>es.filter(e=>!e.closest('[hidden]')).map(e=>getComputedStyle(e).textAlign))).every(v=>v==='center'),'illustrated screen centered: '+view);
  if(view!=='passkey') timerGaps.push(await page.locator('.authWait').evaluate(e=>e.getBoundingClientRect().top-e.previousElementSibling.getBoundingClientRect().bottom));
 }

 assert.ok(Math.abs(timerGaps[0]-timerGaps[1])<=1,'logout timer spacing matches sign-in: '+timerGaps);
 console.log('ok: Continue right, Back left; initial system dark mode, OS changes, reset navigation and autofill palette');
} finally {await browser.close();}
