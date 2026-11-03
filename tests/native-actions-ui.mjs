import assert from 'node:assert/strict';
import {resolve} from 'node:path';
import {readFile} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=resolve(new URL('..',import.meta.url).pathname);
const browser=await chromium.launch({channel:'chrome',headless:true});
try{
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<body class=cms><form method=put><input name=setting value=kept><button type=button data-cms-action class="ajax button" data-on_click=userConfirm data-action=remove data-id=42>Delete</button><button type=button data-cms-action class=ajax data-on_click=refresh>Refresh</button><button type=button data-cms-action class=ajax data-href="/admin/open/?id=42">Open</button><button type=button data-cms-action class=ajax>Generic</button><button type=button data-cms-action onclick="inlineCalls++">Inline</button></form></body>');
 await page.addScriptTag({path:root+'/public/js/ext/jquery.js'});
 await page.evaluate(()=>{
  window.calls=[];window.submits=0;window.inlineCalls=0;window.updateOctalSelector=()=>{};
  window.sys={core:{event:{}},processEvent(e){e.preventDefault();calls.push({id:e.currentTarget.textContent,type:e.type,dataset:{...e.currentTarget.dataset}});},processEventDelayed(){}};
  document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();submits++;});
 });
 await page.addScriptTag({path:root+'/public/js/core/event/initEvents.js'});
 await page.addScriptTag({path:root+'/public/js/core/lib/obj.js'});
 await page.addScriptTag({path:root+'/public/js/core/event/event.pathItem.js'});
 await page.evaluate(()=>sys.initEvents($(document.body)));
 for(const name of ['Delete','Refresh','Open','Generic']){
  for(const key of ['Enter','Space']){
   await page.evaluate(()=>calls=[]);await page.getByRole('button',{name,exact:true}).focus();await page.keyboard.press(key);
   assert.equal(await page.evaluate(()=>calls.length),1,name+' '+key+' sends exactly one action');assert.equal(await page.evaluate(()=>calls[0].type),'click');
  }
 }
 await page.getByRole('button',{name:'Inline',exact:true}).press('Enter');assert.equal(await page.evaluate(()=>inlineCalls),1);assert.equal(await page.evaluate(()=>submits),0,'action controls never submit a surrounding form');
 const payload=await page.evaluate(()=>new sys.core.event.pathItem(document.querySelector('[data-action=remove]'),{type:'click'}).getData());
 assert.equal(payload.dataset.on_click,'userConfirm');assert.equal(payload.dataset.action,'remove');assert.equal(payload.dataset.id,'42');assert.equal(payload.form.setting,'kept');assert.ok(!Object.hasOwn(payload.form,''));assert.ok(!Object.hasOwn(payload.values,''));
 execFileSync(process.env.FLUXO_PHP||'php',[resolve(root,'../admin/tests/editor-link-dialog.php'),'--render'],{stdio:'ignore'});
 await page.setContent(await readFile('/private/tmp/fluxo-link-dialog-fixture.html','utf8'));
 await page.addScriptTag({path:root+'/public/js/gui/HTMLeditor.funcs.js'});
 await page.evaluate(()=>{window.linkApplyCalls=0;window.applyHTMLeditorLink=()=>linkApplyCalls++;});
 await page.getByRole('textbox',{name:'Link address',exact:true}).press('Enter');assert.equal(await page.evaluate(()=>linkApplyCalls),1,'Enter in link field activates the new footer button');
 await page.getByRole('tab',{name:'Page',exact:true}).press('Enter');assert.equal(await page.evaluate(()=>linkApplyCalls),1,'Tab activation does not submit the link');
 assert.deepEqual(errors,[]);console.log('PASS: native action Enter/Space dispatch once, inline handlers once, no implicit submit, confirmation/action/ID/form values preserved, no anonymous value or JS errors.');
}finally{await browser.close();}
