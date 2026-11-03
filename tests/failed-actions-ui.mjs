import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 await page.route('https://fixture.test/**',route=>route.fulfill({contentType:'text/html',body:'<body class=cms><input data-on_blur=save value=old><footer class=cmsStatusbar><span class=cmsSaveState hidden></span></footer><table><tbody data-request-failures></tbody></table></body>'}));
 await page.goto('https://fixture.test/admin/pages/?id=42&token=secret&password=secret');
 await page.evaluate(()=>{window.$=()=>({keydown(){}});});
 await page.addScriptTag({path:'public/js/gui/ui.js'});
 await page.locator('input').fill('Do not record this value');
 await page.evaluate(()=>{const t=cmsFeedback.begin({event:{target:document.querySelector('input'),type:'blur'}},'put');cmsFeedback.finish(t,false);});
 const entries=await page.evaluate(()=>cmsFeedback.failures());
 assert.equal(entries.length,1);
 assert.equal(entries[0].url,'https://fixture.test/admin/pages/?id=42');
 assert.ok(!JSON.stringify(entries).includes('secret'));
 assert.ok(!JSON.stringify(entries).includes('Do not record'));
 await page.addScriptTag({path:'panels/admin/090_tools/logs/failed/panel.js'});
 assert.equal(await page.locator('[data-request-failures] tr').count(),1);
 assert.equal(await page.locator('[data-request-failures] a').getAttribute('href'),entries[0].url);
 console.log('ok: browser failure overview, sanitized page URL, no field values or secrets');
} finally {await browser.close();}
