import fs from 'node:fs';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const css=['00_theme','11_icons','13_icons','18_ui','19_appearance','20_base','23_pylon','25_widgets','26_spinner','27_tree','50_forms','60_lightbox','70_components'].map(n=>fs.readFileSync(`public/css/${n}.css`,'utf8')).join('\n')+fs.readFileSync('panels/cms/user/profile.css','utf8');
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent(`<style>${css}.profile{padding:20px}.fixturePreview{position:relative;width:300px;height:200px}</style><body class=cms><section class="cms profile"><div class=profileGrid>${Array.from({length:7},(_,i)=>`<section class=widget><header><h2>Widget ${i}</h2></header><main>Content</main></section>`).join('')}</div></section><ul class=tree><li class=active><label><a class=fold><i class="cmsIcon cmsIcon-chevron-right"></i></a><a class=label>Selected page</a></label></li></ul><label class=checkbox><input type=checkbox class=switch><div><div></div></div></label><div class="cms message cmsActionMessages" data-system-messages><div class=warning><p class=message>Backup warning</p></div></div><div class=cmslightbox><section><main><div class="fileFrame fixturePreview"><img id=preview></div></main></section></div></body>`);
 let prior=0;
 for(const width of [2260,2560,3000]){await page.setViewportSize({width,height:1000});const size=await page.locator('.profileGrid > .widget').first().evaluate(e=>e.getBoundingClientRect().width);assert.ok(size>prior,'profile widgets grow beyond 2260px');prior=size;}
 await page.evaluate(()=>document.documentElement.dataset.mode='dark');
 const colour=await page.locator('.tree li.active > label').evaluate(e=>({bg:getComputedStyle(e).backgroundColor,text:getComputedStyle(e.querySelector('.label')).color,fold:getComputedStyle(e.querySelector('.fold')).color}));
 assert.equal(colour.text,colour.fold,'selected page and chevron use same readable text');
 await page.locator('.tree a.label').evaluate(e=>{const button=document.createElement('button');button.className='label';button.textContent=e.textContent;e.replaceWith(button);});
 assert.equal(await page.locator('.tree button.label').evaluate(e=>getComputedStyle(e).color),colour.text,'tree buttons and links have identical selected contrast');
 const switchTrack=await page.locator('input.switch + div').evaluate(e=>getComputedStyle(e).backgroundColor);assert.notEqual(switchTrack,'rgba(0, 0, 0, 0)');
 const overlay=await page.locator('.cmsActionMessages').evaluate(e=>({padding:getComputedStyle(e).padding,shadow:getComputedStyle(e).boxShadow}));assert.equal(overlay.padding,'0px');assert.equal(overlay.shadow,'none');
 await page.evaluate(()=>{window.$=()=>({keydown(){}});});await page.addScriptTag({path:'public/js/gui/ui.js'});
 await page.evaluate(()=>document.dispatchEvent(new CustomEvent('cms:rendered')));
 await page.locator('#preview').evaluate(e=>e.src='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/>');
 await page.waitForFunction(()=>document.querySelector('#preview').complete);
 await page.waitForFunction(()=>document.querySelector('[data-preview-loading]')?.hidden);
 assert.equal(await page.locator('[data-preview-loading]').isVisible(),false,'loaded image hides spinner despite icon display styles');
 await page.locator('#preview').evaluate(e=>e.dispatchEvent(new Event('error')));
 assert.equal(await page.locator('[data-preview-loading]').isVisible(),false,'failed image does not leave a loading spinner');
 assert.deepEqual(errors,[]);
 await page.locator('.cmslightbox').evaluate(e=>e.remove());
 await page.setViewportSize({width:1024,height:768});await page.screenshot({path:'/private/tmp/fluxo-block1-dark-regressions.png'});
 console.log('ok: wide profile growth, selected tree colours, switch track, overlay edges, preview loading and error state');
} finally {await browser.close();}
