/** Regression: auth module styles may remain loaded after a login transition. */
import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {Window}=await import(pathToFileURL(process.env.FLUXO_DOM_MODULE || 'node_modules/happy-dom/lib/index.js').href);
const base=await readFile(new URL('../public/css/20_base.css',import.meta.url),'utf8');
const auth=await readFile(new URL('../panels/cms/auth/auth.css',import.meta.url),'utf8');
// Happy DOM cannot evaluate CSS nesting. Retain the real html/body selectors
// and their initial layout declarations; the nested card styling is irrelevant here.
const layout=Array.from(auth.matchAll(/^(html[^{}]*|body[^{}]*)\{([^{}]*)/gm),match=>match[1]+'{'+match[2].slice(0,match[2].lastIndexOf(';')+1)+'}').join('\n');
assert.ok(layout.includes('align-items: center'));
const cms='<main><nav>Menu</nav><div class="content"><div class="dashboard">Dashboard</div></div></main>';
const windows=[];
const render=(markup,authLoaded)=>{
 const window=new Window({url:'https://cms.example.test/'});windows.push(window);
 const document=window.document;document.body.className='cms';document.body.innerHTML=markup;
 const style=document.createElement('style');style.textContent=base+(authLoaded?layout:'');document.head.append(style);
 return {window,document,style};
};
const dimensions=({window,document})=>{
 const body=window.getComputedStyle(document.body),html=window.getComputedStyle(document.documentElement);
 return {align:body.alignItems,justify:body.justifyContent,padding:body.padding,height:body.height,htmlHeight:html.height};
};
const baseline=render(cms,false),cached=render(cms,true);
assert.deepEqual(dimensions(cached),dimensions(baseline));
console.log('ok: cached auth styles do not affect the CMS');
const login=render('<div class="authCard"><main>Sign in</main></div>',true);
assert.equal(dimensions(login).align,'center');
assert.notEqual(dimensions(login).padding,dimensions(baseline).padding);
console.log('ok: auth layout still applies to the sign-in card');
const selectors=Array.from(login.style.sheet.cssRules).map(rule=>rule.selectorText).filter(selector=>selector?.includes(':has(.authCard)'));
assert.equal(selectors.length,2);
login.document.body.innerHTML=cms;
for(const selector of selectors) assert.equal(login.document.querySelector(selector),null);
console.log('ok: login-to-CMS markup stops matching both auth layout rules without removing the stylesheet');
for(const window of windows) await window.happyDOM.abort();
