import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const body=await readFile('panels/cms/body.tpl.php','utf8');
const start=body.indexOf('// Keep the value before a short label edit.');
const finish=body.indexOf("document.addEventListener('focusout'",start);
const handler=body.slice(start,finish);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 const stored={title:'Original',tree:'Original'};let writes=[];
 await page.route('http://pencil.test/**',async route=>{
  const request=route.request();
  if(request.method()==='POST') { const data=JSON.parse(request.postData());stored[data.id]=data.value;writes.push(data);return route.fulfill({json:{ok:true}}); }
  return route.fulfill({contentType:'text/html',body:`<h1 id=title class=pageTitle contenteditable=plaintext-only data-on_blur=updateLabel>${stored.title}</h1><ul class=tree><li><span id=tree contenteditable=plaintext-only data-on_blur=updateLabel>${stored.tree}</span></li></ul>`});
 });
 const install = async () => {
  await page.addScriptTag({path:'public/js/ext/jquery.js'});
  await page.evaluate(()=>{window.sys={core:{server:{sendCall(){}}}};window.l=window.f=window.p=()=>{};});
  await page.addScriptTag({path:'public/js/core/lib/obj.js'});
  await page.addScriptTag({path:'public/js/core/event/event.js'});
  await page.addScriptTag({path:'public/js/core/event/event.pathItem.js'});
  await page.evaluate(()=>{
   const EventClass=sys.core.event, PathItem=sys.core.event.pathItem;
   sys.core.event=function(e){
    const event=new EventClass(e);
    event.dispatch=()=>{
     event.getTriggers();
     if(event.triggers.blur==='updateLabel') fetch('/save',{method:'POST',body:JSON.stringify({id:event.element.id,value:event.data.html})});
     return {done(){}};
    };
    return event;
   };
   sys.core.event.pathItem=PathItem;
   $('[data-on_blur]').bind('blur',sys.processEvent);
  });
  await page.addScriptTag({content:handler});
 };
 await page.goto('http://pencil.test/'); await install();
 for(const id of ['title','tree']) {
  const before=writes.length;
  await page.locator('#'+id).fill('Saved via Enter '+id);
  await page.locator('#'+id).press('Enter');
  await page.waitForTimeout(100);
  assert.equal(writes.length,before+1,id+': Enter sends one save');
  await page.reload(); await install();
  assert.equal(await page.locator('#'+id).textContent(),'Saved via Enter '+id,id+': value survives a new document');
 }
 const before=writes.length;
 await page.locator('#title').fill('Cancelled');await page.locator('#title').press('Escape');
 await page.waitForTimeout(100);
 assert.equal(writes.length,before,'Escape does not save');
 console.log('ok: Enter commits page title and tree label exactly once and values survive reload');
}finally{await browser.close();}
