import {readFile,readdir} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const root=new URL('../',import.meta.url),read=p=>readFile(new URL(p,root),'utf8');
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const css=(await Promise.all((await readdir(new URL('public/css/',root))).filter(n=>n.endsWith('.css')).sort().map(n=>read('public/css/'+n)))).join('\n')+await read('panels/cms/dashboard.css');
const browser=await chromium.launch({channel:'chrome',headless:true});
const legacy=['a','b','|','c','d','|','e','f'];
const card=(id,h)=>`<section class="widget dashWidget" data-widget="${id}" data-drag_type="widget"><div class="sort v insertBefore" data-drop_accept="widget" data-widget="${id}" data-on_drop="insertWidgetBefore"></div><div class="sort v insertAfter" data-drop_accept="widget" data-widget="${id}" data-on_drop="insertWidgetAfter"></div><button class="button transparent icononly dashHide" data-on_click="hideWidget">×</button><header><h2>Widget ${id}</h2></header><main style="height:${h}px">Body</main></section>`;
const markup=layouts=>`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms"><div class="dashboard"><div class="widgets dashWidgets" data-layouts='${JSON.stringify(layouts)}'><div class="dashColumn">${card('a',400)}${card('b',240)}<div class="dashDrop" data-drop_accept="widget" data-on_drop="insertWidgetEnd" data-column="0"></div></div><div class="dashColumn">${card('c',130)}${card('d',100)}</div><div class="dashColumn">${card('e',180)}${card('f',110)}</div></div></div></body>`;
try{
 const page=await browser.newPage({viewport:{width:1440,height:1400},hasTouch:true});
 await page.setContent(markup({3:legacy}));
 await page.addScriptTag({content:await read('public/js/ext/jquery.js')});
 await page.evaluate(()=>{window.sys={lib:{add:(setup,ctor)=>window.cmsCtor=ctor,applyParentConstructor(){}},core:{},initEvents(){},onload(fn){fn();}};window.obj={extend:(...o)=>Object.assign({},...o)};for(const n of ['l','p','t','c','f'])window[n]=()=>({open(){}});});
 await page.evaluate(()=>{$.fn.findInSet=function(sel){return this.filter(sel).add(this.find(sel));};});
 await page.addScriptTag({content:await read('panels/cms/panel.js')});
 assert.equal(await page.locator('.dashWidgets').getAttribute('data-columns'),'3','first full document starts without a click');
 await page.evaluate(()=>{window.cms={};cmsCtor.call(cms);window.init=()=>sys.fitDashboards.init({element:document.querySelector('.dashWidgets')});init();});
 const layout=()=>page.locator('.dashColumn').evaluateAll(cols=>cols.map(c=>[...c.querySelectorAll('.dashWidget')].map(w=>w.dataset.widget)));
 assert.deepEqual(await layout(),[['a','b'],['c','d'],['e','f']]);
 assert.equal(await page.locator('.dashWidget > header').first().getAttribute('data-on_longpress'),'dashEdit','holding the title opens existing customization');
 for(const [width,count] of [[1024,2],[390,1],[1440,3],[1024,2]]){
  await page.setViewportSize({width,height:1400});await page.waitForTimeout(100);
  assert.equal((await layout()).length,count);
  assert.deepEqual((await layout()).flat().sort(),['a','b','c','d','e','f']);
  const gaps=await page.locator('.dashColumn').evaluateAll(cols=>cols.flatMap(col=>{const cards=[...col.querySelectorAll('.dashWidget')];return cards.slice(1).map((c,i)=>c.getBoundingClientRect().top-cards[i].getBoundingClientRect().bottom);}));
  assert.ok(gaps.every(g=>g>=0&&g<=22),'no artificial grid row gaps '+gaps);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no overflow');
 }
 // The real renderer binds new AJAX fragments before inserting them into the document.
 await page.evaluate(()=>{const old=document.querySelector('.dashWidgets'),fresh=old.cloneNode(true);fresh.removeAttribute('data-columns');sys.fitDashboards.init({element:fresh});old.replaceWith(fresh);});
 await page.waitForTimeout(100);
 assert.equal(await page.locator('.dashWidgets').getAttribute('data-columns'),'2');
 const tablet=await layout();
 await page.locator('.dashWidgets').evaluate(box=>box.classList.add('is-editing'));
 await page.evaluate(()=>init());assert.deepEqual(await layout(),tablet,'editing preserves derived tablet membership');
 assert.equal(await page.locator('.dashWidget > header').first().getAttribute('data-on_longpress'),null,'editing titles keep immediate drag rather than toggle mode');
 // Preserve a hold across the actual dashboard renderer callback, including release-before-response.
 await page.evaluate(()=>{document.querySelector('.dashWidgets').classList.remove('is-editing');init();window.handoffs=[];sys.processEvent=e=>{if(e.type==='longpress')return {done:fn=>window.completeEdit=()=>{const old=document.querySelector('.dashWidgets'),fresh=old.cloneNode(true);fresh.classList.add('is-editing');fresh.removeAttribute('data-columns');const cards=[...fresh.querySelectorAll('.dashWidget')];fresh.replaceChildren(...[0,1,2].map(i=>{const col=document.createElement('div');col.className='dashColumn';col.append(...cards.slice(i*2,i*2+2));return col;}));old.replaceWith(fresh);fn();}};handoffs.push({type:e.type,key:e.currentTarget.dataset.widget,pointer:e.pointerType});};document.querySelector('.dashWidget > header').cmsLongPress({clientX:100,clientY:150,pointerId:31,pointerType:'touch'});completeEdit();});
 assert.deepEqual(await page.evaluate(()=>handoffs),[{type:'pointerdown',key:'a',pointer:'touch'}],'same held widget enters shared drag after edit render');
 assert.equal((await layout()).length,2,'responsive placement finishes before drag freezes incoming three-column fragment');
 await page.evaluate(()=>{document.querySelector('.dashWidgets').classList.remove('is-editing');init();handoffs=[];document.querySelector('.dashWidget > header').cmsLongPress({clientX:100,clientY:150,pointerId:32,pointerType:'touch'});document.dispatchEvent(new PointerEvent('pointerup',{pointerId:32}));completeEdit();});
 assert.deepEqual(await page.evaluate(()=>handoffs),[],'released hold does not create a delayed drag');
 const saved={1:['f','e','d','c','b','a'],2:['f','b','|','a','c','d','e'],3:legacy};
 await page.locator('.dashWidgets').evaluate((box,layouts)=>{box.dataset.layouts=JSON.stringify(layouts);},saved);
 await page.evaluate(()=>init());assert.deepEqual(await layout(),[['f','b'],['a','c','d','e']]);
 await page.setViewportSize({width:390,height:1400});await page.waitForTimeout(100);assert.deepEqual(await layout(),[['f','e','d','c','b','a']]);
 await page.setViewportSize({width:1440,height:1400});await page.waitForTimeout(100);assert.deepEqual(await layout(),[['a','b'],['c','d'],['e','f']]);
 assert.equal(await page.locator('.dashHide').first().evaluate(e=>getComputedStyle(e).borderRadius),'6px');
 // Use the unchanged shared touch recogniser and drag engine on dashboard widgets.
 await page.setViewportSize({width:390,height:1400});await page.waitForTimeout(100);
 await page.addScriptTag({content:await read('public/js/core/event/event.js')});
 await page.addScriptTag({content:await read('public/js/core/event/event.drag.js')});
 await page.evaluate(()=>{window.started=0;sys.processEvent=()=>started++;$('.dashWidget').on('pointerdown',sys.processPointerDown);});
 const first=page.locator('.dashWidget').first();
 await first.dispatchEvent('pointerdown',{pointerId:5,pointerType:'touch',clientX:80,clientY:100});
 await first.dispatchEvent('pointermove',{pointerId:5,pointerType:'touch',clientX:80,clientY:125});await page.waitForTimeout(430);assert.equal(await page.evaluate(()=>started),0,'scroll cancels long press');
 await first.dispatchEvent('pointerup',{pointerId:5,pointerType:'touch'});
 await first.dispatchEvent('pointerdown',{pointerId:6,pointerType:'touch',clientX:80,clientY:100});await page.waitForTimeout(430);assert.equal(await page.evaluate(()=>started),1,'touch hold starts exactly once');
 await first.dispatchEvent('pointerup',{pointerId:6,pointerType:'touch'});
 await page.locator('.dashHide').first().dispatchEvent('pointerdown',{pointerId:7,pointerType:'touch',clientX:80,clientY:100});await page.waitForTimeout(430);assert.equal(await page.evaluate(()=>started),1,'hide button does not start widget dragging');
 await page.evaluate(()=>{const held=document.querySelector('.dashWidget'),r=held.getBoundingClientRect();window.accepted=0;window.testDrag=new sys.core.event.drag({e:$.Event('pointerdown',{cmsHeld:true,currentTarget:held,target:held,clientX:r.x+3,clientY:r.y+3,originalEvent:{target:held,pointerType:'touch',preventDefault(){},stopPropagation(){}}})},{accept:()=>accepted++});if(!document.querySelector('.draghelper'))throw new Error('held handoff did not start drag immediately');const target=document.querySelectorAll('.dashWidget')[1],b=target.getBoundingClientRect();$(document).trigger($.Event('pointermove',{target,clientX:b.x+b.width/2,clientY:b.bottom-1}));});
 await page.waitForTimeout(50);
 assert.equal(await page.locator('.draghelper').evaluate(e=>getComputedStyle(e).visibility),'visible','shared helper remains visible on phone');
 await page.evaluate(()=>{const target=document.querySelectorAll('.dashWidget')[1],b=target.getBoundingClientRect();$(document).trigger($.Event('pointerup',{target,clientX:b.x+b.width/2,clientY:b.bottom-1}));});
 assert.equal(await page.evaluate(()=>accepted),1,'valid dashboard drop accepted once');
 // An empty column exposes a theme-aware target only during a drag.
 await page.setViewportSize({width:1440,height:1400});await page.waitForTimeout(100);
 await page.evaluate(()=>{const col=document.querySelectorAll('.dashColumn')[1];col.querySelectorAll('.dashWidget').forEach(e=>e.remove());if(!col.querySelector('.dashDrop'))col.insertAdjacentHTML('beforeend','<div class="dashDrop" data-drop_accept="widget" data-on_drop="insertWidgetEnd" data-column="1"></div>');document.body.classList.add('widgetDragging');});
 const emptyDrop=page.locator('.dashColumn').nth(1).locator('.dashDrop');
 assert.ok(await emptyDrop.evaluate(e=>e.getBoundingClientRect().height)>=70);
 assert.equal(await emptyDrop.evaluate(e=>getComputedStyle(e).borderStyle),'dashed');
 assert.notEqual(await emptyDrop.evaluate(e=>getComputedStyle(e).backgroundColor),'rgba(0, 0, 0, 0)');
 for(const pointerType of ['mouse','touch']) {
 await page.evaluate(pointerType=>{const held=document.querySelector('.dashWidget'),r=held.getBoundingClientRect();window.emptyAccepted=0;window.emptyDrag=new sys.core.event.drag({e:$.Event('pointerdown',{cmsHeld:true,currentTarget:held,target:held,clientX:r.x+3,clientY:r.y+3,originalEvent:{target:held,pointerType,preventDefault(){},stopPropagation(){}}})},{accept:()=>emptyAccepted++});const target=document.querySelectorAll('.dashColumn')[1].querySelector('.dashDrop'),b=target.getBoundingClientRect();$(document).trigger($.Event('pointermove',{target,clientX:b.x+b.width/2,clientY:b.y+b.height/2}));},pointerType);
 await page.waitForTimeout(80);
 assert.equal(await emptyDrop.evaluate(e=>getComputedStyle(e).borderStyle),'solid');
 assert.equal(await page.locator('.draghelper').evaluate(e=>e.classList.contains('line')),false,'empty column uses a box, not insertion line');
 assert.ok(await page.locator('.draghelper').evaluate(e=>e.getBoundingClientRect().height)>60);
 await page.screenshot({path:'/private/tmp/fluxo-empty-column-hover.png'});
 await page.evaluate(()=>{const target=document.querySelectorAll('.dashColumn')[1].querySelector('.dashDrop'),b=target.getBoundingClientRect();$(document).trigger($.Event('pointerup',{target,clientX:b.x+b.width/2,clientY:b.y+b.height/2}));});
 assert.equal(await page.evaluate(()=>emptyAccepted),1);
 await page.evaluate(()=>document.body.classList.add('widgetDragging'));
 assert.equal(await emptyDrop.evaluate(e=>getComputedStyle(e).borderStyle),'dashed');
 }
 await page.screenshot({path:'/private/tmp/fluxo-empty-column.png'});
 await page.evaluate(()=>document.body.classList.remove('widgetDragging'));
 assert.equal(await emptyDrop.isVisible(),false);
 // Empty and first populated hidden-widget rails keep the same height.
 for (const width of [390,1024,1440]) {
  await page.setViewportSize({width,height:1400});
  await page.locator('.dashboard').evaluate(d=>{d.insertAdjacentHTML('afterbegin','<div class="dashCustomize"><p>Drag a widget by its title to move it. Drop it here or use the cross to hide it.</p><p class="dashHidden"></p></div>');});
  const rail=page.locator('.dashCustomize').first(),empty=await rail.evaluate(e=>e.getBoundingClientRect().height);
  await rail.locator('.dashHidden').evaluate(e=>{e.innerHTML='<span>Add:</span><button class="button" data-drag_type="widget">Backup</button>';});
  assert.ok(Math.abs(await rail.evaluate(e=>e.getBoundingClientRect().height)-empty)<=1,'first hidden widget does not change rail height');
  await rail.evaluate(e=>e.remove());
 }
 // Regression: shrinking earlier widgets must not leave the held title behind.
 await page.setViewportSize({width:1440,height:1400});
 await page.setContent(markup({3:legacy}));
 await page.addScriptTag({content:await read('public/js/ext/jquery.js')});
 await page.evaluate(()=>{$.fn.findInSet=function(sel){return this.filter(sel).add(this.find(sel));};});
 await page.evaluate(()=>init());
 await page.evaluate(()=>{
  sys.processEvent=e=>{
   if(e.type==='longpress')return{done:fn=>{fn();setTimeout(()=>{const old=document.querySelector('.dashWidgets'),fresh=old.cloneNode(true);fresh.classList.add('is-editing');old.replaceWith(fresh);init();},20);}};
   window.carried=new sys.core.event.drag({e});
  };
  const header=document.querySelectorAll('.dashWidget > header')[1],r=header.getBoundingClientRect();
  window.holdPoint={x:r.left+30,y:r.top+20};
  header.cmsLongPress({clientX:holdPoint.x,clientY:holdPoint.y,pointerId:70,pointerType:'touch'});
 });
 await page.locator('.draghelper').waitFor({state:'visible'});
 const carried=await page.evaluate(()=>{const r=document.querySelector('.draghelper').getBoundingClientRect();return{top:r.top,bottom:r.bottom,point:holdPoint.y};});
 assert.ok(carried.point>=carried.top&&carried.point<=carried.bottom,'held title stays under pointer after collapse '+JSON.stringify(carried));
 assert.equal(await page.evaluate(()=>carried.dropObj),null,'layout handoff does not select the rail under a stationary finger');
 await page.evaluate(()=>$(document).trigger($.Event('pointermove',{target:document.querySelector('.dashWidget'),clientX:holdPoint.x+2,clientY:holdPoint.y+2,pointerId:70})));
 assert.equal(await page.evaluate(()=>carried.dropObj),null,'tiny touch jitter does not activate an unintended target');
 // A held widget continues through a scrollable content area.
 await page.evaluate(()=>{const d=document.querySelector('.dashboard');d.style.cssText='height:260px;min-height:0;flex:none;overflow-y:auto;position:relative';d.insertAdjacentHTML('beforeend','<div style="height:1200px;flex:none"></div>');const r=d.getBoundingClientRect();window.edgePoint={x:r.left+50,y:r.top+245};$(document).trigger($.Event('pointermove',{target:d,clientX:edgePoint.x,clientY:edgePoint.y,pointerId:70}));});
 await page.waitForTimeout(200);
 assert.ok(await page.locator('.dashboard').evaluate(e=>e.scrollTop>0),'held drag scrolls the content');
 assert.equal(await page.locator('.draghelper').isVisible(),true,'held widget remains during scroll');
 await page.evaluate(()=>$(document).trigger($.Event('pointercancel',{pointerId:70})));
 console.log('ok: legacy layout; stable 1/2/3 stacks; per-view saved order; no gaps; global hide button; touch hold, scroll cancellation and shared drop feedback');
}finally{await browser.close();}
