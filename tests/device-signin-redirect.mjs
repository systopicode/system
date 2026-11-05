import vm from 'node:vm';import fs from 'node:fs';import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../public/js/gui/code-until.js',import.meta.url),'utf8');
for(const mode of ['absent','throws','ready']){
 const timers=[];const window={setTimeout(fn){timers.push(fn);},setInterval(){}};
 if(mode!=='absent')window.sys={passkeys:{showSignedIn(){if(mode==='throws')throw Error('fixture');}}};
 const location={};vm.runInNewContext(source,{window,location,document:{readyState:'complete',documentElement:{},querySelectorAll(){return [];},addEventListener(){}},MutationObserver:class{observe(){}},console:{error(){}}});
 const block={isConnected:true,dataset:{}};window.finishDeviceSignIn(block,{redirect:'/fixture-target'});window.finishDeviceSignIn(block,{redirect:'/fixture-target'});
 assert.equal(timers.length,1);timers[0]();assert.equal(location.href,'/fixture-target');console.log('ok: redirect survives success UI '+mode);
}
