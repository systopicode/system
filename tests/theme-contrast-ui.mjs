import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try{
 const page=await browser.newPage();
 await page.setContent('<style>:root{--cmsTextColor:#000;--cmsCanvas:#fff}</style><dl data-contrast-live><dd data-contrast-result></dd></dl>');
 await page.addScriptTag({path:'panels/admin/030_settings/032_theme/panel.js'});
 assert.ok((await page.locator('dd').textContent()).trim().startsWith('21.00:1'));
 await page.evaluate(()=>document.documentElement.style.setProperty('--cmsTextColor','#fff'));
 await page.waitForFunction(()=>document.querySelector('dd').textContent.trim().startsWith('1.00:1'));
 assert.ok((await page.locator('dd').textContent()).includes('Low contrast'));
 await page.evaluate(()=>document.documentElement.style.setProperty('--cmsCanvas','color-mix(in srgb, #000 95%, #fff)'));
 await page.waitForFunction(()=>document.querySelector('dd').textContent.includes('Sufficient'));
 console.log('ok: effective colour contrast follows light/dark changes and CSS colour-mix');
}finally{await browser.close();}
