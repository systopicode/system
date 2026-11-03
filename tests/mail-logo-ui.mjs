import {readFile} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const html=execFileSync('php',['tests/mail-logo-fixture.php'],{encoding:'utf8'});
assert(!html.includes('mailLogoPlate'));
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage(); const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://fixture.test/**',async route=>route.fulfill({contentType:'image/png',body:await readFile('public/img/'+new URL(route.request().url()).pathname.split('/').pop())}));
 await page.setContent(html);
 for(const width of [1280,834,390]) {
  await page.setViewportSize({width,height:800});
  for(const mode of ['light','dark','light']) {
   await page.emulateMedia({colorScheme:mode});
   const visible=page.locator(mode==='light'?'.mailLogoLight':'.mailLogoDark');
   await visible.waitFor({state:'visible'});
   assert(await visible.locator('img').evaluate(img=>img.complete && img.naturalWidth>0));
   assert.equal(await page.locator(mode==='light'?'.mailLogoDark':'.mailLogoLight').isVisible(),false);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   await page.screenshot({path:`/private/tmp/fluxo-mail-logo-${mode}-${width}.png`});
  }
 }
 assert.deepEqual(errors,[]);
 console.log('PASS real Letter markup: light/dark/light without reload at 1280/834/390; correct loaded logo, no plate or overflow');
} finally {await browser.close();}
