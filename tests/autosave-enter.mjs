import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const listeners = new Map();
vm.runInNewContext(await readFile(new URL('../public/js/core/event/initEvents.js', import.meta.url), 'utf8'), {
    sys: {}, document: {addEventListener(type, listener) { listeners.set(type, listener); }}
});
const enter = listeners.get('keydown');
function attempt(fieldOverrides = {}, eventOverrides = {}) {
    let blurs = 0, validations = 0, prevented = false;
    const field = {
        tagName: 'INPUT', type: 'text', disabled: false, readOnly: false,
        closest(selector) { return selector === 'form[target]' ? null : {}; },
        reportValidity() { validations++; return true; },
        blur() { blurs++; }, ...fieldOverrides
    };
    enter({key: 'Enter', target: field, preventDefault() { prevented = true; }, ...eventOverrides});
    return {blurs, validations, prevented};
}
for (const type of ['text', 'number', 'email', 'password', 'search', 'url', 'tel', 'date', 'datetime-local', 'time', 'month', 'week']) {
    assert.deepEqual(attempt({type}), {blurs: 1, validations: 1, prevented: true}, type + ' commits once through blur and suppresses implicit form submit');
}
assert.deepEqual(attempt({reportValidity() { return false; }}), {blurs: 0, validations: 0, prevented: true}, 'invalid input stays focused without submitting');
for (const override of [{tagName:'TEXTAREA'}, {tagName:'DIV'}, {tagName:'SELECT'}, {disabled:true}, {readOnly:true}, ...['file','checkbox','radio','range','color','submit','button'].map(type => ({type})), {closest() { return null; }}]) {
    assert.deepEqual(attempt(override), {blurs: 0, validations: 0, prevented: false}, JSON.stringify(override) + ' retains its own behavior');
}
assert.equal(attempt({closest() { return {}; }}).blurs, 0, 'native target forms retain their own submit behavior');
for (const override of [{key:'Tab'}, {key:'Escape'}, {defaultPrevented:true}, {isComposing:true}, {keyCode:229}, {repeat:true}, {shiftKey:true}, {ctrlKey:true}, {altKey:true}, {metaKey:true}]) {
    assert.deepEqual(attempt({}, override), {blurs:0, validations:0, prevented:false}, JSON.stringify(override) + ' is not intercepted');
}
console.log('PASS: Enter autosave, native validation, multiline/form controls, IME, modifiers and existing key handlers');
