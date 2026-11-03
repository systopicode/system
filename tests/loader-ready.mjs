/** Readiness depends on the DOM and required JS, never image/window load. */
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=await readFile(new URL('../public/js/loader.js',import.meta.url),'utf8');
const listeners=new Map();let builds=0;let deadline;
const document={querySelector(){return null;},dispatchEvent(){},readyState:'loading',addEventListener:(n,f)=>listeners.set(n,f),removeEventListener:(n,f)=>{if(listeners.get(n)===f)listeners.delete(n);}};
const sys={core:{},onJSReadyFuncs:[],singletons:{},classes:{},onload(){},flushOnJSReady(){},buildSingletons(){builds++;},buildObjects(){},flushOnLibReady(){}};
vm.runInNewContext(source,{setTimeout(fn){deadline=fn;return 1;},clearTimeout(){deadline=null;},sys,document,settings:{sysRoot:"/"},CustomEvent:class {constructor(type){this.type=type;}},l(){},p(){},t(){},f(){}});
sys.loader.onScriptsReady();assert.equal(builds,0);
document.readyState='interactive';listeners.get('readystatechange')();
assert.equal(builds,1,'initialize before images finish (document is not complete)');
assert.equal(listeners.size,0);
sys.loader.scriptsLoading.set('required',{});sys.loader.onScriptsReady();assert.equal(builds,1,'required JS still blocks initialization');
sys.loader.scriptsLoading.clear();sys.loader.onScriptsReady();assert.equal(builds,2,'initialize after required JS arrives even with resources pending');
console.log('ok: DOM and JS readiness initialize UI before full resource load');

sys.loader.scriptsFailed.set('missing',{});sys.loader.onScriptsReady();assert.equal(builds,2,'failed dependencies do not initialize a partially usable interface');

const scriptListeners=new Map();
document.createElement=()=>({addEventListener:(name,fn)=>scriptListeners.set(name,fn)});
document.head={append(){}};
sys.loader.scriptsFailed.clear();
sys.script=sys.core.script.__class;
sys.loader.addScripts([{id:'missing-file',root:'sys',src:'missing.js'}]);
scriptListeners.get('error')();
assert.equal(sys.loader.scriptsLoading.size,0,'a missing file does not leave the pending set stuck');
assert.equal(sys.loader.scriptsFailed.has('missing-file'),true,'missing file is reported separately');
console.log('ok: script network failures become visible failure state');

sys.loader.status('loading');assert.equal(typeof deadline,'function');deadline();
let failed=false;sys.loader.scriptsFailed.clear();sys.buildObjects=()=>{throw new Error('broken init')};
try{sys.loader.onScriptsReady()}catch{failed=true}
assert.ok(failed,'initialization error remains visible, not suppressed');
console.log('ok: loading timeout and initialization failure are bounded');
