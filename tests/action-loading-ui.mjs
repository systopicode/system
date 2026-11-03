import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try{const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.setContent('<button data-loading data-on_click="check">Check now</button>');
await page.evaluate(()=>{window.sys={core:{},http:{root:'/',path2pathStr:()=>'',diffUrl:x=>x}};window.obj={extend:Object.assign,getQueryString:()=>''};window.l=window.p=()=>{};window.event={};window.XMLHttpRequest=class{static DONE=4;constructor(){window.request=this;this.upload={};this.listeners={};}addEventListener(n,fn){this.listeners[n]=fn;}open(){}setRequestHeader(){}send(){}end(status){this.status=status;this.readyState=4;this.onreadystatechange();this.listeners.loadend();}};});
await page.addScriptTag({path:'public/js/core/server/server.js'});
await page.evaluate(()=>{Object.assign(sys.core.server,{scrollPositions:()=>[],headersFor:()=>({}),backupStep:()=>false,showBusy(){},settled(){}});});
for(const status of [0,503]){await page.evaluate(()=>{const call=new sys.core.server.call();call.event={type:'click',element:document.querySelector('button'),data:{},url:{path:[],query:{}}};call.send(call.event.url);});assert.equal(await page.locator('button').getAttribute('class'),'loading');await page.evaluate(status=>request.end(status),status);assert.equal(await page.locator('button').getAttribute('class'),'');}
await page.locator('button').evaluate(e=>{e.removeAttribute('data-loading');e.setAttribute('role','tab');});
await page.evaluate(()=>{const call=new sys.core.server.call();call.event={type:'click',element:document.querySelector('button'),data:{},url:{path:[],query:{}}};call.send(call.event.url);});assert.equal(await page.locator('button').getAttribute('class'),'');await page.evaluate(()=>request.end(503));
assert.deepEqual(errors,[]);console.log('PASS actual shared request lifecycle: loading while pending; removed on network abort and server failure');
}finally{await browser.close();}
