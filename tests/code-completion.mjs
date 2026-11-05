import vm from 'node:vm';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../public/js/gui/code-until.js',import.meta.url),'utf8');
for(const outcome of ['consumed','expired','closed']) {
 let interval, closed=0;const calls=[], timers=[];
 const destination={replaceChildren(child){this.child=child;}},feedback={replaceChildren(child){this.child=child;}},offer={hidden:false};
 const box={dataset:{},hidden:false,getAttribute(){return null;},querySelector(){return {click(){closed++;}};},querySelectorAll(){return [offer];}};
 const node={dataset:{},isConnected:true,hidden:false,getAttribute(key){return {'data-code-value':'TEST-ONLY','data-device-kind':'screen','data-code-refresh':'/admin/user/refreshScreenDevices'}[key]??null;},closest(){return box;},querySelector(selector){return selector.startsWith('[data-create-gate]')||selector==='[data-offer-renew]'?null:feedback;}};
 const document={readyState:'complete',visibilityState:'visible',documentElement:{},contains:()=>node.isConnected,addEventListener(){},querySelectorAll(selector){return selector==='[data-code-value]'?[node]:[];},querySelector(selector){return selector.startsWith('[data-device-feedback=')?destination:{};},createElement(){return {append(child){this.child=child;}};}};
 const context={document,location:{pathname:'/admin/user/',search:''},MutationObserver:class{observe(){}},URLSearchParams,Date,clearInterval(){},fetch:async()=>({json:async()=>({alive:false,used:outcome==='consumed'})}),sys:{core:{server:{call:class{send(url){calls.push(url);}}}}},window:{setInterval(fn){interval=fn;return 1;},setTimeout(fn,delay){timers.push({fn,delay});},sys:true}};
 context.window.sys=context.sys;vm.runInNewContext(source,context);
 node.isConnected=false;box.hidden=outcome==='closed';interval();await new Promise(resolve=>setImmediate(resolve));
 if(outcome==='consumed'){
  assert.equal(destination.child,undefined);assert.equal(feedback.child.className,'confirm');assert.equal(feedback.child.child.textContent,'Device signed in successfully.');assert.equal(offer.hidden,true);assert.equal(closed,0);assert.equal(calls.length,1);
  timers.forEach(t=>t.fn());assert.equal(calls.length,3);
 }else{assert.equal(destination.child,undefined);assert.equal(calls.length,0);assert.equal(closed,0);}
 console.log('ok: detached profile completion '+outcome);
}
