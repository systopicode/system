import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 await page.setContent('<div data-node_id="10"><input name="text01" data-on_blur="updateTextfield" value="old"><textarea data-on_blur="sidebar/updateParam">old</textarea></div><div class="editor" contenteditable="true" data-on_blur="updatePageParam"><p>Before</p></div><button id=outside>Outside</button><input type=search data-on_input="search">');
 await page.addScriptTag({path:'public/js/ext/jquery.js'});
 await page.evaluate(()=>{
  window.sys={core:{},processEvent(e){window.calls.push({type:e.type,action:e.currentTarget.dataset['on_'+e.type]});},processEventDelayed(e){window.calls.push({type:e.type,action:e.currentTarget.dataset.on_input});}};
  window.calls=[];window.updateOctalSelector=()=>{};window.wysiwygOf=node=>(node?.nodeType===1?node:node?.parentElement)?.closest('.editor');
  window.editorRange=null;window.editorPointer='mouse';
 });
 await page.addScriptTag({path:'public/js/core/event/initEvents.js'});
 await page.addScriptTag({path:'public/js/gui/HTMLeditor.format.js'});
 await page.evaluate(()=>sys.initEvents($(document.body)));
 for (const selector of ['input[name=text01]','textarea','.editor']) {
  await page.locator(selector).fill('Changed');
  await page.waitForTimeout(650);
  assert.equal(await page.evaluate(()=>calls.length),0,selector+' must not save during typing');
  await page.locator('#outside').focus();
  assert.equal(await page.evaluate(()=>calls.length),1,selector+' saves on blur');
  assert.equal(await page.evaluate(()=>calls[0].type),'blur');
  await page.evaluate(()=>calls=[]);
 }
 await page.locator('input[type=search]').fill('query');
 assert.equal(await page.evaluate(()=>calls[0].action),'search','search remains immediate');
 await page.evaluate(()=>{calls=[];wysiwyg.changed(document.querySelector('.editor'));});
 assert.equal(await page.evaluate(()=>calls[0].type),'blur','completed toolbar changes save when editor focus is already elsewhere');
 console.log('ok: plain inputs, sidebar textarea and WYSIWYG wait for blur; search stays immediate; unfocused toolbar changes commit');
} finally {await browser.close();}
