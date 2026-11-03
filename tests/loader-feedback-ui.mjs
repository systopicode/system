import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 await page.setContent('<div class="cmsLoadState" hidden><i></i><span></span><button>Reload</button></div>');
 await page.evaluate(()=>window.sys={core:{}});
 await page.addScriptTag({path:'public/js/loader.js'});
 await page.evaluate(()=>sys.loader.status('loading'));
 await page.waitForTimeout(200);
 assert.equal(await page.locator('.cmsLoadState').isVisible(),false,'fast loads do not flash workspace notice');
 await page.evaluate(()=>sys.loader.status('ready'));
 await page.waitForTimeout(400);
 assert.equal(await page.locator('.cmsLoadState').isVisible(),false,'cancelled reveal never appears after ready');
 await page.evaluate(()=>sys.loader.status('loading'));
 await page.waitForTimeout(550);
 assert.equal(await page.locator('.cmsLoadState').isVisible(),true,'slow workspace initialization is visible');
 await page.evaluate(()=>sys.loader.status('error'));
 assert.equal(await page.locator('.cmsLoadState button').isVisible(),true,'failure exposes reload');
 await page.evaluate(()=>sys.loader.status('ready'));
 assert.equal(await page.locator('.cmsLoadState').isVisible(),false,'success ends notice');
 console.log('PASS workspace notice: delayed reveal, no stale reveal, slow loading, failure and completion');
} finally { await browser.close(); }
