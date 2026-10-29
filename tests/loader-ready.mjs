/** Readiness depends on the DOM and required JS, never image/window load. */
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=await readFile(new URL('../public/js/loader.js',import.meta.url),'utf8');
const listeners=new Map();let builds=0;
const document={readyState:'loading',addEventListener:(n,f)=>listeners.set(n,f),removeEventListener:(n,f)=>{if(listeners.get(n)===f)listeners.delete(n);}};
const sys={core:{},onJSReadyFuncs:[],singletons:{},classes:{},onload(){},flushOnJSReady(){},buildSingletons(){builds++;},buildObjects(){},flushOnLibReady(){}};
vm.runInNewContext(source,{sys,document,l(){},p(){},t(){},f(){}});
sys.loader.onScriptsReady();assert.equal(builds,0);
document.readyState='interactive';listeners.get('readystatechange')();
assert.equal(builds,1,'initialize before images finish (document is not complete)');
assert.equal(listeners.size,0);
sys.loader.scriptsLoading.set('required',{});sys.loader.onScriptsReady();assert.equal(builds,1,'required JS still blocks initialization');
sys.loader.scriptsLoading.clear();sys.loader.onScriptsReady();assert.equal(builds,2,'initialize after required JS arrives even with resources pending');
console.log('ok: DOM and JS readiness initialize UI before full resource load');
