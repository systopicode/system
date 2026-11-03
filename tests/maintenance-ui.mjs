import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const system=new URL('../',import.meta.url);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();let css='';
 for(const path of ['public/css/00_theme.css','public/css/18_ui.css','public/css/19_appearance.css','public/css/20_base.css','public/css/24_table.css','public/css/30_layout.css','public/css/50_forms.css','public/css/60_lightbox.css','public/css/70_components.css','public/css/x_responsive.css','panels/admin/090_tools/tools.css']) css+='\n'+await readFile(new URL(path,system),'utf8');
 const html=await readFile('/private/tmp/fluxo-maintenance-view.html','utf8');
 for(const width of [390,1024,1440]) {
  await page.setViewportSize({width,height:900});
  await page.setContent(`<style>${css}</style><body class="cms tools"><main class="cms" style="padding:16px;min-width:0">${html}</main></body>`);
  assert.equal(await page.locator('[data-job="example"]').count(),2);
  assert.equal(await page.locator('.cleanupSetup').evaluate(e=>e.open),false);
  await page.locator('.cleanupSetup > summary').click();
  assert.equal(await page.locator('.cleanupSetup').evaluate(e=>e.open),true);
  await page.locator('.cleanupMethod > summary').filter({hasText:'HTTPS request'}).click();
  assert.match(await page.locator('.cleanupSetup').innerText(),/HTTPS is required/);
  const size=await page.evaluate(()=>({width:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}));
  assert.ok(size.scroll<=size.width+2,`page overflow at ${width}`);
  assert.equal(await page.locator('button').evaluateAll(buttons=>buttons.filter(b=>b.textContent.trim()&&!b.querySelector('i')).length),0,'every labelled button has an icon');
  assert.equal(await page.locator('.cleanupPolicy').first().evaluate(e=>getComputedStyle(e).display),'block');
 }
 const dashboard=await readFile(new URL('panels/admin/dashboard.css',system),'utf8');
 for(const mode of ['light','dark']) {
  await page.setContent(`<html data-mode="${mode}"><style>${css}${dashboard}</style><body class="cms"><div class="dashThumbs"><a><i class="thumbType">I</i></a><a class="is-mark"><i class="thumbType">Z</i></a></div></body></html>`);
  const ratio=await page.locator('.dashThumbs a:not(.is-mark) i').evaluate(el=>{
   const s=getComputedStyle(el);const c=document.createElement('canvas');c.width=c.height=1;const x=c.getContext('2d');
   const rgb=color=>{x.clearRect(0,0,1,1);x.fillStyle=color;x.fillRect(0,0,1,1);return [...x.getImageData(0,0,1,1).data].slice(0,3);};
   const lum=rgb=>rgb.map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((a,v,i)=>a+v*[.2126,.7152,.0722][i],0);
   const f=lum(rgb(s.color)),b=lum(rgb(s.backgroundColor));return (Math.max(f,b)+.05)/(Math.min(f,b)+.05);
  });
  assert.ok(ratio>=3,`media badge contrast in ${mode}: ${ratio}`);
 }
 console.log('ok: dashboard media badge contrast in light and dark mode');
 console.log('ok: cleanup UI at phone/tablet/desktop widths; readable policies; collapsed setup and HTTPS information');
} finally {await browser.close();}
