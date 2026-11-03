import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
const page=await browser.newPage();let remote=0;
await page.route('https://tracking.example/**',route=>{remote++;return route.abort();});
await page.setContent(await readFile('/private/tmp/fluxo-mail-preview.html','utf8'));
await page.waitForLoadState('networkidle');
assert.equal(await page.evaluate(()=>window.compromised),undefined);
assert.equal(remote,0,'mail preview cannot load a tracking image');
assert.equal(await page.frameLocator('iframe').locator('b').innerText(),'Message body');
console.log('ok: logged HTML retains formatting but scripts and remote tracking images are blocked');
}finally{await browser.close();}
