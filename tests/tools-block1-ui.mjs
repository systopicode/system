import fs from 'node:fs';
import assert from 'node:assert/strict';
const { chromium }=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
const css=['00_theme','18_ui','19_appearance','20_base','24_table','50_forms','70_components','60_lightbox'].map(n=>fs.readFileSync(`public/css/${n}.css`,'utf8')).join('\n');
try {
for(const [width,height,touch] of [[1440,900,false],[1024,768,true],[390,844,true]]) {
 const context=await browser.newContext({viewport:{width,height},hasTouch:touch,isMobile:touch});const page=await context.newPage();
 await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class=cms><main><section class="cms"><section class="widget is-list"><header><h2>Media support</h2><button class=button>Test media tools</button></header><div class=logFilters><label class=field><span>From</span><input type=date></label><label class=field><span>Until</span><input type=date></label><label class=field><span>User</span><input type=search></label><label class=field><span>Result</span><select><option>All results</option></select></label><label class="field logReset"><span>&nbsp;</span><button class="button icononly">×</button></label></div><div class=tableScroll data-table-scroll><table><thead><tr><th>Rule</th><th>Status</th><th></th></tr></thead><tbody><tr><td><b>Email delivery log</b><small>Long policy text that must remain readable while the action column stays in view.</small></td><td>Last check successful</td><td class=icons><ul><li><button class=button>Preview</button></li><li><button class=button>Clean up</button></li></ul></td></tr></tbody></table></div></section></section></main></body>`);
 await page.evaluate(()=>window.sys={});await page.addScriptTag({content:fs.readFileSync('public/js/gui/tableFit.js','utf8')});await page.evaluate(()=>sys.fitTables.fit(document.querySelector('.tableScroll')));
 const data=await page.evaluate(()=>{const r=e=>{const q=e.getBoundingClientRect();return {x:q.x,y:q.y,right:q.right,bottom:q.bottom,w:q.width,h:q.height}};return {fields:[...document.querySelectorAll('.logFilters>.field')].map(r),box:r(document.querySelector('.tableScroll')),action:r(document.querySelector('td.icons')),sticky:getComputedStyle(document.querySelector('td.icons')).position,stacked:document.querySelector('.tableScroll').classList.contains('stacked'),direction:getComputedStyle(document.querySelector('td.icons ul')).flexDirection,head:r(document.querySelector('header')),button:r(document.querySelector('header button'))}});
 assert.equal(data.stacked,false,'explicit scroll report retains table');
 const edges=await page.evaluate(()=>{const table=document.querySelector('table');return {left:getComputedStyle(table.querySelector('th')).paddingLeft,right:getComputedStyle(table.querySelector('th:last-child')).paddingRight,filter:getComputedStyle(document.querySelector('.logFilters')).paddingLeft,standard:getComputedStyle(document.documentElement).getPropertyValue('--cmsCardPadding').trim()};});
 assert.equal(edges.left,edges.filter,'table and filter content share the standard left inset');
 assert.equal(edges.right,edges.left,'table uses equal outer edge insets');
 const controls=await page.locator('.logFilters > .field').evaluateAll(fields=>fields.map(field=>{const a=field.getBoundingClientRect(),b=field.querySelector('input,select,button').getBoundingClientRect();return b.left>=a.left-1&&b.right<=a.right+1;}));
 assert.ok(controls.every(Boolean),'native controls stay inside their own filter field');
 assert.equal(await page.locator('.logReset').evaluate(e=>getComputedStyle(e).flexBasis),'auto','reset action does not reserve a filter column');
 if(width>=1024)assert.ok(Math.abs(data.fields[3].bottom-data.fields[4].bottom)<1,'reset remains beside result on wide layouts');
 const heights=await page.locator('.logFilters input,.logFilters select').evaluateAll(items=>items.map(e=>e.getBoundingClientRect().height));
 assert.ok(Math.max(...heights)-Math.min(...heights)<1,'date, search and select controls share the same height');
 await page.locator('input[type=date]').first().fill('2026-10-30');
 assert.equal(await page.locator('input[type=date]').first().inputValue(),'2026-10-30','native date value remains editable');
 await page.evaluate(()=>{const box=document.createElement('div');box.id='nativeDates';box.style.width='240px';box.innerHTML='<input type="date"><input type="time"><input type="datetime-local">';document.querySelector('main').append(box);});
 for(const [type,value] of [['date','2026-10-30'],['time','17:30'],['datetime-local','2026-10-30T17:30']]){
  const input=page.locator(`#nativeDates input[type="${type}"]`);await input.fill(value);assert.equal(await input.inputValue(),value);
  assert.ok(await input.evaluate(e=>e.getBoundingClientRect().width<=e.parentElement.clientWidth),'native date/time field fits narrow container');
 }
 const nativeHeights=await page.locator('#nativeDates input').evaluateAll(items=>items.map(e=>e.getBoundingClientRect().height));
 assert.ok(Math.max(...nativeHeights)-Math.min(...nativeHeights)<1,'all native date/time types share control height');
 for(let i=0;i<data.fields.length;i++)for(let j=i+1;j<data.fields.length;j++){let a=data.fields[i],b=data.fields[j];assert.ok(a.right<=b.x+1||b.right<=a.x+1||a.bottom<=b.y+1||b.bottom<=a.y+1,'filters do not overlap');}
 assert.ok(Math.abs(data.head.y+data.head.h/2-data.button.y-data.button.h/2)<2,'header action vertically centred');
 if(width===390){assert.equal(data.sticky,'sticky');assert.ok(data.action.right<=data.box.right+1);}
 if(width<=1280)assert.equal(data.direction,'column');
 await page.locator('.tableScroll').evaluate(e=>e.scrollLeft=e.scrollWidth);assert.ok(await page.locator('td.icons button').first().isVisible());
 console.log(`ok: ${width}×${height}, touch=${touch}: filters, header, action column and horizontal scrolling`);await context.close();
}
} finally {await browser.close();}
