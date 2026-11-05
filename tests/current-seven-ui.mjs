import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const css=(await Promise.all((await fs.readdir('public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('public/css/'+n,'utf8')))).join('\n')+await fs.readFile('panels/admin/080_access/010_users/edit/user.css','utf8');
const ui=await fs.readFile('public/js/gui/ui.js','utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for(const width of [1440,1194,390]){
 const page=await browser.newPage({viewport:{width,height:900},hasTouch:width!==1440}); const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://fixture.test/icons/tabler/**', async route => {
  const name=new URL(route.request().url()).pathname.split('/').pop();
  try { await route.fulfill({contentType:'image/svg+xml',body:await fs.readFile('public/icons/tabler/'+name,'utf8')}); } catch { await route.abort(); }
 });
 await page.setContent(`<base href="https://fixture.test/css/"><style>${css}:root{--cmsMainColor:#203c48;--cmsHighlightColor:#ffbb00;--cmsOnHighlight:#161c1f;--cmsLineStrong:#ccd5d9;}body{overflow:auto!important}article{padding:2rem;max-width:700px;background:var(--cmsSurface)}.cmslightbox{position:static!important}.userMailEntry{display:block}</style><body class=cms><article><form data-password-check><label>New password<input name=password type=password></label><label>Repeat password<input name=password_repeat type=password></label><button type=submit disabled>Save</button></form><button id=slow class=button data-loading><i class="cmsIcon cmsIcon-envelope"></i>Send test email</button><button id=more class=cmsBoxMore>Show all messages (28)</button><div class=cmslightbox><details class=userMailEntry><summary>Mail one</summary></details><details class=userMailEntry><summary>Mail two</summary></details><div class=signInTools>Footer</div></div><div class=HTMLeditorMenu><ul><li><button><i class="cmsIcon cmsIcon-crop-simple"></i></button></li></ul></div></article></body>`);
 await page.addScriptTag({content:ui.slice(ui.lastIndexOf('(function () {'))});
 assert.match(await page.locator('[data-password-hint]').textContent(),/minimum 15/);
 await page.locator('[name=password]').fill('a long passphrase');await page.locator('[name=password_repeat]').fill('different');assert.match(await page.locator('[data-password-hint]').textContent(),/Repeat/);assert.equal(await page.locator('[type=submit]').isEnabled(),false);
 await page.locator('[name=password_repeat]').fill('a long passphrase');assert.equal(await page.locator('[type=submit]').isEnabled(),true);
 await page.locator('[name=password]').fill('aaaaaaaaaaaaaaa');await page.locator('[name=password_repeat]').fill('aaaaaaaaaaaaaaa');assert.equal(await page.locator('[type=submit]').isEnabled(),false);assert.match(await page.locator('[data-password-hint]').textContent(),/Avoid repeating/);
 const before=await page.locator('#slow').boundingBox();await page.locator('#slow').evaluate(e=>e.classList.add('loading'));const after=await page.locator('#slow').boundingBox();assert.equal(before.width,after.width);assert.equal(before.height,after.height);
 const styles=await page.locator('#slow').evaluate(e=>({text:getComputedStyle(e).color,spinner:getComputedStyle(e,':before').borderRightColor}));assert.notEqual(styles.text,'rgba(0, 0, 0, 0)');assert.equal(styles.spinner,styles.text);
 assert.equal(await page.locator('.userMailEntry').last().evaluate(e=>getComputedStyle(e).borderBottomWidth),'0px');
 assert.equal(await page.locator('.passwordRule').count(),3);
 assert.notEqual(await page.locator('.cmsIcon-crop-simple').evaluate(e=>getComputedStyle(e).transform),'none');
 await page.locator('html').evaluate(e=>e.setAttribute('data-mode','dark'));
 const dark=await page.locator('#slow').evaluate(e=>({text:getComputedStyle(e).color,spinner:getComputedStyle(e,':before').borderRightColor}));assert.equal(dark.spinner,dark.text);
 await page.screenshot({path:`/private/tmp/fluxo-seven-${width}.png`});assert.deepEqual(errors,[]);await page.close();
 }
 const head=await fs.readFile('panels/admin/head.tpl.php','utf8');
 let script=head.slice(head.indexOf('(function () {'),head.indexOf('</script>',head.indexOf('(function () {'))).replace('<?= json_encode($appearance) ?>',JSON.stringify('system')).replace('<?= json_encode($darkGround) ?>',JSON.stringify('#161c1f')).replace('<?= json_encode($canvasColor) ?>',JSON.stringify('#eef2f4'));
 const appearance=await browser.newPage();await appearance.emulateMedia({colorScheme:'dark'});await appearance.setContent('<body>Appearance fixture</body>');await appearance.addScriptTag({content:script});assert.equal(await appearance.locator('html').getAttribute('data-mode'),'dark');await appearance.evaluate(()=>document.documentElement.setAttribute('data-appearance','light'));assert.equal(await appearance.locator('html').getAttribute('data-mode'),null);await appearance.evaluate(()=>document.documentElement.setAttribute('data-appearance','system'));await appearance.emulateMedia({colorScheme:'light'});await appearance.waitForFunction(()=>!document.documentElement.hasAttribute('data-mode'));await appearance.close();
 console.log('PASS System default follows light/dark; explicit Light remains honoured');
 console.log('PASS 1440/1194-touch/390-touch: password explanations, matching, visible spinner label without geometry change, main-color spinner, single mail separator, crop glyph');
} finally {await browser.close();}
