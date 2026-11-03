import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE || 'playwright');
const root=new URL('../',import.meta.url).pathname;
let css=(await Promise.all((await fs.readdir(root+'public/css')).filter(x=>x.endsWith('.css')).sort().map(x=>fs.readFile(root+'public/css/'+x,'utf8')))).join('\n');
const b=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1440,1194,390])for(const mode of ['light','dark'])for(const panel of ['backup','user','settings','tools','pages']){
 const p=await b.newPage({viewport:{width,height:834}});let errors=[];p.on('pageerror',e=>errors.push(e.message));
 const pylon=['settings','tools','pages'].includes(panel)?'<div class=pylon><nav>Navigation</nav></div>':'';
 await p.setContent(`<style>${css}</style><html data-mode="${mode}"><body class="cms ${panel}"><main><div class="main cms"><header>fluxo</header><nav>Main navigation</nav></div>${pylon}<div class=content><header class=cms></header><div class=flexRow><div class=flexColumn><section class=cms><h1>${panel}</h1><button class=button>Action</button></section></div></div></div></main></body></html>`);
 if(width>1024&&panel!=='pages'){
 const v=await p.locator('.content').evaluate(e=>{const c=getComputedStyle(e),r=getComputedStyle(e.querySelector('.flexRow'));return {left:parseFloat(c.borderTopLeftRadius),right:parseFloat(c.borderTopRightRadius),rowRight:parseFloat(r.borderTopRightRadius),rowLeft:parseFloat(r.borderTopLeftRadius),overflow:c.overflow}});
 assert.ok(v.right>0&&v.rowRight===v.right,JSON.stringify(v));if(!pylon)assert.ok(v.left>0&&v.rowLeft===v.left,JSON.stringify(v));assert.notEqual(v.overflow,'hidden');
 }
 assert.equal(errors.length,0);if(width===1440&&['backup','settings'].includes(panel))await p.screenshot({path:`/private/tmp/corners-${panel}-${mode}.png`});await p.close();
}console.log('PASS 30 layout fixtures: backup, profile, settings, tools, Pages; 1440/1194/390; light/dark. Desktop shoulders inherit; no window clipping.');}finally{await b.close()}
