/** The pictures' loader (public/js/core/media.js): choosing a size, the queue, the preview's swap. No browser - the DOM it needs is drawn here. */
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = await readFile(new URL('../public/js/core/media.js', import.meta.url), 'utf8');
const ok = label => console.log('ok: ' + label);

// --------------------------------------------------------------- a tiny DOM
const requests = [];           // every fetch, in order: {url, resolve}
const timers = [];
const fetch = (url, options) => new Promise(resolve => requests.push({url, options, resolve}));
const answer = (index, status = 200, retry = null) => requests[index].resolve({status, ok: status >= 200 && status < 300, headers: {get: name => name === 'Retry-After' ? retry : null}});
const settle = () => new Promise(resolve => setImmediate(resolve));
let observer = null;
class IntersectionObserver {
	constructor(callback) { this.callback = callback; observer = this; this.watched = new Set(); }
	observe(element) { this.watched.add(element); }
	unobserve(element) { this.watched.delete(element); }
	show(...elements) { this.callback(elements.map(target => ({target, isIntersecting: true}))); }
}
const image = (dataset = {}, clientWidth = 0, clientHeight = 0) => {
	const listeners = {};
	return {dataset, clientWidth, clientHeight, style: {background: 'url(preview)'}, srcset: '', src: 'placeholder', sizes: 'auto, 100vw', complete: false,
		addEventListener(name, fn) { listeners[name] = fn; }, fire(name) { listeners[name] && listeners[name](); }, parentNode: null};
};
const sys = {};
const context = {sys, fetch, IntersectionObserver, window: {devicePixelRatio: 2}, setTimeout: (fn, ms) => { timers.push({fn, ms}); return timers.length; }, setImmediate, console, WeakSet, JSON, Math, Number, String, Array, Object, Infinity,
	// start() is called by hand below, once the observers exist
	document: {readyState: 'loading', addEventListener() {}, documentElement: {}, querySelectorAll: () => []}};
vm.runInNewContext(source, context);
const media = sys.media;
assert.ok(media, 'sys.media is there');
ok('the loader attaches to sys.media');

// ------------------------------------------------------------------- pure
assert.deepEqual(JSON.parse(JSON.stringify(media.parseSrcset('/a--0.5mp.webp?v=1 943w, /a--1mp.webp?v=1 1333w,  /a.webp'))), [{url: '/a--0.5mp.webp?v=1', width: 943}, {url: '/a--1mp.webp?v=1', width: 1333}, {url: '/a.webp', width: 0}]);
assert.equal(media.parseSrcset('').length, 0);
ok('a srcset is read into addresses and widths');

const candidates = [{url: 's', width: 943}, {url: 'm', width: 1333}, {url: 'l', width: 1886}];
assert.equal(media.pick(candidates, 500).url, 's');
assert.equal(media.pick(candidates, 943).url, 's');
assert.equal(media.pick(candidates, 944).url, 'm');
assert.equal(media.pick(candidates, 5000).url, 'l', 'more than there is: the largest');
assert.equal(media.pick([], 100), null);
ok('the smallest size that has the pixels is picked, else the largest');

const ratios = {'2by1': [2, 1, 3000], '16by9': [16, 9, 3000], '1by1': [1, 1, 2000], '9by16': [9, 16, 1125]};
assert.equal(media.nearestRatio(ratios, 1600, 900), '16by9');
assert.equal(media.nearestRatio(ratios, 1000, 1000), '1by1');
assert.equal(media.nearestRatio(ratios, 300, 600), '9by16');
assert.equal(media.nearestRatio(ratios, 1000, 700), '16by9', 'between two: the nearer one on the logarithmic scale (1.43 is closer to 1.78 than to 1)');
assert.equal(media.nearestRatio(ratios, 1000, 780), '1by1');
ok('a box takes the declared ratio nearest to it');

const data = {base: '/var/formats/000/42/bild--', type: 'webp', v: 'abc', sizes: [0.5, 1, 2, 5, 10], ratios};
let pick = media.autoVariant(data, 400, 225, 1);
assert.deepEqual({...pick}, {key: '16by9', variant: '0.5mp16by9', width: 943, url: '/var/formats/000/42/bild--0.5mp16by9.webp?v=abc'});
pick = media.autoVariant(data, 400, 225, 3);
assert.equal(pick.variant, '1mp16by9', '400 css pixels on a 3x screen need 1200 real ones');
pick = media.autoVariant(data, 1200, 675, 2);
assert.equal(pick.variant, '5mp16by9', '2400 real pixels: 2 megapixels (1886 wide) are not enough');
assert.equal(pick.width, 2981);
pick = media.autoVariant(data, 2000, 1125, 2);
assert.equal(pick.variant, '10mp16by9', 'more than the crop has: the first size that holds all its pixels');
assert.equal(pick.width, 3000, 'and no more than its own');
pick = media.autoVariant(data, 300, 300, 1);
assert.equal(pick.variant, '0.5mp1by1');
pick = media.autoVariant(data, 200, 800, 1);
assert.equal(pick.key, '9by16');
assert.equal(pick.variant, '0.5mp9by16', 'a narrow box is covered by its height: 800 high needs 450 wide');
pick = media.autoVariant(data, 200, 1900, 1);
assert.equal(pick.variant, '5mp9by16', '1900 high needs 1069 wide - 2 megapixels are 1061 wide, 5 hold all the crop has');
assert.equal(pick.width, 1125);
assert.equal(media.autoVariant(data, 0, 0, 1), null, 'no box yet: nothing');
pick = media.autoVariant({...data, sizes: [0.5]}, 3000, 1688, 2);
assert.equal(pick.variant, '16by9', 'no declared size is enough and the crop has more: its own pixels');
ok('a box gets the ratio, the size and the address it needs');

assert.equal(media.queueUrl('/a.webp'), '/a.webp?queue=1');
assert.equal(media.queueUrl('/a.webp?v=1'), '/a.webp?v=1&queue=1');
ok('the loader marks its requests so the server may answer "busy"');

// ------------------------------------------------------------------ queue
media.start();
assert.ok(observer, 'visibility is watched');
const done = [];
const pictures = [1, 2, 3, 4, 5, 6].map(n => ({name: 'p' + n}));
pictures.forEach(element => media.enqueue({element, url: '/' + element.name + '.webp', done: okay => done.push(element.name + ':' + okay)}));
assert.deepEqual(requests.map(r => r.url), ['/p1.webp?queue=1', '/p2.webp?queue=1', '/p3.webp?queue=1'], 'three at a time, in the order of the page');
assert.equal(requests[0].options.method, 'HEAD');
ok('only a few pictures are asked for at the same time');

observer.show(pictures[5]);                 // the visitor scrolled to the last one
answer(0);
await settle();
assert.deepEqual(done, ['p1:true']);
assert.equal(requests[3].url, '/p6.webp?queue=1', 'the visible one jumps the queue');
ok('a picture the visitor looks at is asked for first');

answer(1, 503, '3');                        // the server is busy
await settle();
assert.equal(timers.length, 1);
assert.equal(timers[0].ms, 3000, 'it waits as long as the server says');
assert.equal(requests[4].url, '/p4.webp?queue=1', 'meanwhile the next one is asked for');
assert.deepEqual(done, ['p1:true'], 'a busy server is not a failure');
timers[0].fn();
answer(2); answer(3); answer(4);
await settle();
assert.ok(requests.some((r, i) => i > 4 && r.url === '/p2.webp?queue=1'), 'and then asks again');
ok('a busy server makes a picture wait and ask again');

answer(requests.findIndex((r, i) => i > 4 && r.url === '/p5.webp?queue=1'), 404);
await settle();
assert.ok(done.includes('p5:false'), 'a picture that cannot be made is given up, not asked for forever');
requests.filter(r => !r.answered).forEach((r, i) => { r.answered = true; });
for (let i = 0; i < requests.length; i++) answer(i);
await settle();
assert.equal(done.length, 6);
ok('every picture is finished in the end, made or not');

// ---------------------------------------------------------------- pending
requests.length = 0;
const img = image({mediaSrcset: '/b--0.5mp1by1.webp?v=1 707w, /b--1mp1by1.webp?v=1 1000w, /b--2mp1by1.webp?v=1 1414w', mediaSrc: '/b--1mp1by1.webp?v=1'}, 400);
media.pending(img);
assert.equal(requests.length, 1);
assert.equal(requests[0].url, '/b--1mp1by1.webp?v=1&queue=1', '400 css pixels at 2x need 800: the 1 megapixel file is made first');
assert.equal(img.src, 'placeholder', 'until it is there the preview stays');
media.pending(img);
assert.equal(requests.length, 1, 'a picture is queued once');
answer(0);
await settle();
assert.equal(img.srcset, '/b--0.5mp1by1.webp?v=1 707w, /b--1mp1by1.webp?v=1 1000w, /b--2mp1by1.webp?v=1 1414w');
assert.equal(img.src, '/b--1mp1by1.webp?v=1');
assert.equal(img.dataset.mediaSrcset, undefined);
assert.equal(img.style.background, 'url(preview)', 'the preview stays under the picture until it has loaded');
img.fire('load');
assert.equal(img.style.background, '');
assert.equal(img.sizes, '400px', 'and the picture is told how wide it is shown');
ok('a picture showing its preview gets its real addresses once its file is made');

// -------------------------------------------------------------------- fit
const wide = image({}, 640);
wide.srcset = 'x 943w';
media.fit(wide);
assert.equal(wide.sizes, '640px');
wide.clientWidth = 660;
media.fit(wide);
assert.equal(wide.sizes, '640px', 'a little wider: not asked again');
wide.clientWidth = 900;
media.fit(wide);
assert.equal(wide.sizes, '900px', 'clearly wider: more pixels');
wide.clientWidth = 300;
media.fit(wide);
assert.equal(wide.sizes, '900px', 'narrower: what is there is enough');
const loaded = image({}, 500);
Object.assign(loaded, {srcset: 'x 1333w', complete: true, naturalWidth: 1333});
media.fit(loaded);
assert.equal(loaded.sizes, 'auto, 100vw', 'a picture that already has the pixels is left alone');
ok('a picture with srcset is told its measured width, and only ever asks for more');

// ------------------------------------------------------------------- auto
requests.length = 0;
const box = image({mediaAuto: JSON.stringify(data)}, 400, 225);
media.auto(box);
assert.equal(requests.at(-1).url, '/var/formats/000/42/bild--0.5mp16by9.webp?v=abc&queue=1');
answer(requests.length - 1);
await settle();
assert.equal(box.src, '/var/formats/000/42/bild--0.5mp16by9.webp?v=abc');
const before = requests.length;
box.clientWidth = 380;
media.auto(box);
assert.equal(requests.length, before, 'a smaller box of the same shape keeps its picture');
box.clientWidth = 400; box.clientHeight = 400;
media.auto(box);
assert.equal(requests.at(-1).url, '/var/formats/000/42/bild--1mp1by1.webp?v=abc&queue=1', 'another shape: another ratio (800 real pixels: half a megapixel is 707 wide)');
box.clientWidth = 1200; box.clientHeight = 1200;
media.auto(box);
const last = requests.length - 1;
assert.equal(requests[last].url, '/var/formats/000/42/bild--5mp1by1.webp?v=abc&queue=1', 'a bigger box: more pixels');
answer(last - 1); answer(last);
await settle();
assert.equal(box.src, '/var/formats/000/42/bild--5mp1by1.webp?v=abc', 'the last choice wins, whichever file arrives when');
ok('a picture without a ratio follows its box');

console.log('media loader: all checks passed');
