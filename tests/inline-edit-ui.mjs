import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const read=p=>readFile(new URL(p,import.meta.url),'utf8');
const template=await read('../panels/admin/body.tpl.php');
const tail=template.slice(template.indexOf('var storedDevice'),template.indexOf('</script>',template.indexOf('var storedDevice')));
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://fixture.test/**',r=>r.fulfill({body:'<html></html>'}));await page.goto('https://fixture.test/');
 await page.setContent('<style>.cmsIcon-pen{display:inline-block;width:20px;height:20px}</style><body class="cms"><main><h1 class="pageTitle" contenteditable="false" data-on_blur="updateLabel">Original title</h1><button data-page-title-edit>Edit</button><ul class="tree"><li data-node_id="1"><label><span class="label" data-ready contenteditable="plaintext-only" data-on_blur="updateLabel">Tree title</span></label></li></ul><div id="setting">Setting name</div></main></body>');
 await page.addScriptTag({content:await read('../public/js/ext/jquery.js')});
 await page.evaluate(()=>{window.sys={core:{},lib:{add:(s,c)=>{window.editableConstructor=c;}}};window.calls=[];sys.core.server={sendCall:(d,a)=>calls.push(a)};window.l=window.f=window.p=()=>({});window.selectElementContents=el=>{el.focus();const r=document.createRange();r.selectNodeContents(el);getSelection().removeAllRanges();getSelection().addRange(r);};window.saveCaretPosition=()=>()=>{};});
 await page.addScriptTag({content:await read('../public/js/core/event/event.js')});
 await page.evaluate(()=>{$('[data-on_blur]').on('blur',sys.processEvent);});
 await page.evaluate('(function(){ var root=document.body;var mobile=matchMedia("(max-width:1024px)");'+tail);
 await page.locator('[data-page-title-edit]').click();
 await page.locator('.pageTitle').fill('Changed title');await page.locator('.pageTitle').press('Escape');
 assert.equal(await page.locator('.pageTitle').textContent(),'Original title');
 assert.equal(await page.locator('.pageTitle').getAttribute('contenteditable'),'false');
 assert.deepEqual(await page.evaluate(()=>calls),[],'cancel title never saves');
 // Tree focus is normally initiated after rendering the edit row.
 await page.locator('span.label').focus();await page.locator('span.label').fill('Changed tree');await page.locator('span.label').press('Escape');
 assert.equal(await page.locator('span.label').textContent(),'Tree title');
 assert.deepEqual(await page.evaluate(()=>calls),['cancelEditLabel'],'tree exits edit mode with a render only');
 await page.addScriptTag({content:await read('../lib/class/dom/editable/domEditable.js')});
 await page.evaluate(()=>{window.edits={};editableConstructor.call(edits);edits.create({element:document.querySelector('#setting'),onconfirm:()=>calls.push('saveSetting')});});
 await page.locator('#setting .cmsIcon-pen').click();await page.locator('#setting [contenteditable]').fill('Changed setting');await page.locator('#setting [contenteditable]').press('Escape');
 assert.equal(await page.locator('#setting > span').textContent(),'Setting name');
 assert.equal(await page.locator('#setting .cmsIcon-pen').count(),1);
 assert.deepEqual(await page.evaluate(()=>calls),['cancelEditLabel'],'setting cancel does not confirm through blur');
 assert.deepEqual(errors,[]);
 console.log('ok: title, tree and reusable setting editor restore old values on Escape without saving');
} finally {await browser.close();}
