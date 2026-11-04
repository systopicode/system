/** Layout regression only; native iOS/Android keyboard and browser chrome need device QA.
 * FLUXO_PLAYWRIGHT_MODULE=/path/to/playwright-core/index.mjs node tests/auth-mobile-ui.mjs
 */
import {readFile, readdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const root = new URL('../', import.meta.url);
const read = path => readFile(new URL(path, root), 'utf8');
const {chromium} = await import(process.env.FLUXO_PLAYWRIGHT_MODULE ? pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href : 'playwright');
const files = (await readdir(new URL('public/css/', root))).filter(name => name.endsWith('.css')).sort();
const base = (await Promise.all(files.map(name => read('public/css/' + name)))).join('\n');
const auth = await read('panels/auth/auth.css');
const head = await read('panels/admin/head.tpl.php');
assert.match(head, /viewport-fit=cover,interactive-widget=<\?= \$onAuth \? 'resizes-visual' : 'resizes-content'/);
const template = new URL('src/Auth/login.card.tpl.php', root).pathname;
const render = method => method === 'signedIn' ? execFileSync('php', [new URL('tests/auth-screen-fixture.php', root).pathname, method], {encoding:'utf8'}) : execFileSync('php', ['-r', `class http {public static $root='/';} class message {static function flush(...$args){return '';}} $authMethod=$argv[1];$authCodes=true;include $argv[2];`, method, template], {encoding:'utf8'}).replace(/<script>[\s\S]*?<\/script>/g, '');
const methods = Object.fromEntries(['', 'password', 'code', 'device', 'signedIn'].map(method => [method, render(method)]));
const renderLegal = name => execFileSync('php', ['-r', `class http {public static $root='/';} require $argv[1];include $argv[2];`, new URL('tests/bootstrap.php', root).pathname, new URL('panels/auth/' + name + '.tpl.php', root).pathname], {encoding:'utf8'});
const body = content => `<body class="cms"><div class="authCard cms"><a class="logo"><img alt="fluxo" width="120" height="40"></a>${content}</div><footer class="authFooter"><span>© 2026 systopic GmbH · GUI 0.5.5</span><nav><a>License</a><a>Imprint</a><a>Privacy</a><a>Website</a><a>Help</a></nav></footer></body>`;
const markup = (content, sheet=auth, globals=base) => `<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,interactive-widget=resizes-visual"><style>@layer cms {${globals}\n${sheet}}</style>${body(content)}`;
const browser = await chromium.launch({channel:process.env.FLUXO_BROWSER_CHANNEL || 'chrome', headless:true});
try {
 const page = await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 for (const width of [320,390,430,768,1200]) {
  await page.setViewportSize({width,height:width===1200?1400:844});
  for (const method of Object.keys(methods)) {
   await page.setContent(markup(methods[method]));
   const geometry = await page.evaluate(() => {
    const card=document.querySelector('.authCard'), style=getComputedStyle(document.body), box=card.getBoundingClientRect();
    return {height:box.height,top:box.top,min:innerHeight-parseFloat(style.paddingTop)-parseFloat(style.paddingBottom),max:getComputedStyle(card).maxHeight,wide:document.documentElement.scrollWidth>innerWidth,footer:document.querySelector('.authFooter').getBoundingClientRect().top};
   });
   assert.ok(geometry.height>=geometry.min-1,JSON.stringify({width,method,geometry}));
   assert.equal(geometry.max,'none');
   assert.equal(geometry.wide,false);
   assert.ok(geometry.footer>=geometry.top+geometry.height);
   await page.locator('.authFooter').scrollIntoViewIfNeeded();
   assert.ok(await page.locator('.authFooter').isVisible());
  }
 }
 // Simulate safe-area values in CSS, without claiming to emulate native chrome.
 await page.setViewportSize({width:390,height:844});
 await page.setContent(markup(methods.password));
 await page.addStyleTag({content:'html:has(.authCard){--authSafeTop:59px;--authSafeBottom:34px}'});
 await page.evaluate(()=>scrollTo(0,0));
 assert.equal(await page.locator('.authCard').evaluate(node=>node.getBoundingClientRect().top),59);
 const lighting = await page.evaluate(() => {
  const body=getComputedStyle(document.body), light=getComputedStyle(document.body,'::before');
  return {image:light.backgroundImage,position:light.position,top:parseFloat(light.top),bottom:parseFloat(light.bottom),mask:light.maskImage,pointer:light.pointerEvents,bodyImage:body.backgroundImage};
 });
 assert.equal(lighting.position,'fixed');
 assert.ok(lighting.top>=59 && lighting.bottom>=34);
 assert.match(lighting.image,/radial-gradient/);
 assert.match(lighting.mask,/linear-gradient/);
 assert.equal(lighting.bodyImage,'none');
 assert.equal(lighting.pointer,'none');
 // Changing all theme colours must also change both light sources.
 await page.addStyleTag({content:':root{--cmsMainColor:#643b5a;--cmsHighlightColor:#7de3be;--cmsBgColor:#faf5ef}'});
 const changedLight=await page.evaluate(()=>({image:getComputedStyle(document.body,'::before').backgroundImage,base:getComputedStyle(document.body).backgroundColor}));
 assert.notEqual(changedLight.image,lighting.image);
 assert.equal(changedLight.base,'rgb(100, 59, 90)');
 for(const viewport of [{width:390,height:844},{width:844,height:390},{width:1440,height:900}]) {
  await page.setViewportSize(viewport);
  const edge=await page.evaluate(()=>{const light=getComputedStyle(document.body,'::before');return {top:parseFloat(light.top),bottom:parseFloat(light.bottom)};});
  assert.ok(edge.top>=59 && edge.bottom>=34);
 }
 await page.setViewportSize({width:390,height:844});
 // Long forms can grow; pressure from a short viewport must not clip actions.
 await page.setViewportSize({width:390,height:360});
 await page.setContent(markup(methods.password.replace('</form>', '<div style="height:650px"></div><button type="button" id="last">Last action</button></form>')));
 assert.ok(await page.locator('.authCard').evaluate(node=>node.getBoundingClientRect().height>650));
 await page.locator('[name=username]').focus();
 await page.locator('[name=password]').focus();
 await page.locator('#last').scrollIntoViewIfNeeded();
 assert.ok(await page.locator('#last').evaluate(node=>{const b=node.getBoundingClientRect();return b.top>=0 && b.bottom<=innerHeight;}));
 // Legal pages retain a bounded inner text scroller and their own foot.
 await page.setViewportSize({width:390,height:844});
 await page.setContent(markup('<h1>License</h1><main class="authText"><div class="authScroll"><div style="height:2000px">Legal text</div></div><div class="authLinks">Legal links</div></main><p class="authFoot">Back to sign in</p>'));
 assert.ok(await page.locator('.authScroll').evaluate(node=>node.scrollHeight>node.clientHeight && getComputedStyle(node).overflowY==='auto'));
 // Render the actual legal templates, including the expanded CMS privacy text.
 for (const name of ['privacy', 'license']) {
  await page.setContent(markup(renderLegal(name)));
  await page.evaluate(()=>scrollTo(0,0));
  assert.ok(await page.locator('.authScroll').evaluate(node=>node.scrollHeight>node.clientHeight && getComputedStyle(node).overflowY==='auto'));
  await page.locator('.authScroll').evaluate(node=>node.scrollTop=node.scrollHeight);
  assert.ok(await page.locator('.authScroll').evaluate(node=>node.scrollTop>0));
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.locator('.authFooter').scrollIntoViewIfNeeded();
 }
 // A method switch replaces only the existing main, as a partial render does.
 await page.setContent(markup(methods.password));
 const height = await page.locator('.authCard').evaluate(node=>node.getBoundingClientRect().height);
 for(const method of ['code','device','','password']) {
  await page.locator('.authSignin').evaluate((node,html)=>node.outerHTML=html,methods[method]);
  assert.equal(await page.locator('.authCard').evaluate(node=>node.getBoundingClientRect().height),height);
 }
 if(process.env.FLUXO_SCREENSHOT) {await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:process.env.FLUXO_SCREENSHOT,fullPage:true});}
 // Compare desktop geometry against the committed styles, not a mirrored expectation.
 const old = path => execFileSync('git',['show','HEAD:'+path],{cwd:root,encoding:'utf8'});
 const oldBase=base.replace(await read('public/css/x_responsive.css'),old('public/css/x_responsive.css'));
 await page.setViewportSize({width:1440,height:1000});
 const desktop=async (sheet,globals)=>{await page.setContent(markup(methods.password,sheet,globals));return page.locator('.authCard').evaluate(node=>{const b=node.getBoundingClientRect();return {x:b.x,y:b.y,width:b.width,height:b.height};});};
 assert.deepEqual(await desktop(auth,base),await desktop(old('panels/auth/auth.css'),oldBase));
 // Auth-only background vanishes when the CMS replaces the card.
 await page.setContent(markup(methods.password));
 await page.locator('body').evaluate(node=>node.innerHTML='<main>Dashboard</main>');
 assert.equal(await page.evaluate(()=>getComputedStyle(document.body,'::before').content),'none');
 assert.equal(await page.evaluate(()=>getComputedStyle(document.body,'::after').content),'none');
 // During a hard reload the shell can paint before any content is parsed.
 await page.setContent(`<style>@layer cms {${base}\n${auth}}</style><body class="cms system"></body>`);
 assert.equal(await page.evaluate(()=>getComputedStyle(document.body).backgroundColor),'rgb(240, 243, 244)');
 assert.equal(await page.evaluate(()=>getComputedStyle(document.documentElement).backgroundColor),'rgb(240, 243, 244)');
 assert.equal(await page.evaluate(()=>getComputedStyle(document.body,'::before').content),'none');
 console.log('ok: mobile methods, safe-area content inset, theme-aware side lights, quiet top/bottom edges, long forms, short viewport, legal scroller, method switches, desktop baseline, auth-to-CMS cleanup');
} finally {await browser.close();}
