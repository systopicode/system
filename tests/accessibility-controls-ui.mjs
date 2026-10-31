import assert from 'node:assert/strict';
import {readFile,readdir,mkdir,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {resolve} from 'node:path';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const sys=resolve(new URL('..',import.meta.url).pathname),out=process.env.FLUXO_A11Y_ARTIFACTS || resolve(tmpdir(),'fluxo-accessibility-controls');await mkdir(out,{recursive:true});
const css=(await Promise.all((await readdir(sys+'/public/css')).filter(n=>n.endsWith('.css')).map(n=>readFile(sys+'/public/css/'+n,'utf8')))).join('\n');
const browser=await chromium.launch({channel:'chrome',headless:true});
const results=[];
try{
for(const width of [1440,768,390]){
const page=await browser.newPage({viewport:{width,height:960},hasTouch:width<1000});const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.setContent(`<html lang=en><title>Fixture project | fluxo</title><style>${css}</style><body class=cms><a class=cmsSkipLink href=#cms-main>Skip to content</a><main><div class=main><nav aria-label="Main navigation"><a href=#>Main</a></nav></div><div class=pylon><nav>Section</nav></div><div class=content id=cms-main role=main tabindex=-1><h1>Persons</h1><section class=cms><form class=portraitDrop><input id=portrait type=file aria-label="Upload profile picture"></form><div class=tableScroll><table><tbody><tr><td>Example person</td><td class=icons><button title=Edit><i class=cmsIcon></i></button></td></tr></tbody></table></div><div class=appearanceChoices role=radiogroup aria-label=Appearance><button role=radio aria-checked=true data-value=light>Light</button><button role=radio aria-checked=false data-value=dark>Dark</button><button role=radio aria-checked=false data-value=system>System</button></div><form><input id=required required aria-label="Required field"></form><button id=opener>Actions</button><div class=splitApply><button type=button class=splitApplyMenu aria-label="Apply options">Apply options</button><div class=splitApplyList hidden><button>Apply to all formats</button></div></div><div contenteditable=true role=textbox aria-label="Description" aria-multiline=true>Example</div><main>Card body</main></section></div></main><footer class=cmsStatusbar><span class=cmsJobs hidden></span><span class=cmsSaveState role=status aria-live=polite hidden></span></footer></body></html>`);
await page.addScriptTag({path:sys+'/public/js/ext/jquery.js'});await page.evaluate(()=>{window.sys={initEvents(){}};window.settings={};window.radioCalls=[];document.querySelectorAll('[role=radio]').forEach(el=>el.onclick=()=>radioCalls.push(el.dataset.value));});
await page.addScriptTag({path:sys+'/public/js/gui/dropdownMenus.js'});
await page.addScriptTag({path:sys+'/public/js/gui/ui.js'});
await page.addScriptTag({path:sys+'/public/js/gui/jobs.js'});
await page.waitForFunction(()=>document.title.startsWith('Persons ·'));
assert.equal(await page.locator('main main').getAttribute('role'),'presentation');
await page.locator('.cmsSkipLink').focus();await page.keyboard.press('Enter');assert.equal(await page.locator('#cms-main').evaluate(e=>document.activeElement===e),true);
await page.getByRole('radio',{name:'Light',exact:true}).focus();await page.keyboard.press('ArrowRight');assert.equal(await page.getByRole('radio',{name:'Dark',exact:true}).getAttribute('aria-checked'),'true');assert.deepEqual(await page.evaluate(()=>radioCalls),['dark']);
assert.equal(await page.getByRole('button',{name:'Edit: Example person',exact:true}).count(),1);
await page.locator('#required').evaluate(e=>e.checkValidity());assert.equal(await page.locator('#required').getAttribute('aria-invalid'),'true');assert.ok(await page.locator('#required').getAttribute('aria-describedby'));await page.locator('#required').fill('valid');assert.equal(await page.locator('#required').getAttribute('aria-invalid'),'false');
await page.getByRole('button',{name:'Apply options',exact:true}).focus();await page.keyboard.press('Enter');assert.equal(await page.locator('.splitApplyMenu').getAttribute('aria-expanded'),'true');assert.equal(await page.getByRole('button',{name:'Apply to all formats',exact:true}).evaluate(e=>document.activeElement===e),true);await page.keyboard.press('Escape');assert.equal(await page.locator('.splitApplyMenu').getAttribute('aria-expanded'),'false');assert.equal(await page.locator('.splitApplyMenu').evaluate(e=>document.activeElement===e),true);
await page.evaluate(()=>openContextMenu({html:'<ul class=contextmenu style="position:fixed;background:white;padding:20px"><li><a data-on_click=rename>Rename</a></li><li><a data-on_click=duplicate>Duplicate</a></li></ul>',event:{sourceElement:document.querySelector('#opener'),e:{detail:0}}}));
assert.equal(await page.getByRole('button',{name:'Rename',exact:true}).evaluate(e=>document.activeElement===e),true);await page.keyboard.press('ArrowDown');assert.equal(await page.getByRole('button',{name:'Duplicate',exact:true}).evaluate(e=>document.activeElement===e),true);await page.keyboard.press('Escape');assert.equal(await page.locator('#opener').evaluate(e=>document.activeElement===e),true);assert.equal(await page.locator('.contextmenu').count(),0);
await page.evaluate(()=>{
 const editor=document.querySelector('[contenteditable]');editor.classList.add('editor');
 window.formatCalls=0;window.fixtureFormat=()=>formatCalls++;
 window.HTMLeditorRightClick=(type,event)=>new contextMenuWW({bold:{title:'Bold',className:'cmsIcon cmsIcon-pen',func:'fixtureFormat',selected:true},more:{title:'More formatting',className:'cmsIcon cmsIcon-ellipsis',subs:{italic:{title:'Italic',func:'fixtureFormat'}}},disabled:{title:'Disabled format',className:'cmsIcon cmsIcon-xmark',func:'fixtureFormat',disabled:true}},event);
});
const editorSource=await readFile(sys+'/public/js/gui/HTMLeditor.contextmenu.js','utf8');
await page.addScriptTag({content:editorSource.slice(0,editorSource.lastIndexOf('$(function ()'))});
await page.evaluate(()=>initContenteditableContextMenus());
await page.locator('[contenteditable]').focus();
await page.evaluate(()=>{const range=document.createRange();range.selectNodeContents(document.querySelector('[contenteditable]'));getSelection().removeAllRanges();getSelection().addRange(range);});
await page.keyboard.press('Alt+F10');
await page.getByRole('button',{name:'Bold',exact:true}).waitFor({timeout:4000}).catch(async e=>{console.log('EDITOR DEBUG',errors,await page.evaluate(()=>({menu:document.querySelector('.HTMLeditorMenu')?.outerHTML,focus:document.activeElement.outerHTML,selection:getSelection().toString()})));throw e;});
assert.equal(await page.getByRole('button',{name:'Bold',exact:true}).evaluate(e=>document.activeElement===e),true);
assert.equal(await page.getByRole('button',{name:'Bold',exact:true}).getAttribute('aria-pressed'),'true');
await page.locator('.HTMLeditorMenu [aria-disabled=true]').evaluate(e=>e.click());assert.equal(await page.evaluate(()=>formatCalls),0);
await page.keyboard.press('ArrowRight');assert.equal(await page.getByRole('button',{name:'More formatting',exact:true}).evaluate(e=>document.activeElement===e),true);
await page.keyboard.press('ArrowRight');await page.waitForFunction(()=>document.activeElement.textContent.trim()==='Italic',{},{timeout:2000}).catch(async e=>{console.log('SUB DEBUG',width,await page.evaluate(()=>({focus:document.activeElement.outerHTML,menu:document.querySelector('.HTMLeditorMenu')?.outerHTML,items:[...document.querySelectorAll('.HTMLeditorMenu a')].map(a=>({name:a.textContent||a.title,visibility:getComputedStyle(a).visibility,rects:a.getClientRects().length,tab:a.tabIndex}))})));throw e;});assert.equal(await page.getByRole('button',{name:'Italic',exact:true}).evaluate(e=>document.activeElement===e),true);
await page.keyboard.press('ArrowLeft');assert.equal(await page.getByRole('button',{name:'More formatting',exact:true}).evaluate(e=>document.activeElement===e),true);
await page.keyboard.press('ArrowLeft');
await page.keyboard.press('Enter');assert.equal(await page.evaluate(()=>formatCalls),1);assert.equal(await page.locator('.HTMLeditorMenu').count(),0);
await page.evaluate(()=>cmsJobs.update('upload',{title:'Upload files',done:1,total:3}));assert.equal(await page.getByRole('progressbar',{name:'Upload files'}).getAttribute('aria-valuenow'),'33');await page.evaluate(()=>cmsJobs.update('upload',{title:'Upload files',done:3,total:3}));assert.ok((await page.locator('.cmsA11yStatus[role=status]').textContent()).includes('completed'));
let chooserPromise=page.waitForEvent('filechooser');await page.getByRole('button',{name:'Upload profile picture',exact:true}).focus();await page.keyboard.press('Enter');let chooser=await chooserPromise;assert.equal(chooser.isMultiple(),false);
if(process.env.FLUXO_AXE_PATH){
 await page.addScriptTag({path:process.env.FLUXO_AXE_PATH});
 const violations=await page.evaluate(async()=> (await axe.run(document,{runOnly:{type:'rule',values:['label','button-name','aria-input-field-name','aria-toggle-field-name','aria-allowed-attr','aria-valid-attr-value','aria-required-attr','aria-required-parent','aria-required-children']}})).violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})));
 assert.deepEqual(violations,[],'named controls and ARIA contracts');
}
await page.evaluate(()=>document.querySelector('.content').insertAdjacentHTML('beforeend','<section id=profile-title-fixture class=profile></section>'));
await page.waitForFunction(()=>document.title.startsWith('Account ·'));
await page.locator('#profile-title-fixture').evaluate(e=>e.remove());await page.waitForFunction(()=>document.title.startsWith('Persons ·'));
await page.screenshot({path:out+'/controls-'+width+'.png'});assert.deepEqual(errors,[]);results.push({width,status:'PASS'});await page.close();
}
}catch(e){console.error(e);throw e;}finally{await browser.close();}
await writeFile(out+'/results.json',JSON.stringify(results,null,2));console.log('PASS: desktop/iPad/narrow keyboard menus, Escape/focus return, radios, upload entry, action names, validation, progress and page navigation; no JS errors.');
