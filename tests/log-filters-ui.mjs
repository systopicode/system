import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 await page.setContent(`<section class=logList><input class=listSearch><div class=logFilters><input data-log-from type=date><input data-log-until type=date><input data-log-user><select data-log-result><option value=""></option><option value=success>Successful</option><option value=error>Failed</option></select><button data-log-reset>Reset</button><small data-log-count></small></div><table><tbody>
 <tr data-log-time="${Date.parse('2026-01-01T12:00:00')/1000}" data-log-user=Alice data-log-result=success><td>Alpha</td></tr>
 <tr data-log-time="${Date.parse('2026-02-01T12:00:00')/1000}" data-log-user=Bob data-log-result=error><td>Beta</td></tr>
 <tr data-log-time="" data-log-user=Alice data-log-result=error><td>Undated</td></tr>
 </tbody></table></section>`);
 await page.addScriptTag({path:'panels/admin/090_tools/logs/panel.js'});
 const shown=()=>page.locator('tbody tr:not([hidden])').count();
 assert.equal(await shown(),3);
 await page.locator('input[data-log-user]').fill('alice');assert.equal(await shown(),2);
 await page.locator('select[data-log-result]').selectOption('error');assert.equal(await shown(),1);
 await page.locator('[data-log-from]').fill('2026-01-01');assert.equal(await shown(),0,'undated rows excluded from a bounded period');
 await page.locator('[data-log-reset]').click();assert.equal(await shown(),3);
 await page.locator('.listSearch').fill('beta');assert.equal(await shown(),1);
 await page.locator('[data-log-reset]').click();
 await page.locator('[data-log-until]').fill('2026-01-15');assert.equal(await shown(),1);
 console.log('ok: combined date/user/result/search filters, reset and undated entries');
}finally{await browser.close();}
