import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const read=p=>readFile(new URL('../'+p,import.meta.url),'utf8');
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 await page.setContent('<div id="item" style="width:200px;height:60px" data-drag_type="page">Item</div><header data-on_longpress="dashEdit">Title <button>Action</button></header>');
 await page.addScriptTag({content:await read('public/js/ext/jquery.js')});
 await page.evaluate(()=>{window.sys={core:{}};window.obj={extend:(...o)=>Object.assign({},...o)};window.l=window.f=window.p=()=>({open(){}});});
 await page.addScriptTag({content:await read('public/js/core/event/event.js')});
 await page.addScriptTag({content:await read('public/js/core/event/event.drag.js')});
 await page.evaluate(()=>{window.realProcessEvent=sys.processEvent;window.starts=0;window.actions=0;sys.processEvent=()=>actions++;window.begin=(type,resize=false)=>{const item=document.querySelector('#item');item.dataset.drag_type=type;if(resize)item.dataset.on_draginit='resize';else delete item.dataset.on_draginit;window.drag=new sys.core.event.drag({e:$.Event('mousedown',{currentTarget:item,target:item,clientX:10,clientY:10,button:0})},{start:()=>starts++});};window.release=()=>$(document).trigger($.Event('mouseup',{target:document.querySelector('#item'),clientX:10,clientY:10}));});
 for(const type of ['page','media','tag','table','widget']) {
  await page.evaluate(type=>begin(type),type);await page.waitForTimeout(450);
  assert.equal(await page.evaluate(()=>drag.status),'dragging',type+' supports stationary mouse hold');
  if(type==='tag'){assert.equal(await page.locator('.draghelper').textContent(),'');assert.ok(await page.locator('.draghelper').evaluate(e=>e.classList.contains('line')));}
  await page.evaluate(()=>release());assert.equal(await page.locator('.draghelper').count(),0);
 }
 await page.evaluate(()=>{window.accepted=0;const item=document.querySelector('#item');item.dataset.drag_type='page';window.drag=new sys.core.event.drag({e:$.Event('mousedown',{currentTarget:item,target:item,clientX:10,clientY:10,button:0})},{accept:()=>accepted++});});
 await page.waitForTimeout(450);await page.evaluate(()=>{drag.status='accept';release();});assert.equal(await page.evaluate(()=>accepted),0,'stationary hold never commits a drop');
 await page.evaluate(()=>begin('page'));await page.waitForTimeout(70);await page.evaluate(()=>release());await page.waitForTimeout(450);
 assert.equal(await page.evaluate(()=>starts),5,'quick click never starts drag later');
 await page.evaluate(()=>{begin('media');$(document).trigger($.Event('mousemove',{target:document.querySelector('#item'),clientX:25,clientY:10}));});
 assert.equal(await page.evaluate(()=>starts),6,'movement starts immediately');await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>starts),6,'hold cannot start twice');await page.evaluate(()=>release());
 await page.evaluate(()=>begin('media'));await page.evaluate(()=>window.dispatchEvent(new Event('blur')));await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>starts),6,'blur cancels hold');
 await page.evaluate(()=>begin('media',true));await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>starts),6,'resize hooks keep existing behavior');await page.evaluate(()=>release());
 const title=page.locator('header');
 await title.dispatchEvent('pointerdown',{pointerType:'mouse',pointerId:1,button:0,clientX:10,clientY:90});await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>actions),1);await title.dispatchEvent('pointerup',{pointerId:1});
 await page.locator('button').dispatchEvent('pointerdown',{pointerType:'mouse',pointerId:2,button:0});await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>actions),1,'buttons excluded');await page.locator('button').dispatchEvent('pointerup',{pointerId:2});
 await title.dispatchEvent('pointerdown',{pointerType:'mouse',pointerId:3,button:0,clientX:10,clientY:90});await title.dispatchEvent('pointermove',{pointerId:3,clientX:40,clientY:90});await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>actions),1,'movement cancels mode-entry hold');await title.dispatchEvent('pointerup',{pointerId:3});
 // The real page-tree drag surface is a label, not a generic div.
 await page.evaluate(()=>{document.querySelector('#item').outerHTML='<label id="item" data-drag_type="page" style="display:block;width:200px;height:60px">Tree page</label>';begin('page');});
 await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>drag.status),'dragging','real tree label supports mouse hold');await page.evaluate(()=>release());
 await page.evaluate(()=>{
  document.querySelector('#item').outerHTML='<ul class="tree"><li><label id="item" data-drag_type="page"><button class="label" data-href="?id=42">Real tree title</button></label></li></ul>';
  const item=document.querySelector('#item'),link=item.querySelector('button');
  window.drag=new sys.core.event.drag({e:$.Event('mousedown',{currentTarget:item,target:link,clientX:10,clientY:10,button:0})},{});
 });
 await page.waitForTimeout(450);assert.equal(await page.evaluate(()=>drag.status),'dragging','holding the tree title link starts dragging');await page.evaluate(()=>release());
 assert.equal(await page.evaluate(()=>{const e=new Event('webkitmouseforcewillbegin',{bubbles:true,cancelable:true});document.querySelector('#item button').dispatchEvent(e);return e.defaultPrevented;}),true);
 await page.evaluate(()=>{
  document.body.insertAdjacentHTML('beforeend','<div class="editor" contenteditable="true"><a href="page::42">Page link</a></div>');
  window.bars=0;$(document.body).on('contextmenu','.editor a',()=>bars++);
  const a=document.querySelector('.editor a');realProcessEvent($.Event('click',{target:a,currentTarget:a}));
 });
 assert.equal(await page.evaluate(()=>bars),1,'page link click opens editor bar rather than navigating');
 const editorSource=await read('public/js/gui/HTMLeditor.contextmenu.js');
 await page.addScriptTag({content:editorSource.slice(0,editorSource.lastIndexOf('$(function ()'))});
 await page.evaluate(()=>{window.toolbarTypes=[];window.HTMLeditorRightClick=type=>toolbarTypes.push(type);initContenteditableContextMenus();document.querySelector('.editor a').setAttribute('target','_blank');document.querySelector('.editor a').click();});
 assert.deepEqual(await page.evaluate(()=>toolbarTypes),['anchor'],'existing editor capture opens the link bar even for target=_blank');
 console.log('ok: mouse hold across drag types; immediate dragging; quick-click, resize, blur, duplicate and interactive-control guards; shared mode-entry action');
} finally {await browser.close();}
