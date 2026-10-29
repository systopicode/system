import {readFile,readdir} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const root=new URL('../',import.meta.url),read=p=>readFile(new URL(p,root),'utf8');
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
// Deliberately omit all module CSS and JavaScript: shell scrolling must already work.
const css=(await Promise.all((await readdir(new URL('public/css/',root))).filter(n=>n.endsWith('.css')).sort().map(n=>read('public/css/'+n)))).join('\n');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for (const width of [1180,1440]) {
  const page=await browser.newPage({viewport:{width,height:850},javaScriptEnabled:false});
  await page.setContent(`<style>${css}</style><body class="cms settings"><main><div class="main cms"><header>Fluxo</header></div><div class="pylon cms"><nav>Settings</nav></div><div class="content"><header class="cms">Settings</header><div class="flexRow"><div class="flexColumn"><section class="cms"><h1>Media Settings</h1><div class="tableScroll" style="overflow-x:auto"><table><tbody>${Array.from({length:30},(_,i)=>`<tr><td style="height:80px">Setting ${i}<input value="Value"></td></tr>`).join('')}</tbody></table></div></section></div></div></div></main></body>`);
  const box=page.locator('section.cms');
  assert.ok(await box.evaluate(el=>el.scrollHeight>el.clientHeight+500&&el.clientHeight<innerHeight),'bounded CSS-only settings scroller at '+width);
  await page.locator('.tableScroll td').nth(1).hover();await page.mouse.wheel(0,400);
  await page.waitForTimeout(180);
  assert.ok(await box.evaluate(el=>el.scrollTop)>0,'vertical wheel above a table reaches the settings scroller before JavaScript is available');
  await page.close();
 }
 const page=await browser.newPage();
 const form=await read('src/Settings/EnvironmentForm.php');
 const pattern=form.match(/pattern="([^\"]+)"/)[1];
 await page.setContent(`<input id="ip" pattern="${pattern}"><input id="other">`);
 for(const ip of ['192.168.10.66','::1','2001:db8::1','']){await page.locator('#ip').fill(ip);assert.equal(await page.locator('#ip').evaluate(el=>el.checkValidity()),true,ip);}
 await page.locator('#ip').fill('192.168.10.666');assert.equal(await page.locator('#ip').evaluate(el=>el.checkValidity()),false);
 await page.evaluate(()=>{window.sys={lib:{add:(s,c)=>window.environmentCtor=c,applyParentConstructor(){}},core:{server:{sendEventCall(){window.sent=true;}}}};});
 await page.addScriptTag({content:await read('panels/cms/030_settings/035_environment/panel.js')});
 await page.evaluate(()=>{const env={};environmentCtor.call(env);window.env=env;env.saveMatch({sourceElement:document.querySelector('#ip')});});
 assert.equal(await page.locator('#ip').getAttribute('aria-invalid'),'true');
 assert.equal(await page.evaluate(()=>!!window.sent),false,'invalid address is not sent');
 console.log('ok: scrolling works without module CSS/JS; IPv4/IPv6 validity is visible and invalid IP is not submitted');
} finally {await browser.close();}
