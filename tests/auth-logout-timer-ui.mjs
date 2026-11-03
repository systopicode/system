import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const html=execFileSync(process.env.FLUXO_PHP || 'php',['-r',`class http {public static $root='https://fluxo.test/';} include '${process.cwd()}/../system/panels/admin/auth/loggedOut.tpl.php';`],{encoding:'utf8'});
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for(const mode of ['automatic','click','replaced']) {
  const page=await browser.newPage();const errors=[];let redirects=0;
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://fluxo.test/**',async route=>{redirects++;await route.fulfill({body:'Login methods'});});
  await page.clock.install();await page.setContent(html);
  await page.clock.runFor(3000);
  if(mode==='click') {
   // Simulate the router consuming the link without unloading the document.
   await page.locator('a.button').evaluate(a=>a.addEventListener('click',e=>e.preventDefault()));
   await page.locator('a.button').click();
  } else if(mode==='replaced') await page.locator('main').evaluate(el=>el.remove());
  await page.clock.runFor(31000);
  if(mode==='automatic') await page.waitForURL('https://fluxo.test/admin/auth/?choose=1');
  assert.equal(redirects,mode==='automatic'?1:0,mode);
  assert.deepEqual(errors,[]);
  await page.close();
 }
 console.log('PASS logout timer: automatic redirect, early Sign in again cancels timer, AJAX replacement prevents stale redirect');
} finally {await browser.close();}
