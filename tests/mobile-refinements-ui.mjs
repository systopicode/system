/** Targeted mobile regressions; no project data or credentials. */
import {readFile,readdir} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const root=new URL('../',import.meta.url);
const read=p=>readFile(new URL(p,root),'utf8');
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE ? pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href : 'playwright');
const css=(await Promise.all((await readdir(new URL('public/css/',root))).filter(n=>n.endsWith('.css')).sort().map(n=>read('public/css/'+n)))).join('\n')+await read('panels/admin/dashboard.css')+await read('panels/admin/080_users/010_users/edit/user.css');
const controller=await read('panels/admin/mobile.js'),tables=await read('public/js/gui/tableFit.js');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 await page.route('https://cms.example.test/**',r=>r.fulfill({body:'<html></html>',contentType:'text/html'}));
 await page.goto('https://cms.example.test/admin/settings/');
 await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms settings"><header class="cmsMobileHeader"><button data-cms-window="menu"></button><strong data-cms-module></strong></header><main><div class="main"><nav><li class="active"><a href="/admin/settings/">Settings</a></li></nav></div><div class="pylon cms"><header></header><nav><ul><li class="active"><a href="/admin/settings/pagesettings/">Global Settings</a></li></ul></nav></div><div class="content"></div></main><button class="cmsMobileBackdrop" hidden></button></body>`);
 await page.evaluate(controller);
 await page.evaluate(()=>document.addEventListener('click',e=>{const a=e.target.closest('a');if(!a)return;e.preventDefault();history.pushState({},'',a.href);document.querySelector('.content').innerHTML='<div class="flexRow"><div class="flexColumn"><section class="cms"><h1>Global Settings</h1></section></div></div>';}));
 await page.getByRole('link',{name:'Global Settings',exact:true}).click();
 await page.waitForFunction(()=>document.body.dataset.cmsWindow==='edit');
 // Response scripts run again, and a later partial response drops wrapper attributes.
 await page.evaluate(controller);
 await page.locator('.content').evaluate(n=>n.removeAttribute('data-cms-sheet'));
 await page.evaluate(controller);
 assert.equal(await page.locator('body').getAttribute('data-cms-window'),'edit');
 assert.equal(await page.locator('.content').getAttribute('data-cms-sheet'),'1');
 assert.ok((await page.getByRole('heading',{name:'Global Settings'}).boundingBox()).width>200);
 await page.evaluate(`window.cmsMobile.introducedHref='https://cms.example.test/previous/';document.querySelector('.content').removeAttribute('data-cms-sheet');${controller}`);
 assert.equal(await page.locator('body').getAttribute('data-cms-window'),'edit');
 assert.equal(await page.locator('.content').getAttribute('data-cms-sheet'),'1');
 await page.keyboard.press('Escape');
 await page.waitForFunction(()=>document.body.dataset.cmsWindow==='base');
 await page.evaluate(controller);
 assert.equal(await page.locator('body').getAttribute('data-cms-window'),'base');
 await page.locator('.content').evaluate(n=>n.innerHTML='<div class="cmslightbox"><div class="tabmenu" style="display:flex;width:250px;overflow:auto"><button style="flex:0 0 150px">General</button><button style="flex:0 0 150px">Roles</button><button class="active" style="flex:0 0 150px">Maintenance</button></div></div>');
 await page.evaluate(()=>window.cmsMobile.sync());
 await page.waitForTimeout(500);
 let tabPosition=await page.locator('.tabmenu').evaluate(n=>({rail:n.getBoundingClientRect().toJSON(),tab:n.querySelector('.active').getBoundingClientRect().toJSON()}));
 assert.ok(tabPosition.tab.right<=tabPosition.rail.right && tabPosition.tab.left>=tabPosition.rail.left, 'Selected tab is entirely visible');
 // A narrower slide-in must remeasure the same selection, not retain a stale marker.
 await page.locator('.tabmenu').evaluate(n=>{n.style.width='210px';n.scrollLeft=0;});
 await page.evaluate(()=>window.cmsMobile.sync());
 await page.waitForTimeout(500);
 tabPosition=await page.locator('.tabmenu').evaluate(n=>({rail:n.getBoundingClientRect().toJSON(),tab:n.querySelector('.active').getBoundingClientRect().toJSON()}));
 assert.ok(tabPosition.tab.right<=tabPosition.rail.right && tabPosition.tab.left>=tabPosition.rail.left);
 // Content-only modules must not inherit a detail sheet from a previous module.
 for (const module of ['backup','user']) {
  await page.goto('https://cms.example.test/admin/'+module+'/');
  await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms ${module}"><header class="cmsMobileHeader"><button data-cms-window="menu"></button><strong data-cms-module></strong></header><button class="cmsMobileBackdrop" hidden></button><main><div class="main"></div><div class="content" data-cms-sheet="1"><header></header><section>Content</section></div></main></body>`);
  await page.evaluate(controller);
  assert.equal(await page.locator('.content').getAttribute('data-cms-sheet'),null, module+' remains ordinary content');
 }
 await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms"><div class="tableScroll"><table><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody><tr><td><span>Site name</span></td><td><input value="Original"></td></tr></tbody></table></div><ul class="dashList"><li><a><span><strong>A long dashboard title that should be readable on a small smartphone screen</strong></span><em>Hidden</em></a></li></ul><div class="cmsFind"><span class="cmsKeys">Enter Esc</span><span class="cmsFindTouchHint">Tap a result to open it.</span></div></body>`);
 await page.evaluate(()=>window.sys={});
 await page.evaluate(tables);
 for(const width of [320,390,430]) {
  await page.setViewportSize({width,height:844});
  await page.evaluate(()=>sys.fitTables.fit(document.querySelector('.tableScroll')));
  const toggle=page.getByRole('button',{name:'Details: Site name'});
  assert.equal(await toggle.count(),1);
  await toggle.press('Enter');
  assert.equal(await toggle.getAttribute('aria-expanded'),'true');
  assert.equal(await page.locator('input').isVisible(),true);
  await page.locator('input').click();
  assert.equal(await toggle.getAttribute('aria-expanded'),'true');
  await toggle.press('Space');
  assert.equal(await toggle.getAttribute('aria-expanded'),'false');
  assert.equal(await page.locator('input').isVisible(),false);
  assert.equal(await page.locator('.cmsKeys').isVisible(),false);
  assert.equal(await page.locator('.cmsFindTouchHint').isVisible(),true);
  assert.equal(await page.locator('.dashList strong').evaluate(n=>getComputedStyle(n).whiteSpace),'normal');
 }
 await page.setViewportSize({width:1440,height:900});
 await page.evaluate(()=>sys.fitTables.fit(document.querySelector('.tableScroll')));
 assert.equal(await page.locator('[data-table-fold]').count(),0);
 assert.equal(await page.locator('input').isVisible(),true);
 assert.equal(await page.locator('.cmsFindTouchHint').isVisible(),false);
 assert.equal(await page.locator('.dashList strong').evaluate(n=>getComputedStyle(n).whiteSpace),'nowrap');
 await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms"><div class="cmslightbox cms userEdit"><section><header><h1>Glass</h1></header><main><input value="Readable form"></main></section></div></body>`);
 for(const width of [320,390,768,1440]) {
  await page.setViewportSize({width,height:1000});
  const glass=await page.locator('.cmslightbox > section').evaluate(n=>({blur:getComputedStyle(n.parentElement).backdropFilter,surfaceBlur:getComputedStyle(n).backdropFilter,background:getComputedStyle(n).backgroundColor,shadow:getComputedStyle(n).boxShadow,width:n.getBoundingClientRect().width}));
  assert.match(glass.blur,/blur\(18px\)/);
  assert.equal(glass.surfaceBlur,'none');
  assert.ok(!glass.background.includes('/'), 'Dialog surface remains opaque');
  assert.ok(glass.width<=width, JSON.stringify({width,glass}));
  assert.equal(await page.locator('input').isVisible(),true);
 }
 console.log('ok: detail navigation across panel reinitialisation, accordion pointer/keyboard/state, mobile title wrapping/search hints, desktop reset');
}finally{await browser.close();}
