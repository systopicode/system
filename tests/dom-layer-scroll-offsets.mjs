/** A shorter/new view must not abort navigation while restoring an old scroll path. */
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = await readFile(new URL('../public/js/core/domLayer.js', import.meta.url), 'utf8');
let construct;
vm.runInNewContext(source, {sys: {lib: {add(_setup, callback) { construct = callback; }}}});
const layer = {};
construct.call(layer);
const element = (children = [], top = 0, left = 0) => ({nodeType: 1, children, scrollTop: top, scrollLeft: left});

// Scrolling the home list before opening a project captures a nested offset.
layer.rootNodes = [element([element([], 640, 12)])];
const offsets = layer.scrollOffsets();
assert.equal(offsets.length, 1);
const sameScroller = element();
layer.rootNodes = [element([sameScroller])];
layer.applyScrollOffsets(offsets);
assert.equal(sameScroller.scrollTop, 640, 'an unchanged view retains vertical scroll');
assert.equal(sameScroller.scrollLeft, 12, 'an unchanged view retains horizontal scroll');

for (const root of [{nodeType: 3}, {nodeType: 8}, undefined, element()]) {
    layer.rootNodes = [root];
    assert.doesNotThrow(() => layer.applyScrollOffsets(offsets), 'a missing/non-element path in a new view is skipped');
}

// A stale path must not prevent later valid offsets or the history update.
const remaining = element();
layer.rootNodes = [{nodeType: 3}, element([remaining])];
let historyUpdated = false;
assert.doesNotThrow(() => {
    layer.applyScrollOffsets([...offsets, {path: [1, 0], top: 120, left: 0}]);
    historyUpdated = true;
});
assert.equal(remaining.scrollTop, 120);
assert.equal(historyUpdated, true, 'navigation can update browser history after rebuilding');
console.log('ok: scroll restoration survives changed views and preserves valid offsets');
