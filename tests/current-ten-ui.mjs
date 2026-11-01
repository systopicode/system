import {readFile,readdir} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const css=(await Promise.all((await readdir('public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile('public/css/'+n,'utf8')))).join('\n')+await readFile('panels/cms/030_settings/project.css','utf8')+await readFile('panels/cms/user/profile.css','utf8');
const ui=await readFile('public/js/gui/ui.js','utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<form data-password-check data-password-strict="true"><input name=password><input name=password_repeat><button type=submit disabled>Save</button></form>');
 await page.addScriptTag({content:ui.slice(ui.lastIndexOf('(function () {'))});
 const a=page.locator('[name=password]'),b=page.locator('[name=password_repeat]'),save=page.locator('button');
 for(const [value,repeat,enabled] of [['','',false],['weak','weak',false],['Valid-13','different',false],['a long passphrase','a long passphrase',true],['Valid-13','',false]]){await a.fill(value);await b.fill(repeat);assert.equal(await save.isEnabled(),enabled);}
 console.log('PASS password: empty, weak, mismatch, valid and cleared repeat; no credential submission');
 for(const width of [390,834,1194,1440]){
 await page.setViewportSize({width,height:900});
 await page.setContent(`<style>${css}body{overflow:auto!important}.projectFiles{padding:20px}.tableScroll{max-width:100%}</style><body class="cms settings"><div class=projectFiles><div class="tableScroll ${width<600?'stacked':''}"><table><tbody>${['pixel','mp'].map(mode=>`<tr class=imageFormatRow><td>⋮</td><td><input value=key></td><td><input value=label></td><td><div class=formatSizing><label><span>&nbsp;</span><select><option>${mode==='mp'?'Megapixels':'Pixel dimensions'}</option></select></label><label><span>${mode==='mp'?'MP':'Width'}</span><input value=800></label><label><span>${mode==='mp'?'Aspect ratio':'Height'}</span><input value=600></label></div></td><td class=icons><button>×</button></td></tr>`).join('')}</tbody></table></div></div></body>`);
 if(width>=600){const gaps=await page.locator('.formatSizing').evaluateAll(rows=>rows.map(row=>{const labels=[...row.children];return labels.slice(1).map((label,i)=>label.getBoundingClientRect().left-labels[i].querySelector('input,select').getBoundingClientRect().right);}));assert.ok(gaps.flat().every(g=>g>=5),JSON.stringify(gaps));}
 await page.screenshot({path:`/private/tmp/fluxo-format-table-${width}.png`});
 }
 console.log('PASS image format sizing: mode and dimensions do not overlap at desktop/tablet widths; narrow stacked fixture');
 await page.setContent(`<style>${css}body{overflow:auto!important}.profile{padding:20px}</style><body class=cms><div class="profile"><div class="profileGrid widgets">${Array.from({length:6},(_,i)=>`<section class=widget><header><h2>Widget ${i}</h2></header><main style="height:${100+i*20}px">Content</main></section>`).join('')}</div></div></body>`);
 await page.evaluate(()=>{window.sys={};});await page.addScriptTag({path:'public/js/gui/widgetColumns.js'});
 await page.evaluate(()=>sys.fitWidgets.layout(document.querySelector('.profileGrid')));
 for(const width of [390,834,1194,2560]){await page.setViewportSize({width,height:1000});await page.evaluate(()=>sys.fitWidgets.layout(document.querySelector('.profileGrid')));const tops=await page.locator('.profileGrid>.widget').evaluateAll(items=>items.map(e=>e.getBoundingClientRect().top));if(width>=834)assert.ok(Math.abs(tops[0]-tops[1])<1,JSON.stringify(tops));await page.screenshot({path:`/private/tmp/fluxo-profile-grid-${width}.png`});}
 const chipSource=await readFile('public/js/gui/HTMLeditor.contextmenu.js','utf8');
 await page.setContent('<style>.pageHead{position:fixed;top:0;height:100px}.cmsPreview{height:500px;overflow:auto;margin-top:100px}.cmsMediaFormat[hidden]{display:none}</style><header class=pageHead>Pages</header><div class=cmsPreview><article class=cmsContent><div style="height:150px"></div><img width=200 height=200><div style="height:1000px"></div></article></div>');
 await page.addScriptTag({content:'function mediaFormatLabel(){return "Test format";}'+chipSource.slice(chipSource.indexOf('function initMediaFormatChip()'),chipSource.indexOf('$(function () {',chipSource.indexOf('function initMediaFormatChip()')))+'initMediaFormatChip();'});
 await page.locator('img').dispatchEvent('pointerover',{pointerType:'mouse'});assert.equal(await page.locator('.cmsMediaFormat').isVisible(),true);
 await page.locator('.cmsPreview').evaluate(e=>{e.scrollTop=200;e.dispatchEvent(new Event('scroll'));});assert.equal(await page.locator('.cmsMediaFormat').isVisible(),false,'format chip does not cover the Pages header');
 console.log('PASS format chip: shown over image, hidden when scrolling behind Pages header');
 assert.deepEqual(errors,[]);console.log('PASS shared profile masonry: top alignment, 390/834/1194/2560; no JS errors');
}finally{await browser.close();}
