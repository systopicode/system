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
 await page.evaluate(()=>{const held=document.querySelector('.dashWidget'),r=held.getBoundingClientRect();window.accepted=0;window.testDrag=new sys.core.event.drag({e:$.Event('pointerdown',{currentTarget:held,target:held,clientX:r.x+3,clientY:r.y+3,originalEvent:{target:held,pointerType:'touch',preventDefault(){},stopPropagation(){}}})},{accept:()=>accepted++});const target=document.querySelectorAll('.dashWidget')[1],b=target.getBoundingClientRect();$(document).trigger($.Event('pointermove',{target,clientX:b.x+b.width/2,clientY:b.bottom-1}));});
 await page.waitForTimeout(50);
 assert.equal(await page.locator('.draghelper').evaluate(e=>getComputedStyle(e).visibility),'visible','shared helper remains visible on phone');
 await page.evaluate(()=>{const target=document.querySelectorAll('.dashWidget')[1],b=target.getBoundingClientRect();$(document).trigger($.Event('pointerup',{target,clientX:b.x+b.width/2,clientY:b.bottom-1}));});
 assert.equal(await page.evaluate(()=>accepted),1,'valid dashboard drop accepted once');
 // Empty and first populated hidden-widget rails keep the same height.
 for (const width of [390,1024,1440]) {
  await page.setViewportSize({width,height:1400});
  await page.locator('.dashboard').evaluate(d=>{d.insertAdjacentHTML('afterbegin','<div class="dashCustomize"><p>Drag a widget by its title to move it. Drop it here or use the cross to hide it.</p><p class="dashHidden"></p></div>');});
  const rail=page.locator('.dashCustomize').first(),empty=await rail.evaluate(e=>e.getBoundingClientRect().height);
  await rail.locator('.dashHidden').evaluate(e=>{e.innerHTML='<span>Add:</span><button class="button" data-drag_type="widget">Backup</button>';});
  assert.ok(Math.abs(await rail.evaluate(e=>e.getBoundingClientRect().height)-empty)<=1,'first hidden widget does not change rail height');
  await rail.evaluate(e=>e.remove());
 }
 console.log('ok: legacy layout; stable 1/2/3 stacks; per-view saved order; no gaps; global hide button; touch hold, scroll cancellation and shared drop feedback');
}finally{await browser.close();}
