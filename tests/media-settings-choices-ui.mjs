import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const css=await readFile(new URL('../panels/admin/030_settings/project.css',import.meta.url),'utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for (const width of [390,1200]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  for(const count of [1,2,3,4,5,6,17]) {
   await page.setContent(`<style>:root{--cmsLine:#ccc}${css}</style><div class="cms mediaTypes"><div class="mediaTypeChoices">${Array.from({length:count},(_,i)=>`<div class="mediaTypeChoice">Choice ${i}</div>`).join('')}</div></div>`);
   const styles=await page.locator('.mediaTypeChoice').evaluateAll(els=>els.map(el=>({top:getComputedStyle(el).borderTopWidth,bottom:getComputedStyle(el).borderBottomWidth})));
   const columns=width<640?1:2;
   styles.forEach((style,i)=>{assert.equal(style.bottom,'0px');assert.equal(style.top,i<columns?'0px':'1px');});
  }
  await page.close();
 }
 console.log('ok: row separators have no trailing border with odd/even counts, one item and mobile single-column layout');
} finally {await browser.close();}
