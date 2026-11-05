import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=readFileSync(new URL('../public/js/gui/code-until.js',import.meta.url),'utf8');
for(const [renew,visibility] of [[true,'visible'],[true,'hidden'],[false,'visible']]){
 let reloads=0,requests=0,replacements=0;const live={hidden:false},expired={hidden:true},next={};
 const node={dataset:{},isConnected:true,offsetParent:{},getAttribute:k=>k==='data-code-until'?String(Math.floor(Date.now()/1000)-2):'0',hasAttribute:k=>k==='data-code-renew'&&renew,querySelector:k=>k==='[data-code-expired]'?expired:null,querySelectorAll:()=>[live],replaceWith(value){assert.equal(value,next);replacements++;}};
 const document={documentElement:{},readyState:'complete',visibilityState:visibility,querySelector:()=>node,querySelectorAll:k=>k==='[data-code-until]'?[node]:[],addEventListener(){}};
 vm.runInNewContext(source,{document,window:{setTimeout(){},setInterval(){}},MutationObserver:class{observe(){}},DOMParser:class{parseFromString(){return {querySelector:()=>next};}},fetch:async()=>{requests++;return {ok:true,json:async()=>({html:'fixture'})};},location:{pathname:'/auth/',search:'?method=device_code',reload(){reloads++;}},Date});
 await new Promise(resolve=>setImmediate(resolve));
 assert.equal(reloads,0);assert.equal(requests,renew&&visibility==='visible'?1:0);assert.equal(replacements,requests);
 assert.equal(live.hidden,!renew);assert.equal(expired.hidden,renew);
 console.log(`ok: renew=${renew}, visibility=${visibility}: only QR block changes, no page reload or interim message`);
}
