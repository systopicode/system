import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createServer} from 'node:http';
import {pathToFileURL} from 'node:url';
import {resolve,dirname,extname} from 'node:path';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const root=resolve(dirname(new URL(import.meta.url).pathname),'..');
const registry=JSON.parse(await readFile(root+'/src/Ui/icon-map.json','utf8'));
const art=await readFile(root+'/src/Auth/passkey-icons.tpl.php','utf8');
const css=['11_icons.css','75_passkeys.css'].map(n=>'<link rel="stylesheet" href="/public/css/'+n+'">').join('');
const icons=Object.keys(registry.outline).map(key=>'<button aria-label="'+key+'"><i class="cmsIcon cmsIcon-'+key+'" aria-hidden="true"></i></button>').join('');
const duo=Object.keys(registry.duotone).map(key=>'<i class="cmsIcon cmsIcon-duotone cmsIcon-'+key+'" aria-hidden="true"></i>').join('');
const mmlCss=await readFile(root+'/../cms/panels/admin/010_pages/pages.mml.css','utf8');
const thumbnailFixture='<div class="cms"><div class="mmlContent"><ul><li class="title"><ul><li class="is-mark" style="width:180px;height:180px"><i class="cmsIcon cmsIcon-file-zipper thumbType"></i><div class="thumbActions"><div class="thumbButtons"><a title="Edit"><i class="cmsIcon cmsIcon-pen"></i></a><label title="Replace"><i class="cmsIcon cmsIcon-arrows-rotate"></i></label></div></div></li><li class="thumbMissing" style="width:180px;height:180px"><i class="cmsIcon cmsIcon-image-slash thumbBroken"></i></li><li style="width:180px;height:180px"><i class="cmsIcon cmsIcon-image thumbType"></i></li></ul></li></ul></div></div>';
const html='<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">'+css+'<style>'+mmlCss+'</style>'+thumbnailFixture+'<style>:root{--cmsMainColor:#344650;--cmsCanvas:white}.icons{display:flex;flex-wrap:wrap;gap:8px}button{display:inline-flex;gap:8px;padding:12px}.duo{color:#344650;font-size:28px}.active{background:#344650}.active .cmsIcon{--icon-primary:#fff;--icon-secondary:#ffb600;--icon-secondary-opacity:1}.passkeyArtwork{width:88px;height:88px}.passkeys .passkeyAuthFeedback{display:block}#shadow{margin:1rem}</style><div class="icons">'+icons+'</div><div class="duo">'+duo+'</div><div class="duo active">'+duo+'</div><div class="passkeys passkeyLogin"><div class="passkeyAuthFeedback"><div class="passkeyArtwork">'+art+'</div></div></div><div id="shadow"></div><script src="/public/js/gui/icons.js"></script>';
const server=createServer(async(req,res)=>{try{if(req.url==='/'){res.setHeader('Content-Type','text/html');res.end(html);return;}const p=resolve(root,'.'+req.url.split('?')[0]);if(!p.startsWith(root+'/public/')){res.writeHead(403);res.end();return;}res.setHeader('Content-Type',({'.svg':'image/svg+xml','.css':'text/css','.js':'text/javascript'})[extname(p)]||'application/octet-stream');res.end(await readFile(p));}catch{res.writeHead(404);res.end();}});
await new Promise(done=>server.listen(0,'127.0.0.1',done));
const origin='http://127.0.0.1:'+server.address().port;
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();const errors=[],bad=[],requests=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)bad.push(r.url());});page.on('request',r=>requests.push(r.url()));
 await page.goto(origin,{waitUntil:'networkidle'});
 assert.equal(await page.locator('svg.passkeyIcon').count(),3,'passkey artwork is parsed as real SVG');
 assert.ok(await page.locator('svg.passkeyIcon path').count()>0,'original SVG paths are rendered');
 await page.evaluate(async assets=>{await Promise.all(assets.map(async path=>{const image=new Image();image.src=path;await image.decode();}));},[...new Set(Object.values(registry.outline))].map(name=>'/public/icons/'+(name.endsWith('-line-duotone')?'solar/':'tabler/')+name+'.svg'));

 assert.ok(requests.filter(u=>u.endsWith('.svg')).length>=Object.keys(registry.duotone).length*2,'the full displayed icon catalogue actually loads its SVG images');
 for(const width of [1440,768,390]){
  await page.setViewportSize({width,height:900});
  const slots=await page.locator('button .cmsIcon').evaluateAll(es=>es.map(e=>({w:e.getBoundingClientRect().width,h:e.getBoundingClientRect().height,mask:getComputedStyle(e,'::before').maskImage})));
  assert.ok(slots.every(s=>s.w>0&&s.h>0&&s.mask!=='none'),'all registered icons have a local mask and stable slot at '+width);
  assert.ok(await page.locator('button').first().getAttribute('aria-label'));
 }
 await page.evaluate(()=>{const s=document.querySelector('#shadow').attachShadow({mode:'open'});s.innerHTML='<link rel="stylesheet" href="/public/css/11_icons.css"><i class="cmsIcon cmsIcon-check"></i>';});
 await page.waitForFunction(()=>getComputedStyle(document.querySelector('#shadow').shadowRoot.querySelector('i'),'::before').maskImage!=='none');
 await page.evaluate(()=>document.querySelector('.icons').insertAdjacentHTML('beforeend','<i id="legacy" class="fa-regular fa-check"></i>'));
 assert.equal(await page.locator('#legacy').getAttribute('class'),'fa-regular fa-check','legacy markup is not upgraded');
 assert.equal(await page.evaluate(()=>cmsIcons.classes('fa-duotone fa-gear')),'cmsIcon cmsIcon-circle-question');
 assert.equal(await page.evaluate(()=>cmsIcons.classes('cmsIcon cmsIcon-duotone cmsIcon-wrench')),'cmsIcon cmsIcon-wrench cmsIcon-duotone');
 assert.equal(registry.duotone.wrench,'wrench','Tools uses original Phosphor Duotone wrench');

 for(const key of ['image-slash','video-slash','file-slash','gif','video']) assert.notEqual(registry.outline[key],undefined,'dynamic media key '+key);
 for(const selector of ['.is-mark .thumbType','.thumbBroken']) {
  const size=await page.locator(selector).first().evaluate(e=>parseFloat(getComputedStyle(e,'::before').width));
  assert.ok(size>=16&&size<=36,'glyph stays bounded inside a 180px tile: '+selector+' '+size);
 }
 const badge=await page.locator('.mmlContent li:not(.is-mark) > .thumbType').evaluate(e=>parseFloat(getComputedStyle(e,'::before').width));
 assert.ok(badge<=22,'type badge is small, independently of tile size');
 const edit=await page.locator('.thumbButtons .cmsIcon').first().evaluate(e=>({glyph:parseFloat(getComputedStyle(e,'::before').width),button:e.parentElement.getBoundingClientRect().width}));
 assert.ok(edit.glyph<=edit.button*.8,'hover icon has breathing room inside its circular button');

 await page.emulateMedia({reducedMotion:'reduce'});
 assert.equal(await page.locator('.passkeyIcon-fingerprint').evaluate(e=>getComputedStyle(e).opacity),'1');
 await page.evaluate(()=>document.querySelector('.passkeys').dataset.step='success');
 assert.equal(await page.locator('.passkeyIcon-success').evaluate(e=>getComputedStyle(e).opacity),'1');
 assert.equal(await page.locator('.passkeyIcon-fingerprint').evaluate(e=>getComputedStyle(e).opacity),'0');
 await page.emulateMedia({reducedMotion:'no-preference'});
 await page.evaluate(()=>{delete document.querySelector('.passkeys').dataset.step;document.querySelectorAll('.passkeyIcon').forEach(e=>{e.style.animationPlayState='paused';e.getAnimations().forEach(a=>a.currentTime=1000);});});
 assert.equal(await page.locator('.passkeyIcon-fingerprint').evaluate(e=>getComputedStyle(e).animationDuration),'9s');
 for(const [time,name] of [[1000,'fingerprint'],[4000,'scan'],[7000,'success']]){
  await page.evaluate(time=>document.querySelectorAll('.passkeyIcon').forEach(e=>e.getAnimations().forEach(a=>a.currentTime=time)),time);
  const visible=await page.locator('.passkeyIcon').evaluateAll(es=>es.filter(e=>Number(getComputedStyle(e).opacity)>.95).map(e=>e.classList[1]));
  assert.deepEqual(visible,['passkeyIcon-'+name],'same 9s phase ordering at '+time);
 }
 assert.ok(requests.every(u=>u.startsWith(origin)),'no third-party icon request');assert.deepEqual(errors,[]);assert.deepEqual(bad,[]);
 console.log('ok: registered SVGs load; desktop/tablet/mobile slots; shadow root; legacy classes unsupported; reduced-motion success; 9s animation; bounded thumbnail/type/hover glyphs; dynamic media fallback keys; local-only requests');
} finally {await browser.close();await new Promise(done=>server.close(done));}
