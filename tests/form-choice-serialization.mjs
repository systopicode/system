import vm from 'node:vm'; import fs from 'node:fs'; import assert from 'node:assert/strict';
const merge=(target,source)=>{for(const [key,value] of Object.entries(source)){if(value&&typeof value==='object'&&!Array.isArray(value)){target[key]=merge(target[key]||{},value);}else target[key]=value;}return target;};
const context=vm.createContext({sys:{core:{event:{}}},obj:{clone:v=>structuredClone(v),extend:merge},l(){}});
vm.runInContext(fs.readFileSync(new URL('../public/js/core/event/event.pathItem.js',import.meta.url),'utf8'),context);
for(const method of ['put','post','get']){
 const form={tagName:'FORM',dataset:{},getAttribute:name=>name==='method'?method:null,querySelectorAll:()=>[]};
 const input=(name,type,value,checked=false,disabled=false)=>({tagName:'INPUT',dataset:{},name,type,value,checked,disabled,form,getAttribute:()=>null,hasAttribute:()=>false});
 form.elements=[input('kind','radio','text'),input('kind','radio','switch',true),input('kind','radio','group'),input('templates[]','checkbox','home',true),input('templates[]','checkbox','project',true),input('templates[]','checkbox','about'),input('off','checkbox','on'),input('ignored','text','x',false,true)];
 const data=new context.sys.core.event.pathItem(form,{}).getFormData(form)[method==='put'?'form':method];
 assert.equal(data.kind,'switch');assert.deepEqual(Array.from(data.templates),['home','project']);assert.equal(data.off,0);assert.equal(data.ignored,undefined);
 console.log('PASS '+method+': chosen radio, template array, unchecked switch and disabled field');
}
