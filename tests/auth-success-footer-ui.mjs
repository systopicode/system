import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 const css=await readFile('public/css/18_ui.css','utf8')+await readFile('public/css/20_base.css','utf8')+await readFile('public/css/50_forms.css','utf8')+await readFile('panels/cms/auth/auth.css','utf8');
 const html=execFileSync('php',['tests/auth-screen-fixture.php','password'],{encoding:'utf8'});
 await page.setContent(`<style>${css}</style><body class="cms"><div class="authCard">${html}</div></body>`);
 assert.equal(await page.locator('[data-success-footer]').isVisible(),false);
 await page.evaluate(()=>{window.sys={lib:{add(config,ctor){const x={};ctor.call(x);sys[config.shorthand]=x;}}};});
 await page.addScriptTag({path:'src/Auth/passkeys.js'});
 await page.evaluate(()=>sys.passkeys.showSignedIn(document.querySelector('.authPane'),{name:'Fixture User',portrait:'/portrait.png'},'/original/page/'));
 assert.equal(await page.locator('[data-success-footer]').isVisible(),true);
 assert.equal(await page.locator('[data-success-continue]').getAttribute('href'),'/original/page/');
 assert.equal(await page.locator('[data-success-name]').textContent(),'Welcome, Fixture User!');
 assert.equal(await page.locator('.authProgress span').evaluate(el=>getComputedStyle(el).animationDuration),'2s');
 for (const width of [390,768,1440]) {
  await page.setViewportSize({width,height:1000});
  const rects=await page.evaluate(()=>{const card=document.querySelector('.authCard').getBoundingClientRect(),foot=document.querySelector('[data-success-footer]').getBoundingClientRect();return {cardBottom:card.bottom,footBottom:foot.bottom};});
  assert.ok(Math.abs(rects.cardBottom-rects.footBottom)<3,`footer stays at card bottom at ${width}: ${JSON.stringify(rects)}`);
 }
 console.log('ok: success footer hidden before login, shown after login, preserves destination and two-second animation');
} finally {await browser.close();}
