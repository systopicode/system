/** Shared smartphone sheets and table regressions. No project data required. */
import assert from 'node:assert/strict';
import {readFile, readdir} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
const root = new URL('../', import.meta.url);
const read = path => readFile(new URL(path, root), 'utf8');
const {chromium} = await import(process.env.FLUXO_PLAYWRIGHT_MODULE ? pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href : 'playwright');
const css = (await Promise.all((await readdir(new URL('public/css/', root))).filter(n => n.endsWith('.css')).sort().map(n => read('public/css/' + n)))).join('\n') + await read('panels/cms/090_tools/tools.css');
const controller = await read('panels/cms/mobile.js');
const tables = await read('public/js/gui/tableFit.js');
const menus = await read('public/js/gui/dropdownMenus.js');
const browser = await chromium.launch({channel:'chrome', headless:true});
try {
 const page = await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 const errors = [];
 page.on('pageerror', error => errors.push(error.message));
 await page.route('https://cms.example.test/**', route => route.fulfill({body:'<html></html>',contentType:'text/html'}));
 await page.goto('https://cms.example.test/cms/');
 await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms tools"><header class="cmsMobileHeader"><button data-cms-window="menu">Menu</button><strong data-cms-module></strong></header><main><div class="main"><nav><ul><li class="active"><a href="/cms/tools/">Tools</a></li></ul></nav></div><div class="pylon cms"><nav>Navigation</nav></div><div class="content"><h1>Tools</h1></div></main><button class="cmsMobileBackdrop" hidden></button></body>`);
 await page.evaluate(controller);
 await page.addScriptTag({content:await read("public/js/ext/jquery.js")});

 await page.addScriptTag({content:menus});
 await page.evaluate(() => {
  document.body.insertAdjacentHTML('beforeend', `<div class="cms cmslightbox"><section><header><h1>First</h1><ul class="barmenu"><li><button class="button">Download</button></li><li><button class="button">Clear</button></li><li><button data-box-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li></ul></header><main><p>Dialog content</p></main><footer>Footer</footer></section></div><div class="cms cmslightbox"><section><header><h1>Nested code</h1><button data-box-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></header><main><div class="qrCodeBar"><strong>ABCD-EFGH</strong><button class="button">Copy code</button></div><div class="qrZoomStage"><div class="fileFrame"><div class="frameView"><svg viewBox="0 0 100 100"><rect width="100" height="100"/></svg></div></div></div></main><footer>Nested footer</footer></section></div>`);
 });
 await page.waitForTimeout(350);
 const boxes = page.locator('.cmslightbox');
 assert.equal(await boxes.nth(1).evaluate(n => n.classList.contains('cmsNestedDialog')), true);
 const nested = await boxes.nth(1).locator(':scope > section').boundingBox();
 assert.ok(nested.x >= 15 && nested.width <= 360 && nested.y > 0 && nested.y + nested.height <= 844);
 assert.equal(await boxes.first().getByRole('button',{name:'More',exact:true}).count(), 1);
 assert.equal(await boxes.first().getByRole('button',{name:'Close',exact:true}).isVisible(), true);
 const qr = await boxes.nth(1).locator('.qrZoomStage').boundingBox();
 const code = await boxes.nth(1).locator('.qrCodeBar').boundingBox();
 assert.ok(qr.width <= nested.width && qr.y >= code.y + code.height);
 assert.equal(await boxes.nth(1).locator('footer').evaluate(n => getComputedStyle(n).backgroundColor), await boxes.nth(1).locator('section').evaluate(n => getComputedStyle(n).backgroundColor));
 await boxes.nth(1).evaluate(n => n.querySelector('section').insertAdjacentHTML('beforeend', '<footer class="cmsDialogStatus"><span class="cmsSaveState" role="status">Saved</span></footer>'));
 await page.waitForTimeout(100);
 const status = await boxes.nth(1).locator('.cmsSaveState').boundingBox();
 assert.ok(status.x >= 0 && status.x + status.width <= 390 && status.height < 45);
 assert.equal(await boxes.nth(1).locator('.cmsSaveState').evaluate(n => getComputedStyle(n).position), 'fixed');
 await boxes.nth(1).evaluate(n => n.remove());
 await boxes.first().getByRole('button',{name:'More',exact:true}).click();
 assert.equal(await page.locator('body > .cmsMobileActionMenu.is-open').getByRole('button',{name:'Download'}).isVisible(),true);
 await page.locator('body > .cmsMobileActionMenu.is-open').getByRole('button',{name:'Download'}).click();
 await page.evaluate(() => { document.querySelectorAll('.cmslightbox').forEach(n => n.remove()); document.querySelector('.pylon').remove(); document.querySelector('.content').innerHTML='<div class="schemaScroll"><table><tbody class="schemaCard"><tr class="schemaCardHead"><th colspan="3"><div class="schemaCardTitle"><h2>Long table name</h2><span class="logPill">Rows</span></div></th></tr><tr><td>column_name</td><td>Database value</td><td>Model value</td></tr></tbody></table></div>'; window.sys={}; });
 await page.evaluate(tables);
 await page.evaluate(() => sys.fitTables.fit(document.querySelector('.schemaScroll')));
 const toggle = page.getByRole('button',{name:'Details: column_name'});
 await toggle.click();
 assert.equal(await toggle.getAttribute('aria-expanded'),'true');
 assert.equal(await page.getByText('Database value',{exact:true}).isVisible(),true);
 await toggle.click();
 assert.equal(await page.getByText('Database value',{exact:true}).isVisible(),false);
 for (const width of [1194,1440]) {
  await page.setViewportSize({width,height:900});
  await page.evaluate(() => sys.fitTables.fit(document.querySelector('.schemaScroll')));
  assert.equal(await page.locator('.schemaScroll.stacked').count(),0);
  assert.equal(await page.getByText('Database value',{exact:true}).isVisible(),true);
 }
 assert.deepEqual(errors,[]);
 console.log('PASS: smartphone nested dialog, header overflow/close, QR boundaries, themed footer, database accordion, tablet/desktop table reset; no JS errors');
} finally { await browser.close(); }
