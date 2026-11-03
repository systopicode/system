import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const read=p=>readFile(new URL('../'+p,import.meta.url),'utf8');
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 const css=(await Promise.all(['public/css/20_base.css','public/css/50_forms.css','public/css/27_tree.css'].map(read))).join('\n');
 await page.setContent(`<style>${css}</style><body class="cms"><ul class="tree" style="width:340px"><li><label><a class="fold"></a><button class="label"><i class="type">▯</i>ABOUT</button></label></li><li><label><a class="fold"></a><span class="treeLabelEdit"><i class="type">▯</i><span class="label" contenteditable="plaintext-only">ABOUT</span></span></label></li></ul></body>`);
 const shapes=await page.locator('ul.tree >li').evaluateAll(rows=>rows.map(row=>{const icon=row.querySelector('i.type'),rect=icon.getBoundingClientRect();return {height:row.getBoundingClientRect().height,iconLeft:rect.left,iconWidth:rect.width,font:getComputedStyle(icon).fontSize};}));
 assert.ok(Math.abs(shapes[0].height-shapes[1].height)<=1,JSON.stringify(shapes));
 assert.equal(shapes[0].iconLeft,shapes[1].iconLeft);assert.equal(shapes[0].iconWidth,shapes[1].iconWidth);assert.equal(shapes[0].font,shapes[1].font);
 await page.locator('[contenteditable]').fill('A very long page title '.repeat(15));
 await page.locator('[contenteditable]').press('End');
 const bounds=await page.locator('[contenteditable]').evaluate(el=>({right:el.getBoundingClientRect().right,parent:el.closest('ul.tree').getBoundingClientRect().right,overflow:getComputedStyle(el).overflowX}));
 assert.ok(bounds.right<=bounds.parent+1,'long editable title stays inside tree');
 assert.equal(bounds.overflow,'hidden');
 console.log('ok: tree label editing retains row/icon geometry; long title clips and remains editable');
} finally {await browser.close();}
