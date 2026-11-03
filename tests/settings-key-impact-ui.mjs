import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 await page.setContent('<input value=original data-key-impact="Rename key?" pattern="[A-Za-z_]+"><button>Elsewhere</button>');
 await page.evaluate(()=>{window.allowed=false;window.confirm=()=>allowed;window.writes=[];document.querySelector('input').addEventListener('blur',e=>writes.push(e.target.value));});
 await page.addScriptTag({path:'panels/admin/030_settings/panel.js'});
 await page.locator('input').fill('new_key');await page.locator('button').click();
 assert.equal(await page.locator('input').inputValue(),'original');
 assert.deepEqual(await page.evaluate(()=>writes),[],'cancel sends no save');
 await page.evaluate(()=>allowed=true);
 await page.locator('input').fill('new_key');await page.locator('button').click();
 assert.deepEqual(await page.evaluate(()=>writes),['new_key'],'accept uses existing blur save once');
 console.log('ok: key impact confirmation precedes autosave; cancellation restores original without writing');
}finally{await browser.close();}
