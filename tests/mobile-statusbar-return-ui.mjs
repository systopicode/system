/** Run with FLUXO_PLAYWRIGHT_MODULE pointing to playwright/index.mjs if not installed locally. */
import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium} = await import(process.env.FLUXO_PLAYWRIGHT_MODULE ? pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href : 'playwright');
const root = new URL('../', import.meta.url);
const read = path => readFile(new URL(path, root), 'utf8');
const template = await read('panels/cms/body.tpl.php');
const stripPHP = text => text.replace(/<\?(?:php|=)[\s\S]*?\?>/g, '');
const chrome = stripPHP(template.match(/<header class="cmsMobileHeader[\s\S]*?<\/header>/)[0]);
const backdrop = template.match(/<button type="button" class="cmsMobileBackdrop[^>]*><\/button>/)[0];
const styles = (await Promise.all(['00_theme', '18_ui', '20_base', '23_pylon', '30_layout', '40_nav', '45_sidebar', '50_forms', '60_lightbox', '62_find', '10_dropdown', '70_components', 'x_responsive'].map(name => read('public/css/' + name + '.css')))).join('\n');
const controller = await read('panels/cms/mobile.js');
const pageStyles = await readFile(new URL('../cms/public/css/pages.css', root), 'utf8');
const pagesCSS = pageStyles + (await Promise.all(['pages.mml.css', 'pages.tree.css'].map(file => readFile(new URL('../cms/panels/cms/010_pages/' + file, root), 'utf8')))).join('\n');
const browser = await chromium.launch({headless: true, channel: process.env.FLUXO_BROWSER_CHANNEL || 'chrome'});
try {
 const page = await browser.newPage({viewport: {width: 390, height: 844}, isMobile: true, hasTouch: true});
 page.setDefaultTimeout(5000);
 const errors = [];
 page.on('pageerror', error => errors.push(error.message));
 await page.route('https://cms.example.test/**', route => route.fulfill({body: '<html></html>', contentType: 'text/html'}));
 await page.goto('https://cms.example.test/cms/pages/');
 await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${styles + pagesCSS}</style><body class="cms pages">${chrome}<div class="sysdebug" style="position:fixed">Debug</div><main data-debug><div class="main cms"><header>FLUXO</header><nav><div class="bottomMenu"><span class="cmsMobileOnly cmsStage DEV">DEV</span></div><ul><li class="active"><a class="ajax" href="/cms/pages/">Pages</a></li></ul></nav></div><div class="pylon cms"><header></header><nav><div class="tree" style="overflow:auto;height:400px;flex:none"><a class="label ajax" href="?id=10">Home</a><div style="height:1500px"></div></div></nav><footer style="max-height:300px"><div class="mml"><div class="mmlContent">Media<input aria-label="Upload" type="file"></div></div></footer></div><div class="content"><header class="cms"><div class="pageHead"><nav class="crumbs">Home</nav><div class="pageTitleRow"><h1 class="pageTitle">Home page</h1><button class="pageTitleEdit">Edit title</button></div></div><div class="cmsPageTools"><button type="button" class="cmsMobileOnly cmsMobileMore" data-cms-more aria-label="Page tools" aria-expanded="false">…</button><div class="cmsPageToolsMenu"><div class="leftAlign"><div class="langSwitch"><button type="button" aria-label="Language">DE</button></div></div><div class="deviceSwitch"><button data-cms-device="fit">Fit</button><button data-cms-device="mobile">Mobile</button></div><button type="button" class="cmsMobileOnly" data-cms-properties>Properties</button><button type="button" class="cmsMobileOnly" data-cms-window="media">Media Library</button></div></div></header><div class="flexRow"><div class="flexColumn"><input aria-label="Title" value="Saved"><div style="height:1000px">Preview</div></div><aside class="cms"><div class="flexContainer">Properties<input aria-label="SEO"></div></aside></div></div></main>${backdrop}</body>`);
 await page.evaluate(controller);
 await page.evaluate(() => document.addEventListener('click', event => { if (event.target.closest('a[href]')) event.preventDefault(); }));
 // Re-running body scripts after an AJAX response must replace, not duplicate, listeners.
 await page.evaluate(controller);
 const tail = template.slice(template.indexOf('var storedDevice'), template.indexOf('</script>', template.indexOf('var storedDevice')));
 await page.evaluate(() => localStorage.setItem('cmsDevice', 'desktop'));
 await page.evaluate('(function () { var root = document.body; var mobile = matchMedia("(max-width: 1024px)"); ' + tail);
 assert.equal(await page.locator('body').getAttribute('data-cms-device'), 'fit');
 const swipeClose = async selector => {
  await page.waitForTimeout(320);
  const grip = await page.locator(selector + ' .cmsMobileGrip').boundingBox();
  await page.mouse.move(grip.x + 10, grip.y + 2);
  await page.mouse.down(); await page.mouse.move(grip.x + 10, grip.y + 125); await page.mouse.up();
 };
 const state = () => page.locator('body').getAttribute('data-cms-window');
 const openEdit = async () => {
  await page.locator('.tree').evaluate(node => node.scrollTop = 0);
  await page.locator('.tree a.label').click();
 };
 const openMedia = async () => {
  await page.locator('[data-cms-more]').click();
  await page.locator('.cmsPageToolsMenu [data-cms-window="media"]').click();
 };
 assert.equal(await page.locator('#cmsChromeEdge').count(),1);
 for(const width of [1440,390,1194,390,1440]) {
 await page.setViewportSize({width,height:900});
 await page.waitForTimeout(100);
 assert.equal(await page.locator('#cmsChromeEdge').count(),await page.evaluate(()=>matchMedia('(max-width: 1024px), (orientation: portrait) and (max-width: 1400px)').matches)?1:0);
 }
 assert.deepEqual(errors,[]);
 console.log('PASS: real mobile controller removes Safari edge on desktop, repeated phone/tablet/desktop transitions; no JS errors');
} finally { await browser.close(); }
