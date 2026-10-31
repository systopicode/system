import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {mkdir} from 'node:fs/promises';
import {resolve} from 'node:path';
import {tmpdir} from 'node:os';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=resolve(new URL('..',import.meta.url).pathname);
const examples=JSON.parse(execFileSync(process.env.FLUXO_PHP||'php',[root+'/tests/local-avatars-preview.php'],{encoding:'utf8'}));
const artifacts=process.env.FLUXO_AVATAR_ARTIFACTS||resolve(tmpdir(),'fluxo-local-avatar-review');await mkdir(artifacts,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{
 const context=await browser.newContext({viewport:{width:1000,height:550}});const page=await context.newPage();const errors=[],requests=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>requests.push(r.url()));
 await page.setContent('<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Local avatars</title><style>*{box-sizing:border-box}body{margin:0;padding:24px;font:16px Arial;color:#203c48;background:#f0f3f4}main{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}article{padding:20px;text-align:center;background:white;border-radius:12px;border:1px solid #dbe2e5}img{display:block;width:112px;height:112px;border-radius:50%;margin:0 auto 14px;max-width:100%}h1{font-size:22px;margin:0 0 20px}button{display:block;margin:20px auto 0;min-height:44px}body.dark{background:#162126;color:#edf2f4}body.dark article{background:#24353d;border-color:#617780}@media(max-width:600px){main{grid-template-columns:repeat(2,minmax(0,1fr))}body{padding:12px}article{padding:14px}img{width:88px;height:88px}}</style><h1>Local avatars · Notionists &amp; Bottts</h1><main>'+examples.map((e,i)=>'<article><img src="data:image/svg+xml,'+encodeURIComponent(e.svg)+'" alt="'+e.label+'"><span>'+e.label+'</span></article>').join('')+'</main><button type="button">Light / dark</button>');
 await page.evaluate(()=>document.querySelector('button').onclick=()=>document.body.classList.toggle('dark'));
 await page.locator('img').evaluateAll(es=>Promise.all(es.map(e=>e.decode())));
 assert.equal(await page.locator('img').evaluateAll(es=>es.every(e=>e.complete&&e.naturalWidth>0)),true);
 assert.equal(new Set(examples.slice(0,4).map(e=>e.svg)).size,4,'different seeds give varied person avatars');
 for(const width of [1000,768,390]){await page.setViewportSize({width,height:width===1000?550:1000});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);await page.screenshot({path:resolve(artifacts,'avatars-'+width+'.png'),fullPage:true});}
 await page.getByRole('button').click();await page.screenshot({path:resolve(artifacts,'avatars-dark.png'),fullPage:true});
 const touch=await browser.newContext({hasTouch:true,isMobile:true,viewport:{width:768,height:1024}});const tp=await touch.newPage();await tp.setContent(await page.content());await tp.locator('img').evaluateAll(es=>Promise.all(es.map(e=>e.decode())));assert.equal(await tp.locator('img').count(),8);await touch.close();
 assert.deepEqual(errors,[]);assert.ok(requests.every(u=>u.startsWith('data:')||u.startsWith('about:')),'local generation needs no remote avatar, font or image request');
 console.log('PASS: real local SVGs and both Duotone fallbacks decode; varied person seeds; desktop/iPad/narrow/dark and touch display; no external requests or JS errors.');
}finally{await browser.close();}
