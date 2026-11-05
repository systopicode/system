import vm from 'node:vm';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../public/js/gui/code-until.js',import.meta.url),'utf8');
for (const mode of ['visible','hidden','complete']) {
 const calls=[];let visibility;
 const box={hidden:mode==='hidden',dataset:mode==='complete'?{codeComplete:'1'}:{}};
 const button={disabled:false};
 const form={action:'/admin/user/createDeviceCode',querySelectorAll:()=>[{name:'kind',value:'self'},{name:'account',value:'42'}],querySelector:()=>button};
 const node={dataset:{},isConnected:true,offsetParent:{},getAttribute:key=>key==='data-code-until'?'1':'0',hasAttribute:key=>key==='data-offer-renew',querySelector:key=>key==='form'?form:null,closest:()=>box};
 const document={documentElement:{},readyState:'complete',visibilityState:'visible',querySelectorAll:key=>key==='[data-code-until]'||key==='[data-offer-renew]'?[node]:[],addEventListener(type,fn){if(type==='visibilitychange')visibility=fn;}};
 const sys={core:{server:{call:class{constructor(data){this.data=data;}send(url){calls.push({url,data:this.data,background:this.background});const hooks={done(fn){fn();return hooks;},fail(){return hooks;}};return hooks;}}}}};
 vm.runInNewContext(source,{document,window:{sys,setInterval(){},setTimeout(){}},sys,MutationObserver:class{observe(){}},Date});
 await new Promise(resolve=>setImmediate(resolve));
 assert.equal(calls.length,mode==='visible'?1:0);
 if(mode==='visible') {assert.equal(calls[0].background,true);assert.equal(calls[0].data.form.kind,'self');assert.equal(calls[0].data.form.account,'42');assert.equal(calls[0].url,form.action);assert.equal(button.disabled,false);}
 if(mode==='hidden') {box.hidden=false;visibility();await new Promise(resolve=>setImmediate(resolve));assert.equal(calls.length,1);}
 console.log('ok: backend expiry '+mode+' uses quiet existing command transport, resumes when visible, never renews completed code');
}
