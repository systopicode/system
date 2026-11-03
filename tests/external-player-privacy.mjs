import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const html=await readFile('/private/tmp/fluxo-external-player.html','utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
const page=await browser.newPage();let requests=[];
await page.route('**/*',async route=>{
 const req=route.request();
 if(req.url()==='https://cms.example.test/editor')return route.fulfill({contentType:'text/html',body:html+'<img referrerpolicy="no-referrer" src="https://api.dicebear.com/test.svg">'});
 requests.push({url:req.url(),referer:req.headers().referer});
 return route.fulfill({contentType:req.resourceType()==='image'?'image/svg+xml':'text/html',body:req.resourceType()==='image'?'<svg xmlns="http://www.w3.org/2000/svg"/>':'<p>Player fixture</p>'});
});
await page.goto('https://cms.example.test/editor');
await page.addScriptTag({content:await readFile(new URL('../../admin/public/site/embedConsent.js',import.meta.url),'utf8')});
assert.equal(await page.locator('iframe').count(),0);
assert.equal(requests.filter(r=>/youtube|vimeo/.test(r.url)).length,0,'no provider request before a click');
assert.equal(requests.find(r=>r.url.includes('dicebear')).referer,undefined,'external image sends no referrer');
await page.getByRole('button',{name:'Load YouTube player'}).click();
await page.waitForLoadState('networkidle');
assert.equal(await page.locator('iframe').count(),1);
assert.ok(requests.some(r=>r.url.startsWith('https://www.youtube-nocookie.com/embed/')));
assert.equal(requests.filter(r=>r.url.includes('vimeo')).length,0,'loading YouTube does not activate Vimeo');
await page.getByRole('button',{name:'Load Vimeo player'}).click();
await page.waitForLoadState('networkidle');
assert.equal(await page.locator('iframe').count(),2);
assert.equal(await page.evaluate(()=>localStorage.getItem('video_consent_youtube')),null,'CMS choice does not persist public consent');
assert.ok(requests.some(r=>r.url.includes('player.vimeo.com/video/123456789?dnt=1&h=secret123')));
await page.evaluate(markup=>{
 document.body.innerHTML=markup;
 const publicVideo=document.querySelector('[data-ext-video]').cloneNode(true);
 publicVideo.removeAttribute('data-ext-scope');publicVideo.id='publicVideo';
 document.body.appendChild(publicVideo);
},html);
await page.locator('#publicVideo button').click();
assert.equal(await page.evaluate(()=>JSON.parse(localStorage.getItem('video_consent_youtube')).consent),true,'public choice still persists');
assert.equal(await page.locator('[data-ext-scope="view"] iframe').count(),0,'public grant does not activate an editor view');
await page.evaluate(()=>window.initExternalVideos());
assert.equal(await page.locator('[data-ext-scope="view"] iframe').count(),0,'stored public consent never bypasses editor click');
console.log('ok: shared overlay preserves public consent while editor choices stay per-view');
console.log('ok: no player requests before click; providers activate independently; private Vimeo URL preserved; images omit referrer');
}finally{await browser.close();}
