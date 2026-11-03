import fs from 'node:fs';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const css=['00_theme','18_ui','19_appearance','20_base','24_table','27_tree','50_forms','60_lightbox','70_components','75_passkeys'].map(n=>fs.readFileSync(`public/css/${n}.css`,'utf8')).join('\n')+fs.readFileSync('panels/admin/auth/auth.css','utf8');
 await page.setContent(`<style>${css}</style><body class=cms><div class="authCard cms"><main class="authSignin is-passkey-signing-in"><div class=authPane><div class=passkeyLogin><div class=passkeyAuthFeedback>Old lock animation</div></div></div><div class="authSuccess" data-auth-success hidden><h1 data-success-title></h1><img data-success-portrait hidden><p class="authLead" data-success-name></p><p class="authWait">Redirecting in 2 seconds …</p></div><p class="authFoot" data-success-footer hidden><a class="authSkip" data-success-continue>Continue now</a></p></main></div><div class="tableScroll canScrollX"><table><tr><td>Content</td><td class=icons>Action</td></tr></table></div><div class="cms message is-stay"><div class=warning><p class=message>Backup warning</p></div></div></body>`);
 await page.evaluate(()=>window.sys={lib:{add(setup,constructor){const instance={};constructor.call(instance);window.sys[setup.shorthand]=instance;}}});
 await page.addScriptTag({path:'src/Auth/passkeys.js'});
 await page.evaluate(()=>sys.passkeys.showSignedIn(document.querySelector('.passkeyLogin'),{name:'Fixture',screen:false},'/admin/'));
 assert.ok(await page.locator('[data-auth-success]').isVisible());assert.ok(await page.locator('[data-success-footer]').isVisible());assert.equal(await page.locator('.passkeyAuthFeedback').isVisible(),false);
 assert.equal(await page.locator('[data-success-name]').textContent(),'Welcome, Fixture!');
 assert.equal(await page.locator('[data-success-continue]').getAttribute('href'),'/admin/');
 const style=await page.locator('td.icons').evaluate(e=>({opacity:getComputedStyle(e,'::before').opacity,overflow:getComputedStyle(e.closest('.tableScroll')).overscrollBehaviorX}));
 assert.equal(style.opacity,'1');assert.equal(style.overflow,'none');
 const icon=await page.locator('.warning').evaluate(e=>({icon:getComputedStyle(e,'::before').color,text:getComputedStyle(e).color}));assert.equal(icon.icon,icon.text);
 await page.evaluate(()=>{const d=document.createElement('dialog');d.className='cms cmslightbox';d.innerHTML='<section><header>Preview</header><main>No data was deleted.</main></section>';document.body.append(d);d.showModal();});
 assert.ok(await page.locator('dialog.cmslightbox section').evaluate(e=>e.getBoundingClientRect().height)>50,'modern native lightbox must not inherit legacy zero-height dialog rules');
 await page.locator('dialog').evaluate(e=>e.remove());
 assert.ok(!fs.readFileSync('src/Auth/signed-in.tpl.php','utf8').includes('authTick'),'shared success card has no check icon');
 assert.deepEqual(errors,[]);
 await page.screenshot({path:'/private/tmp/fluxo-block1-followup.png'});
 console.log('ok: passkey completion replaces lock state; sticky shadow fallback; horizontal overscroll disabled; warning icon uses message text colour');
} finally {await browser.close();}
