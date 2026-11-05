/** Shared UI regression checks using real styles and the existing event/drag engine. */
import {readFile,readdir} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const root=new URL('../',import.meta.url),read=p=>readFile(new URL(p,root),'utf8');
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE?pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href:'playwright');
const css=(await Promise.all((await readdir(new URL('public/css/',root))).filter(n=>n.endsWith('.css')).sort().map(n=>read('public/css/'+n)))).join('\n')+await read('panels/admin/030_settings/project.css');
const jquery=await read('public/js/ext/jquery.js'),events=await read('public/js/core/event/event.js'),drag=await read('public/js/core/event/event.drag.js');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for(const width of [390,1180,1440]) {
  const page=await browser.newPage({viewport:{width,height:900},isMobile:width<1400,hasTouch:width<1400});
  await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms"><aside class="cms" style="height:450px;position:relative"><nav class="sidebarTabs"><ul><li><a>Content</a></li><li><a>SEO</a></li></ul></nav><div class="flexContainer"><div class="scrollContainer"><div style="height:1500px">Long properties</div></div></div></aside><div class="projectFiles"><div class="widget"><header>Settings</header><div class="tableScroll"><table><tbody><tr id="r1"><td class="pageSettingDrag"><i id="handle" data-drag_type="setting" data-id="1"></i></td><td>First</td></tr><tr id="r2"><td class="pageSettingDrag"><i data-drag_type="setting" data-id="2"></i><div class="sort v insertBefore" data-drop_accept="setting" data-id="2"></div><div class="sort v insertAfter" data-drop_accept="setting" data-id="2"></div></td><td>Second</td></tr></tbody></table></div></div></div><input id="search" value="a"><div class="cmslightbox" style="display:none"><section><main><ul class="tagAssignments"><li><span class="tagAssignmentActions"><a>Open</a><button class="button icononly">Delete</button></span></li></ul></main></section></div></body>`);
  const tabY=await page.locator('.sidebarTabs').evaluate(n=>n.getBoundingClientRect().top);
  await page.locator('.scrollContainer').evaluate(n=>n.scrollTop=600);
  assert.equal(await page.locator('.sidebarTabs').evaluate(n=>n.getBoundingClientRect().top),tabY);
  assert.ok(await page.locator('.scrollContainer').evaluate(n=>n.scrollTop>0));
  assert.equal(await page.locator('.pageSettingDrag').first().evaluate(n=>{const s=getComputedStyle(n);return s.paddingLeft===s.paddingRight&&parseFloat(s.paddingLeft)<parseFloat(getComputedStyle(document.documentElement).fontSize)*.3;}),true,'compact symmetric grip at '+width);
  await page.addScriptTag({content:jquery});
  await page.evaluate(()=>{window.obj={extend:(...o)=>Object.assign({},...o)};window.sys={core:{}};for(const n of ['l','p','t','c','f'])window[n]=()=>({open(){}});});
  await page.addScriptTag({content:events});
  await page.addScriptTag({content:await read('public/js/gui/tableFit.js')});
  await page.evaluate(()=>sys.fitTables.fit(document.querySelector('.tableScroll')));
  // A native input event's currentTarget becomes null after its dispatch.
  await page.evaluate(()=>{window.delayed=[];sys.core.event=function(e){this.collectElementData=()=>delayed.push(e.currentTarget?.id);this.dispatch=()=>{};};document.querySelector('#search').addEventListener('input',sys.processEventDelayed);});
  await page.locator('#search').fill('new query');
  await page.waitForTimeout(380);
  assert.deepEqual(await page.evaluate(()=>window.delayed),['search']);
  await page.evaluate(()=>{window.started=0;window.originalProcessEvent=sys.processEvent;sys.processEvent=()=>window.started++;document.querySelector('#handle').addEventListener('pointerdown',sys.processPointerDown);});
  await page.dispatchEvent('#handle','pointerdown',{pointerId:7,pointerType:'touch',clientX:25,clientY:500});
  await page.dispatchEvent('#handle','pointermove',{pointerId:7,pointerType:'touch',clientX:25,clientY:525});
  await page.waitForTimeout(430);
  assert.equal(await page.evaluate(()=>window.started),0);
  await page.dispatchEvent('#handle','pointerup',{pointerId:7,pointerType:'touch'});
  await page.dispatchEvent('#handle','pointerdown',{pointerId:8,pointerType:'touch',clientX:25,clientY:500});
  await page.waitForTimeout(430);
  assert.equal(await page.evaluate(()=>window.started),1);
  await page.dispatchEvent('#handle','pointerup',{pointerId:8,pointerType:'touch'});
  await page.evaluate(()=>window.originalProcessEvent({type:'mousedown',currentTarget:document.querySelector('#handle'),stopPropagation(){throw Error('Compatibility mouse event was not suppressed');}}));
  await page.addScriptTag({content:drag});
  await page.evaluate(()=>{const h=document.querySelector('#handle'),r=h.getBoundingClientRect();window.accepted=0;window.testDrag=new sys.core.event.drag({e:$.Event('pointerdown',{currentTarget:h,target:h,clientX:r.x+3,clientY:r.y+3,originalEvent:{target:h,pointerType:'touch',preventDefault(){},stopPropagation(){}}})},{accept:()=>window.accepted++});const target=document.querySelector('#r2 .insertAfter'),b=document.querySelector('#r2').getBoundingClientRect();$(document).trigger($.Event('pointermove',{target,clientX:b.x+b.width/2,clientY:b.bottom-1}));});
  // A stacked row changes layout when dragging begins. Follow the finger to
  // its current gap, then release in the same event turn (no synthetic pause).
  await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  const placement=await page.evaluate(()=>{const target=document.querySelector('#r2 .insertAfter'),r=document.querySelector('#r2').getBoundingClientRect(),x=r.x+r.width/2,y=r.bottom-1;$(document).trigger($.Event('pointermove',{target,clientX:x,clientY:y}));const helper=document.querySelector('.draghelper'),cue=helper.getBoundingClientRect(),result={height:cue.height,width:parseFloat(helper.style.width),rowWidth:document.querySelector('#r2').getBoundingClientRect().width};$(document).trigger($.Event('pointerup',{target,clientX:x,clientY:y}));return result;});
  assert.equal(placement.height,2);
  assert.ok(Math.abs(placement.width-placement.rowWidth)<2,JSON.stringify(placement));
  assert.equal(await page.evaluate(()=>window.accepted),1);
  assert.equal(await page.locator('tbody tr').last().getAttribute('id'),'r1');
  await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms"><div class="layout tags"><div class="widget"><div id="tag1" class="tag" data-drag_type="tag" data-id="1" data-label="First">First</div><div id="tag2" class="tag" data-id="2">Second<div class="sort h insertBefore" data-drop_accept="tag" data-id="2"></div><div class="sort h insertAfter" data-drop_accept="tag" data-id="2"></div></div></div></div></body>`);
  await page.evaluate(touch=>{const h=document.querySelector('#tag1'),r=h.getBoundingClientRect();window.tagDrag=new sys.core.event.drag({e:$.Event('pointerdown',{currentTarget:h,target:h,clientX:r.x+3,clientY:r.y+3,originalEvent:{target:h,pointerType:touch?'touch':'mouse',preventDefault(){},stopPropagation(){}}})},{});const target=document.querySelector('#tag2 .insertBefore'),b=document.querySelector('#tag2').getBoundingClientRect();$(document).trigger($.Event('pointermove',{target,clientX:b.x,clientY:b.y+b.height/2}));},width<1400);
  const tagCue=await page.locator('.draghelper').boundingBox(),tagBox=await page.locator('#tag2').boundingBox();
  assert.ok(Math.abs(tagCue.height-tagBox.height-(width<1400?36:0))<1);
  if(width<1400) assert.equal(await page.locator('.draghelper').evaluate(n=>parseFloat(getComputedStyle(n,'::before').left)),tagCue.width/2);
  await page.evaluate(()=>$(document).trigger($.Event('pointercancel',{target:document.querySelector('#tag2')})));
  await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms"><ul class="contextmenu" style="top:20px;left:20px"><li><a><i></i><span>Preview</span></a></li></ul><div class="splitApplyList is-open" style="top:120px;left:20px"><button><i></i>Apply to all formats</button></div><div class="cmslightbox" style="display:none"><section><main><ul class="tagAssignments"><li><span class="tagAssignmentActions"><a>Open</a><button class="button icononly">Delete</button></span></li></ul></main></section></div></body>`);
  const menuStyle=selector=>page.locator(selector).evaluate(n=>{const s=getComputedStyle(n);return {radius:s.borderRadius,bg:s.backgroundColor,padding:s.padding};});
  assert.deepEqual(await menuStyle('ul.contextmenu'),await menuStyle('.splitApplyList'));
  const itemStyle=selector=>page.locator(selector).evaluate(n=>{const s=getComputedStyle(n);return {height:s.minHeight,padding:s.padding,font:s.fontSize,weight:s.fontWeight};});
  assert.deepEqual(await itemStyle('ul.contextmenu a'),await itemStyle('.splitApplyList button'));
  assert.equal(await page.locator('.tagAssignments button').evaluate(n=>getComputedStyle(n).color),await page.locator('.tagAssignments a').evaluate(n=>getComputedStyle(n).color));
  // Own parent groups must neither hover nor accept a dragged child (tags and MML).
  for (const type of ['tag','media']) {
   await page.setContent(`<style>${css}</style><body class="cms"><div class="layout tags"><section class="widget" data-drop_accept="tag"><header>Group</header><span id="child" class="tag" data-drag_type="tag">Child</span></section></div><ul class="mmlContent"><li class="title" data-drop_accept="media"><h4>Media group</h4><ul><li id="medium" data-drag_type="media">File</li></ul></li></ul></body>`);
   await page.evaluate(type=>{const h=document.querySelector(type==='tag'?'#child':'#medium'),parent=h.closest('[data-drop_accept]'),r=h.getBoundingClientRect();window.parentAccepted=0;new sys.core.event.drag({e:$.Event('pointerdown',{currentTarget:h,target:h,clientX:r.x,clientY:r.y,originalEvent:{target:h,pointerType:'touch',preventDefault(){},stopPropagation(){}}})},{accept:()=>parentAccepted++});$(document).trigger($.Event('pointermove',{target:parent,clientX:r.x+30,clientY:r.y+20}));},type);
   await page.waitForTimeout(330);
   assert.equal(await page.locator('.dropHover,.dropTarget').count(),0);
   await page.evaluate(()=>$(document).trigger($.Event('pointerup',{target:document.body})));
   assert.equal(await page.evaluate(()=>parentAccepted),0);
  }
  // A fixed table can fit its box while its fields overflow the grid.
  if(width===1440) {
   await page.setContent(`<style>${css}</style><body class="cms"><div class="projectFiles"><section class="widget"><header>Video formats</header><div class="tableScroll" style="width:400px"><table style="table-layout:fixed;width:100%"><tbody><tr><td><input style="min-width:700px" value="Wide field"></td><td class="icons">Actions</td></tr></tbody></table></div></section></div></body>`);
   await page.evaluate(()=>sys.fitTables.fit(document.querySelector('.tableScroll')));
   assert.equal(await page.locator('.tableScroll').evaluate(n=>n.classList.contains('canScrollX')),true);
   assert.equal(await page.locator('.tableScroll').evaluate(n=>getComputedStyle(n).overflowX),'auto');
  }
  const userCss=await read('panels/admin/080_access/010_users/edit/user.css');
  await page.setContent(`<style>${css}\n${userCss}</style><body class="cms"><div class="cmslightbox" style="display:block"><section><main class="userEdit"><div class="userAccount"><div class="cmsBoxBody"><div class="cms message">That username is already in use.</div><div class="accountPhoto">Portrait</div><div class="accountFields">Fields</div></div></div><div class="cmsBoxBody"><button class="cmsBoxMore">Show all devices</button></div></main></section></div></body>`);
  const form=await page.locator('.userAccount .cmsBoxBody').boundingBox(),message=await page.locator('.message').boundingBox(),photo=await page.locator('.accountPhoto').boundingBox(),fields=await page.locator('.accountFields').boundingBox();
  assert.ok(Math.abs(message.width-form.width)<2);
  assert.ok(photo.y>=message.y+message.height);
  if(width>640) assert.ok(Math.abs(photo.y-fields.y)<1);
  assert.equal(await page.locator('.cmsBoxMore').evaluate(n=>getComputedStyle(n).borderTopWidth),'1px');
  await page.setContent('<input id="cmsSearchInput" value="Sport"><ul id="cmsSearchResults"></ul><div class="scrollContainerContent" data-node_id="10"><div class="relationSearch" data-relation="redirect"><input value="Sport"><ul class="relationResults"></ul></div></div>');
  await page.evaluate(()=>{sys.onDocumentReady=()=>{};sys.initEvents=()=>{};sys.core.server={};});
  await page.addScriptTag({content:await read('public/js/core/server/server.command.js')});
  await page.addScriptTag({content:await read('../cms/panels/admin/010_pages/pages.js')});
  await page.locator('#cmsSearchInput').focus();
  await page.evaluate(()=>{window.searchBefore=document.querySelector('#cmsSearchInput');updatePageSearchResults('Start','<li>stale</li>');});
  assert.equal(await page.locator('#cmsSearchResults').innerText(),'');
  await page.evaluate(()=>updatePageSearchResults('Sport','<li>Current result</li>'));
  assert.equal(await page.locator('#cmsSearchResults').innerText(),'Current result');
  assert.equal(await page.evaluate(()=>document.activeElement===window.searchBefore&&document.querySelector('#cmsSearchInput')===window.searchBefore),true);
  await page.evaluate(()=>{updatePageRelationResults(20,'redirect','Sport','<li>wrong page</li>');updatePageRelationResults(10,'redirect','Start','<li>stale query</li>');});
  assert.equal(await page.locator('.relationResults').innerText(),'');
  await page.evaluate(()=>updatePageRelationResults(10,'redirect','Sport','<li>Matching target</li>'));
  assert.equal(await page.locator('.relationResults').innerText(),'Matching target');
  await page.setContent('<div id="restored" style="height:100px;overflow:auto"><div style="height:2000px"></div></div><div id="untouched" style="height:100px;overflow:auto"><div style="height:2000px"></div></div>');
  await page.addScriptTag({content:await read('public/js/core/server/server.js')});
  await page.evaluate(()=>{
   sys.core.server.restoreScroll([{selector:'#restored',top:600},{selector:'#untouched',top:400}]);
   sys.core.server.revealScroll(document.querySelector('#restored'),120);
  });
  await page.waitForTimeout(850);
  assert.equal(await page.locator('#restored').evaluate(n=>n.scrollTop),120,'late restoration yields to explicit target');
  assert.equal(await page.locator('#untouched').evaluate(n=>n.scrollTop),400,'unrelated scroller keeps restoration');
  await page.evaluate(()=>sys.core.server.restoreScroll([{selector:'#restored',top:250}]));
  assert.equal(await page.locator('#restored').evaluate(n=>n.scrollTop),250,'next navigation can restore normally');
  // A late answer and its image-settling timers must yield to user scrolling.
  await page.evaluate(()=>{
   const el=document.querySelector('#restored');
   const positions=sys.core.server.scrollPositions();
   document.dispatchEvent(new WheelEvent('wheel',{bubbles:true,deltaY:50}));
   el.scrollTop=390;
   sys.core.server.restoreScroll(positions);
  });
  await page.waitForTimeout(850);
  assert.equal(await page.locator('#restored').evaluate(n=>n.scrollTop),390,'scrolling while a request is in flight wins');
  await page.evaluate(()=>{
   sys.core.server.restoreScroll([{selector:'#restored',top:250}]);
   document.dispatchEvent(new WheelEvent('wheel',{bubbles:true,deltaY:50}));
   document.querySelector('#restored').scrollTop=420;
  });
  await page.waitForTimeout(850);
  assert.equal(await page.locator('#restored').evaluate(n=>n.scrollTop),420,'scrolling after an answer cancels delayed restores');
  // Reveal the selected label, not the full height of its nested children.
  await page.setContent('<body class="cms"><main><div class="content"><aside class="cms"><div class="scrollContainer" style="height:200px;overflow:auto"><div style="height:600px"></div><details data-section="relations" open><summary>Relations</summary><div data-relation-section="aliases" style="height:50px">Aliases</div><div style="height:500px"></div></details></div></aside></div></main><div class="tree" style="height:120px;overflow:auto"><div style="height:500px"></div><ul class="tree"><li class="active"><label style="display:block;height:30px">Selected</label><ul style="height:600px"></ul></li></ul></div>');
  await page.evaluate(()=>focusPageRelations('aliases'));
  await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))));
  assert.ok(await page.locator('[data-relation-section="aliases"]').evaluate(n=>Math.abs(n.getBoundingClientRect().top-n.closest('.scrollContainer').getBoundingClientRect().top-8)<2));
  await page.waitForFunction(()=>{const n=document.querySelector('li.active > label'),a=n.getBoundingClientRect(),b=n.closest('div.tree').getBoundingClientRect();return a.top>=b.top&&a.bottom<=b.bottom;});
  const pagesCss=await read('../cms/panels/admin/010_pages/panel.css');
  await page.setContent(`<style>${css}\n${pagesCss}</style><body class="cms"><aside class="cms"><div class="pageRelations"><ul class="relationIncoming"><li><a class="sideAction relationLink"><span>Alias</span></a><button class="button transparent icononly small" id="remove-relation">Remove</button></li></ul></div></aside><button class="button" id="normal-button">Normal</button></body>`);
  const radius=await page.locator('#normal-button').evaluate(n=>getComputedStyle(n).borderRadius);
  assert.equal(await page.locator('#remove-relation').evaluate(n=>getComputedStyle(n).borderLeftWidth),'0px');
  assert.equal(await page.locator('#remove-relation').evaluate(n=>getComputedStyle(n).borderRadius),radius);
  await page.locator('#remove-relation').hover();
  assert.equal(await page.locator('#remove-relation').evaluate(n=>getComputedStyle(n).borderLeftWidth),'0px');
  assert.equal(await page.locator('#remove-relation').evaluate(n=>getComputedStyle(n).borderRadius),radius);
  await page.setContent(`<style>${css}</style><body class="cms"><ul class="contextmenu mediaContextMenu"><li class="iconsonly"><ul>${['crop-simple','eye','arrow-down-to-line','trash'].map(icon=>`<li class="iconOnly"><a><i class="cmsIcon cmsIcon-${icon}"></i></a></li>`).join('')}</ul></li></ul></body>`);
  assert.ok(await page.locator('.mediaContextMenu').evaluate(el=>el.getBoundingClientRect().width)<220,'media icon menu has no empty text-column width');
  assert.ok(await page.locator('.mediaContextMenu').evaluate(el=>{const box=el.getBoundingClientRect(),actions=el.querySelectorAll('a'),first=actions[0].getBoundingClientRect(),last=actions[actions.length-1].getBoundingClientRect();return Math.abs((first.left-box.left)-(box.right-last.right))<1;}),'media menu has equal left and right padding');
  const mediaCss=await read('../cms/panels/admin/010_pages/pages.mml.css');
  await page.setContent(`<style>${css}\n${mediaCss}</style><body class="cms"><aside class="cms" style="width:280px"><div class="mediaDrop shareImage"></div></aside></body>`);
  assert.ok(await page.locator('.mediaDrop.shareImage').evaluate(n=>n.getBoundingClientRect().height)>80,'empty share-image drop keeps its height');
  await page.close();
 }
 console.log('ok: fixed sidebar tabs, symmetric touch grips, native debounce, scroll cancellation, long press and shared row ordering on phone/tablet/desktop');
}finally{await browser.close();}
