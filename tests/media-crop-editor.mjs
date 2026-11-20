/** The image editor's handling (public/js/gui/cropEditor.js) without a browser: moving, zooming, snapping, turning, what it sends to store. Drawing is not part of this. */
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const here = new URL('./', import.meta.url);
const sys = {};
const context = {sys, Math, Number, Object, Array, Map, String, JSON, URLSearchParams, setTimeout, clearTimeout, console, confirm: () => true,
	window: {devicePixelRatio: 1, addEventListener() {}, removeEventListener() {}}, document: {addEventListener() {}, removeEventListener() {}}};
for (const file of ['../public/js/gui/cropMath.js', '../public/js/gui/cropEditor.js']) {
	vm.runInNewContext(await readFile(new URL(file, here), 'utf8'), context);
}
const math = sys.cropMath;
const ok = label => console.log('ok: ' + label);
const near = (a, b, tolerance = 1e-6) => Math.abs(a - b) <= tolerance;
assert.ok(sys.cropEditor && sys.cropEditor.Session, 'sys.cropEditor is there');

// What the server tells about a 3000 x 2000 picture nobody touched - as Media\CropEdits::state() does.
const names = ['2:1', '16:9', '3:2', '4:3', '1:1', '3:4', '9:16'];
const first = math.ratio('2:1');
const last = math.ratio('9:16');
// The crops of the fixtures are written by their middle (cx, cy), which reads more easily; the server
// sends the alignment (ax, ay) - turned into it here, against what each is cut from.
const aligned = (crop, w, h) => {
	if (crop.cx === undefined) {
		return crop;
	}
	const {cx, cy, ...rest} = crop;
	return Object.assign(rest, {ax: math.alignOf(cx, w, undefined, crop.basis), ay: math.alignOf(cy, h, undefined, crop.basis)});
};
const state = (master = {}, manual = {}) => {
	const m = Object.assign({manual: false, ratio: '3:2', ax: 0.5, ay: 0.5, scale: 1, basis: 'fill', angle: 0, flip: false, look: {b: 100, c: 100, s: 100}, width: 3000, height: 2000,
		sources: [{url: '/m-0.5.webp', width: 866, height: 577}, {url: '/m-2.webp', width: 1732, height: 1155}]}, master);
	const g = math.master(3000, 2000, {ratio: math.ratio(m.ratio), ax: 0.5, ay: 0.5, scale: m.scale, basis: m.basis}, m.angle, m.flip);
	return {
		mediaId: 42, version: 'v1', cut: true,
		original: {width: 3000, height: 2000, url: '/o.webp'},
		master: aligned(m, g.frameWidth / g.referenceWidth, g.frameHeight / g.referenceHeight),
		ratios: names.map(name => {
			const r = math.ratio(name);
			const crop = Object.assign({basis: 'fill'}, manual[name] || {ax: 0.5, ay: 0.5, scale: 1, source: 'centered'});
			const [w, h] = math.size(r, crop.scale, m.width, m.height, crop.basis);
			return Object.assign({name, suffix: name.replace(':', 'by'), w: r.w, h: r.h, position: 1 - math.position(r, last, first)}, aligned(crop, w, h));
		}),
		pois: [], sizes: [0.5, 1, 2, 5, 10]
	};
};
const sent = [];
const session = (data, mode = 'crops', ratio = '1:1') => {
	const s = new sys.cropEditor.Session({nodeId: 169, url: '/file/'});
	s.stageEl = {clientWidth: 1000, clientHeight: 800};
	s.render = function () { this.view = this.layout(); };
	s.renderSide = s.status = s.show = s.hideLevel = () => {};
	s.request = (action, params) => { sent.push({action, params}); return {then() {}}; };
	s.mode = mode;
	s.ratioName = ratio;
	s.take(data);
	return s;
};
// The middle of the frame in what it is cut from - what moving and zooming are about.
const mid = s => s.centreOf(s.work);
// The point of the picture (fractions of what is shown) under a place on the stage.
const under = (s, x, y) => {
	const i = s.view.image;
	return [(x - (i.cx - i.w / 2)) / i.w, (y - (i.cy - i.h / 2)) / i.h];
};

// ----------------------------------------------------------------- layout
let s = session(state());
assert.deepEqual({...s.view.frame}, {x: 180, y: 80, w: 640, h: 640}, 'a square frame with a tenth of the stage free around it');
assert.ok(near(s.view.k, 0.32) && near(s.view.image.w, 960) && near(s.view.image.h, 640) && near(s.view.image.cx, 500) && near(s.view.image.cy, 400));
s = session(state(), 'crops', '16:9');
assert.deepEqual({...s.view.frame}, {x: 100, y: 175, w: 800, h: 450}, 'a wide frame is fitted to the width');
s = session(state(), 'crops', '9:16');
assert.ok(near(s.view.frame.h, 640) && near(s.view.frame.w, 360), 'an upright frame is fitted to the height');
ok('the frame stands in the middle of the stage, fitted by width or height');

// -------------------------------------------------------------------- move
s = session(state());
assert.equal(s.work.basis, 'fill', 'a computed crop is "fill": the edge stays outside');
s.pan(100, 0);
assert.ok(near(mid(s)[0], 0.5 - 100 / 960) && near(mid(s)[1], 0.5), 'the picture moved right: the frame\'s middle lies further left in it');
assert.ok(s.dirty());
s.pan(500, 0);
assert.ok(near(mid(s)[0], 1 / 3, 1e-5), 'the picture stops where its edge meets the frame');
s.pan(0, 50);
assert.ok(near(mid(s)[1], 0.5), 'a square of the full height cannot move up or down');
s = session(state());
s.pan(155, 0);
assert.ok(near(mid(s)[0], 0.5 - 155 / 960), 'five pixels from the edge: it stays where it was put - moving does not snap');
s.pan(-152, 0);
assert.ok(near(mid(s)[0], 0.5 - 3 / 960, 1e-9), 'nor three pixels from the middle');
ok('moving: the picture follows the pointer and stops at the frame');

// -------------------------------------------------------------------- zoom
s = session(state());
s.zoom(2, 500, 400);
assert.ok(near(s.work.scale, 2) && near(mid(s)[0], 0.5) && near(mid(s)[1], 0.5), 'zooming at the middle keeps the middle');
s = session(state());
let before = under(s, 300, 200);
s.zoom(1.7, 300, 200);
let after = under(s, 300, 200);
assert.ok(near(before[0], after[0], 1e-9) && near(before[1], after[1], 1e-9), 'the point of the picture under the pointer stays under it');
assert.ok(near(s.work.scale, 1.7));
s = session(state());
s.zoom(0.5, 500, 400);
assert.ok(near(s.work.scale, 1, 1e-5), 'zooming out stops at "fill in" while the edge stays outside');
ok('zooming: around the pointer, limited by the frame in "fill"');

// ------------------------------------------------------------------ basis
s = session(state());
s.zoom(1.5, 500, 400);
s.pan(60, 0);
s.switchBasis();
assert.ok(s.work.basis === 'fit' && s.work.ax === 0.5 && s.work.ay === 0.5 && s.work.scale === 1, 'the toggle starts afresh: centred, scale 1');
s = session(state());
s.switchBasis();
assert.ok(near(s.lengths(s.work)[0], 1) && near(s.lengths(s.work)[1], 1.5) && near(mid(s)[0], 0.5) && near(mid(s)[1], 0.5), '"fill in" centred becomes "fit in" centred');
s.zoom(0.5, 500, 400);
assert.ok(near(s.work.scale, 0.5), 'in "fit" zooming out goes on');
s.pan(5000, 0);
let rect = math.rect(s.work, 3000, 2000);
assert.ok(near(s.work.ax, 1) && near(rect.x + rect.w, 0, 1e-9), 'the picture goes as far as touching the frame from outside: alignment 1, the picture on the right');
s.pan(-10000, 0);
rect = math.rect(s.work, 3000, 2000);
assert.ok(near(s.work.ax, 0) && near(rect.x, 1, 1e-9), 'and 0 on the other side');
s.pan(0, 300);
assert.ok(s.work.ay > 0.5, 'up and down too, though the frame is longer than the picture there');
s.switchBasis();
assert.ok(s.work.basis === 'fill' && s.work.ax === 0.5 && s.work.scale === 1 && math.inside(s.work, 3000, 2000, 1e-6), 'back to "fill": "fill in", centred');
// The case "fill" cannot move: a frame exactly as long as the picture. In "fit" it moves along both axes.
s = session(state(), 'crops', '3:2');
s.pan(50, 30);
assert.ok(near(mid(s)[0], 0.5) && near(mid(s)[1], 0.5) && !s.dirty(), '"fill in" of the picture\'s own ratio: nothing to move in "fill" - and nothing to apply');
s.switchBasis();
s.pan(50, 30);
assert.ok(mid(s)[0] < 0.5 && mid(s)[1] < 0.5 && !math.inside(s.work, 3000, 2000), 'in "fit" it moves both ways, background showing');
s = session(state(), 'master');
s.switchBasis();
assert.ok(s.work.basis === 'fit' && s.work.auto === false && s.masterOf(s.work).width === 3000, 'master: "fit" is a crop - "fit in" of the whole picture is the whole picture');
s.pan(40, -20);
assert.ok(mid(s)[0] < 0.5 && mid(s)[1] > 0.5, 'and it moves, the master being the original');
s = session(state(), 'master');
s.switchBasis();
s.switchBasis();
assert.ok(s.work.auto === true && !s.dirty(), 'back again without anything else: automatic again, nothing to apply');
ok('basis: the toggle starts at scale 1, centred; "fit" moves where "fill" cannot');

// ----------------------------------------------- Ctrl + wheel: steps of a twentieth
s = session(state());
const notch = up => s.zoomStep({deltaY: up ? -100 : 100, deltaMode: 0}, {x: 500, y: 400});
notch(true);
assert.ok(near(s.work.scale, 1.05), 'one notch: 1 -> 1.05');
notch(true);
assert.ok(near(s.work.scale, 1.1), 'and on: 1.1');
s.zoom(1.37 / 1.1, 500, 400);
notch(true);
assert.ok(near(s.work.scale, 1.4), 'from anywhere to the next step: 1.37 -> 1.4');
s.zoom(1.37 / 1.4, 500, 400);
notch(false);
assert.ok(near(s.work.scale, 1.35), 'and down: 1.37 -> 1.35');
for (let i = 0; i < 9; i++) {
	s.zoomStep({deltaY: -6, deltaMode: 0}, {x: 500, y: 400});
}
assert.ok(near(s.work.scale, 1.4), 'a touchpad pinch adds up to a notch first');
for (let i = 0; i < 10; i++) {
	notch(false);
}
assert.ok(near(s.work.scale, 1, 1e-5), 'in "fill" it stops at "fill in"');
ok('Ctrl + wheel: the scale in steps of a twentieth');

// ------------------------------------------------ crops mode: changes go from ratio to ratio
sent.length = 0;
let asks = 0;
context.confirm = () => { asks++; return true; };
s = session(state(), 'crops', '1:1');
s.zoom(2, 500, 400);
s.pan(96, 0);
const changedSquare = {...s.work};
s.stash();
s.ratioName = '3:4';
s.load();
assert.ok(asks === 0 && s.dirty() && !s.changed() && s.pending.has('1:1'), 'to another ratio without a question: the change waits');
assert.ok(s.ratios.find(r => r.name === '1:1').source === 'manual' && near(s.ratios.find(r => r.name === '1:1').scale, 2), 'and counts as set by hand');
const expected = math.resolve(math.ratio('3:4'), 3000, 2000, [{ratio: math.ratio('1:1'), ax: changedSquare.ax, ay: changedSquare.ay, scale: 2, basis: 'fill'}], [], names.map(n => math.ratio(n)));
assert.ok(near(s.work.ax, expected.ax) && near(s.work.scale, expected.scale) && s.ratios.find(r => r.name === '3:4').source === expected.source, 'the crops beside it follow at once, as the server would compute them');
s.zoom(1.5, 500, 400);
s.save();
assert.equal(sent.length, 1, 'the tick: one request');
assert.equal(sent.at(-1).action, 'saveRatioCrops');
let crops = JSON.parse(sent.at(-1).params.crops);
assert.ok(crops.length === 2 && crops[0].ratio === '1:1' && near(crops[0].scale, 2) && crops[0].basis === 'fill' && crops[1].ratio === '3:4' && near(crops[1].scale, expected.scale * 1.5),
	'with every crop changed');
s = session(state({}, {'1:1': {cx: 0.3, cy: 0.5, scale: 2, source: 'manual'}}), 'crops', '1:1');
s.auto();
assert.ok(s.pending.get('1:1').auto === true && s.ratios.find(r => r.name === '1:1').source !== 'manual' && s.dirty(), 'back to automatic: a change like any other');
s.save();
crops = JSON.parse(sent.at(-1).params.crops);
assert.deepEqual(crops, [{ratio: '1:1', auto: true}], 'stored as such');
s = session(state(), 'crops', '1:1');
s.zoom(2, 500, 400);
s.stash();
s.ratioName = '16:9';
s.load();
s.zoom(1.2, 500, 400);
s.cancel();
assert.ok(!s.dirty() && s.pending.size === 0 && near(s.work.scale, 1) && s.ratios.find(r => r.name === '1:1').source !== 'manual', 'the cross: everything goes');
s.zoom(2, 500, 400);
assert.ok(s.leave() && asks === 1 && s.pending.size === 0, 'leaving the mode asks - once');
s.load();
assert.ok(!s.dirty(), 'and what was not stored is gone');
context.confirm = () => false;
s.zoom(1.5, 500, 400);
assert.ok(!s.leave() && s.dirty(), 'no: it stays');
context.confirm = () => true;
ok('crops mode: changes wait from ratio to ratio, the tick stores them all, the cross discards them all');

// ------------------------------------------------------- a crop set by hand
s = session(state({}, {'1:1': {cx: 0.1, cy: 0.5, scale: 0.8, basis: 'fit', source: 'manual'}}));
assert.equal(s.work.basis, 'fit', 'a stored crop opens in its basis');
assert.ok(near(mid(s)[0], 0.1) && near(s.work.scale, 0.8) && !s.dirty());
ok('a stored crop is shown as it is');

// ------------------------------------------------------------------ master
s = session(state(), 'master');
assert.equal(s.work.ratio.name, '3:2');
assert.equal(s.work.auto, true);
assert.deepEqual({...s.view.frame}, {x: 100, y: 400 - 800 / 1.5 / 2, w: 800, h: 800 / 1.5});
assert.ok(near(s.view.image.w, 800) && near(s.view.image.cx, 500), 'the whole original fills the frame');
s.setRatio(math.ratio('1:1'), false);
assert.ok(s.work.ratio.name === '1:1' && near(s.work.scale, 1) && s.work.auto === false, 'a click on a ratio sets the master to it');
assert.ok(near(s.view.image.h, 640) && near(s.view.image.w, 960));
s.pan(-160, 0);
assert.ok(near(mid(s)[0], 0.5 + 160 / 960), 'the centre of the master is a fraction of the picture');
s.pan(-600, 0);
assert.ok(near(mid(s)[0], 2 / 3, 3e-4), 'the master stops at the picture\'s edge too (within half a pixel of the original)');
ok('master: the frame is the master crop over the original');

s = session(state(), 'master');
s.turn(90, false);
assert.ok(s.work.angle === 90 && s.work.ratio.name === '2:3' && s.work.auto === true && near(s.work.scale, 1), 'turned without a crop: the whole picture, upright');
assert.ok(near(s.view.frame.h, 640) && near(s.view.frame.w, 640 * 2 / 3) && s.view.image.angle === 90);
s.turn(100, false);
assert.ok(s.work.ratio.name !== '2:3' && s.masterOf(s.work).insideOriginal(1.5), 'tilted without a crop: the largest frame inside the turned picture');
// Without a crop of its own the frame follows the angle: the largest area, no jump all the way round.
s = session(state(), 'master');
let previousTurn = null;
let steady = true;
for (let tenth = 0; tenth <= 1800; tenth++) {
	s.turn(tenth / 10, false);
	const g = s.masterOf(s.work);
	steady = steady && g.insideOriginal(1.5) && s.work.auto === true
		&& (!previousTurn || (Math.abs(g.width - previousTurn.width) <= 32 && Math.abs(g.height - previousTurn.height) <= 32));
	previousTurn = g;
}
assert.ok(steady, 'turned on in tenths of a degree from 0 to 180 without a crop: inside, and no jump');
s.turn(30.4, false);
const wide = s.masterOf(s.work);
assert.ok(wide.width / wide.height > 1 && wide.width / wide.height < 3 && wide.width * wide.height > 0.35 * 3000 * 2000,
	'at 30.4 degrees a sensible frame, not a strip: ' + s.work.ratio.name);
s.turn(45, false);
assert.equal(s.work.ratio.name, '1:1', 'at 45 degrees a square');
// A frame of a fixed ratio at "fill in" stays "fill in": the largest of its ratio at every angle.
s = session(state({manual: true, ratio: '16:9', cx: 0.5, cy: 0.5, scale: 1, width: 3000, height: 1688}), 'master');
for (const angle of [5, 20, 33, 60, 90]) {
	s.turn(angle, false);
	assert.ok(s.work.ratio.name === '16:9' && near(s.work.scale, 1, 1e-9) && near(mid(s)[0], 0.5, 1e-6) && s.masterOf(s.work).insideOriginal(1),
		'16:9 at "fill in", turned by ' + angle + ': still the largest 16:9 inside');
}
s = session(state({manual: true, ratio: '1:1', cx: 0.25, cy: 0.5, scale: 2, width: 1000, height: 1000}), 'master');
s.turn(90, false);
assert.ok(near(mid(s)[0], 0.5) && near(mid(s)[1], 0.25) && near(s.work.scale, 2) && s.work.ratio.name === '1:1', 'turned with a crop: the same piece of the picture, the same pixels - left became top');
assert.equal(s.masterOf(s.work).width, 1000);
s = session(state({manual: true, ratio: '1:1', cx: 0.25, cy: 0.5, scale: 2, width: 1000, height: 1000}), 'master');
s.turn(0, true);
assert.ok(near(mid(s)[0], 0.75) && near(mid(s)[1], 0.5) && s.work.flip === true, 'mirrored: the same piece, on the other side');
s = session(state({manual: true, ratio: '3:2', cx: 0.5, cy: 0.5, scale: 1, width: 3000, height: 2000}), 'master');
s.turn(10, false);
assert.ok(s.masterOf(s.work).insideOriginal(0.6) && near(s.work.scale, 1, 1e-3) && s.masterOf(s.work).width < 3000,
	'tilted with a crop that no longer fits: "fill in" as the picture stands now - a smaller frame, no background');
ok('master: turning and mirroring keep the piece of the picture in the frame');

s = session(state(), 'master');
for (const position of [0, 0.3, 0.5, 0.8, 1]) {
	assert.ok(near(s.positionOf(s.ratioAt(position)), position, 2e-3), 'slider position ' + position);
}
assert.ok(near(s.positionOf(math.ratio('2:1')), 0) && near(s.positionOf(math.ratio('9:16')), 1) && near(s.positionOf(math.ratio('3:1')), 0), 'the knob stays on the slider');
ok('the slider: a place gives a ratio and the ratio its place');

// ------------------------------------------------------- tools: fix point
s = session(state());
s.setTool('zoom');
s.pivot = {x: 500, y: 400};
s.movePivot(186, 85);
assert.deepEqual({...s.pivot}, {x: 180, y: 80}, 'near a corner of the frame the fix point snaps to it');
s.movePivot(495, 722);
assert.deepEqual({...s.pivot}, {x: 500, y: 720}, 'and to the middle of an edge');
s.movePivot(505, 396);
assert.deepEqual({...s.pivot}, {x: 500, y: 400}, 'and to the middle of the frame');
s.movePivot(300, 300);
assert.deepEqual({...s.pivot}, {x: 300, y: 300}, 'anywhere else it stays where it is put');
ok('the fix point snaps to the frame\'s corners, edge middles and middle');

// Zoom tool: the place that was grabbed stays under the pointer, on the line through the fix point.
s = session(state());
s.setTool('zoom');
s.pivot = {x: 180, y: 80};                                  // the frame's top left corner
const grabbed = under(s, 400, 300);
const fixed = under(s, 180, 80);
let gesture = {tool: 'zoom', start: {x: 400, y: 300}, work: s.work};
s.dragTool(gesture, {x: 620, y: 520});                      // twice as far from the fix point
assert.ok(near(s.work.scale, 2), 'twice the distance from the fix point: twice the size');
let now = under(s, 620, 520);
assert.ok(near(now[0], grabbed[0], 1e-9) && near(now[1], grabbed[1], 1e-9), 'the grabbed place of the picture is under the pointer');
now = under(s, 180, 80);
assert.ok(near(now[0], fixed[0], 1e-9) && near(now[1], fixed[1], 1e-9), 'the place under the fix point has not moved');
s.dragTool(gesture, {x: 510, y: 410});
assert.ok(near(s.work.scale, 1.5), 'a drag is counted from where it began, not step by step');
s.dragTool(gesture, {x: 290, y: 190});
assert.ok(near(s.work.scale, 1, 1e-5), 'towards the fix point it zooms out - to "fill in" while the edge stays outside');
const still = JSON.stringify(s.work);
s.dragTool({tool: 'zoom', start: {x: 182, y: 81}, work: s.work}, {x: 400, y: 300});
assert.equal(JSON.stringify(s.work), still, 'a drag that starts on the fix point has no distance to go by');
ok('zoom tool: the picture grows around the fix point, the grabbed place stays under the pointer');

// Turn tool (master): a drag turns freely around the fix point (the right angles are a button beside the tick).
// "fit": background may show, nothing holds the picture back. 3 over "fit in" (2/3 of "fill in") is 1000 pixels.
s = session(state({manual: true, ratio: '1:1', cx: 0.5, cy: 0.5, scale: 3, basis: 'fit', width: 1000, height: 1000}), 'master');
assert.equal(s.masterOf(s.work).width, 1000);
s.setTool('turn');
s.pivot = {x: 180, y: 80};
const place = (session_, x, y) => session_.masterOf(session_.work).toOriginal((x - session_.view.frame.x) / session_.view.k, (y - session_.view.frame.y) / session_.view.k);
const pinned = place(s, 180, 80);
gesture = {tool: 'turn', start: {x: 680, y: 80}, work: s.work};    // right of the fix point
s.dragTool(gesture, {x: 180, y: 580});                             // now below it: a quarter clockwise
assert.ok(near(s.work.angle, 90), 'dragged a quarter around the fix point: turned by 90 degrees');
let held = place(s, 180, 80);
assert.ok(near(held[0], pinned[0], 1e-6) && near(held[1], pinned[1], 1e-6), 'the place under the fix point stays there');
assert.equal(s.masterOf(s.work).width, 1000, 'and the frame keeps its pixels');
s.dragTool(gesture, {x: 680 - 500 * (1 - Math.cos(Math.PI / 18)), y: 80 + 500 * Math.sin(Math.PI / 18)});
assert.ok(near(s.work.angle, 10, 1e-6), 'any angle');
held = place(s, 180, 80);
assert.ok(near(held[0], pinned[0], 1e-6) && near(held[1], pinned[1], 1e-6), 'around the same place');
s = session(state({manual: true, ratio: '3:2', cx: 0.5, cy: 0.5, scale: 1, width: 3000, height: 2000}), 'master');
s.setTool('turn');
s.pivot = {x: 500, y: 400};
s.dragTool({tool: 'turn', start: {x: 800, y: 400}, work: s.work}, {x: 800, y: 400 + 300 * Math.tan(Math.PI / 18)});
assert.ok(near(s.work.angle, 10, 1e-6) && s.masterOf(s.work).insideOriginal(0.6) && near(s.work.scale, 1, 1e-3),
	'while the edge stays outside, a turned picture is "fill in" as it stands: no background, the frame holds fewer pixels');
ok('turn tool: any angle around the fix point; the frame never shows background unless that is allowed');

// ------------------------------------------------------ points of interest
// A point is a place of the original. Wherever it is shown - over the original in master mode, over the
// master in crops mode, turned, mirrored, cropped - it is found again where it was put.
for (const [label, master, mode] of [
	['over the untouched original', {}, 'master'],
	['over a turned and cropped original', {manual: true, ratio: '1:1', cx: 0.4, cy: 0.5, scale: 1.5, angle: 90, flip: false, width: 1333, height: 1333}, 'master'],
	['over the master', {}, 'crops'],
	['over a turned, mirrored master', {manual: true, ratio: '2:3', cx: 0.5, cy: 0.5, scale: 1, angle: 90, flip: true, width: 2000, height: 3000}, 'crops']
]) {
	s = session(state(master), mode);
	for (const poi of [{x: 0.5, y: 0.5}, {x: 0.3, y: 0.62}]) {
		const at = s.toStage(poi);
		const back = s.fromStage(at.x, at.y);
		assert.ok(near(back.x, poi.x, 1e-9) && near(back.y, poi.y, 1e-9), label + ': ' + JSON.stringify(poi) + ' -> ' + JSON.stringify(at) + ' -> ' + JSON.stringify(back));
	}
}
s = session(state(), 'crops');
let spot = s.toStage({x: 0.5, y: 0.5});
assert.ok(near(spot.x, 500) && near(spot.y, 400), 'the middle of the picture is in the middle of a centred crop');
spot = s.toStage({x: 0, y: 0});
assert.ok(near(spot.x, 500 - 480) && near(spot.y, 400 - 320), 'its corner where the corner of the picture is shown');
s = session(state({manual: true, ratio: '2:3', cx: 0.5, cy: 0.5, scale: 1, angle: 90, flip: false, width: 2000, height: 3000}), 'master');
spot = s.toStage({x: 0, y: 0});
assert.ok(spot.x > 500 && spot.y < 400, 'turned by 90 degrees, the picture\'s top left corner is shown top right');
const outside = s.fromStage(-5000, -5000);
assert.ok(outside.x >= 0 && outside.x <= 1 && outside.y >= 0 && outside.y <= 1, 'a point is never put outside the picture');
ok('points of interest: a place of the picture is found again in every view');

s = session(state(), 'crops');
s.tool = 'poi';
const count = sent.length;
s.addPoi(500, 400);
assert.equal(sent.length, count + 1);
assert.equal(sent.at(-1).action, 'savePois');
assert.deepEqual(JSON.parse(sent.at(-1).params.pois), [{x: 0.5, y: 0.5, source: 'manual'}]);
assert.equal(sent.at(-1).params.drop_crops, undefined, 'no crop was set by hand: nothing to ask, nothing to remove');
assert.equal(s.poi, 0, 'the new point is the chosen one');
let asked = 0;
context.confirm = () => { asked++; return true; };
s = session(state({}, {'1:1': {cx: 0.3, cy: 0.5, scale: 2, source: 'manual'}}), 'crops');
s.addPoi(300, 300);
assert.equal(asked, 1, 'the first point with crops set by hand: the editor asks');
assert.equal(sent.at(-1).params.drop_crops, 1, 'yes: the crops set by hand go');
context.confirm = () => { asked++; return false; };
s = session(state({}, {'1:1': {cx: 0.3, cy: 0.5, scale: 2, source: 'manual'}}), 'crops');
s.addPoi(300, 300);
assert.equal(sent.at(-1).params.drop_crops, undefined, 'no: they stay, the point is stored all the same');
assert.equal(JSON.parse(sent.at(-1).params.pois).length, 1);
const withPoint = Object.assign(state({}, {'1:1': {cx: 0.3, cy: 0.5, scale: 2, source: 'manual'}}), {pois: [{x: 0.2, y: 0.2}]});
s = session(withPoint, 'crops');
asked = 0;
s.addPoi(600, 500);
assert.equal(asked, 0, 'a further point does not ask again');
assert.equal(JSON.parse(sent.at(-1).params.pois).length, 2);
context.confirm = () => true;
ok('points of interest: stored at once; the first one asks about the crops set by hand');

// ------------------------------------------------------------------- store
let one;
s = session(state({}, {'1:1': {cx: 0.3, cy: 0.5, scale: 2, source: 'manual'}}));
const untouched = sent.length;
s.save();
assert.equal(sent.length, untouched, 'a crop set by hand, nothing changed: nothing sent');
assert.ok(!s.pinnable() && !s.dirty(), 'neither pin nor apply');
s = session(state({}, {'1:1': {cx: 0.3, cy: 0.5, scale: 2, source: 'manual'}}), 'master');
s.save();
assert.equal(sent.length, untouched, 'the master unchanged: nothing sent');
s = session(state(), 'crops', '3:4');
assert.ok(s.pinnable() && !s.dirty(), 'a computed crop: the pin keeps it, nothing to apply yet');
const shownPin = Object.assign({}, s.work);
s.pin();
assert.ok(s.dirty() && !s.pinnable() && s.waiting() === 1 && s.ratios.find(r => r.name === '3:4').source === 'manual',
	'pinned: a change like any other, waiting to be applied, shown as set by hand');
s.auto();
assert.ok(!s.dirty() && s.pinnable(), 'back to automatic undoes the pin');
s.pin();
s.save();
one = JSON.parse(sent.at(-1).params.crops);
assert.ok(sent.at(-1).action === 'saveRatioCrops' && one.length === 1 && one[0].ratio === '3:4' && near(one[0].ax, shownPin.ax) && near(one[0].scale, shownPin.scale),
	'stored as it is shown');
ok('a computed crop can be kept as it is (its neighbours may change)');
s = session(state());
s.zoom(2, 500, 400);
s.pan(96, 0);
s.save();
assert.equal(sent.at(-1).action, 'saveRatioCrops');
one = JSON.parse(sent.at(-1).params.crops)[0];
assert.ok(one.ratio === '1:1' && near(math.centreOf(one.ax, 1 / 3), 0.45) && near(one.ay, 0.5) && near(one.scale, 2)
	&& one.basis === 'fill', 'stored: the middle at 0.45 of the master, as an alignment');
s = session(state());
s.switchBasis();
s.save();
one = JSON.parse(sent.at(-1).params.crops)[0];
assert.ok(one.basis === 'fit' && one.scale === 1, 'the basis is stored with it');
ok('a crop is stored as ratio, alignment, scale and basis');

s = session(state(), 'master');
s.work = Object.assign({}, s.work, {look: {b: 120, c: 100, s: 90}});
s.save();
assert.deepEqual({...sent.at(-1).params, ax: 0, ay: 0, scale: 0, ratio: ''}, {ratio: '', ax: 0, ay: 0, scale: 0, basis: 'fill', brightness: 120, contrast: 100, saturation: 90, angle: 0, flip: 0, auto: 1},
	'only the look changed: the master stays automatic');
s = session(state(), 'master');
s.setRatio(s.ratioAt(0.37), true);
s.zoom(1.3, 500, 400);
const drawn = s.masterOf(s.work);
s.save();
const stored = sent.at(-1).params;
assert.equal(sent.at(-1).action, 'saveMasterCrop');
assert.equal(stored.auto, undefined, 'a cropped master is not automatic');
assert.equal(stored.ratio, math.ratio(drawn.width, drawn.height).name, 'a freely set ratio is stored as the fraction of the frame\'s whole pixels');
const again = math.master(3000, 2000, {ratio: math.ratio(stored.ratio), ax: stored.ax, ay: stored.ay, scale: stored.scale}, stored.angle, stored.flip === 1);
assert.ok(again.width === drawn.width && again.height === drawn.height, 'and comes back with exactly those pixels');
s = session(state(), 'master');
s.setRatio(s.ratioAt(0.37), true);
s.switchBasis();
s.zoom(0.8, 500, 400);
const drawnFit = s.masterOf(s.work);
s.save();
const fitStored = sent.at(-1).params;
const fitAgain = math.master(3000, 2000, {ratio: math.ratio(fitStored.ratio), ax: fitStored.ax, ay: fitStored.ay, scale: fitStored.scale, basis: fitStored.basis}, 0, false);
assert.ok(fitStored.basis === 'fit' && fitAgain.width === drawnFit.width && fitAgain.height === drawnFit.height && near(fitAgain.cx, drawnFit.cx, 1e-9), 'in "fit" too');
s = session(state(), 'master');
s.setRatio(math.ratio('16:9'), false);
s.save();
assert.equal(sent.at(-1).params.ratio, '16:9', 'a ratio picked by a click keeps its name');
ok('the master is stored with its look; a free ratio as its pixels, a picked one by name');

// ------------------------------------------------- crops mode: the slider dragged
{
	const square = {cx: 0.3, cy: 0.5, scale: 2, source: 'manual'};
	const threeFour = {cx: 0.3, cy: 0.5, scale: Math.SQRT2, source: 'interpolated'};
	s = session(state({}, {'1:1': square, '3:4': threeFour, '9:16': {cx: 0.3, cy: 0.5, scale: 1, source: 'anchor'}}), 'crops', '1:1');
	const p = name => s.ratios.find(r => r.name === name).position;
	const at = s.cropAt(p('3:4'));
	const own = name => s.ratios.find(r => r.name === name);
	assert.ok(at.crop.ratio.name === '3:4' && near(at.crop.scale, Math.SQRT2) && near(at.crop.ax, own('3:4').ax), 'on a declared ratio: its own crop');
	const half = (p('1:1') + p('3:4')) / 2;
	const midway = s.cropAt(half);
	assert.ok(near(midway.crop.scale, Math.sqrt(2 * Math.SQRT2)) && near(midway.crop.ax, (own('1:1').ax + own('3:4').ax) / 2) && midway.between === '1:1 and 3:4',
		'between two: the alignment in a straight line, the scale in equal factors');
	assert.ok(near(s.positionOf(midway.crop.ratio), half, 1e-3), 'and the frame has the ratio of that place');
	s.slideTo(half);
	assert.ok(s.sliding && near(s.work.scale, midway.crop.scale) && s.view.frame.w / s.view.frame.h < 1 && s.view.frame.w / s.view.frame.h > 0.75, 'the frame follows the knob');
	assert.equal(s.nearest(half + 0.01).name, '3:4', 'let go, the nearest declared ratio');
	s.glide(s.nearest(half + 0.01));
	assert.ok(!s.sliding && s.ratioName === '3:4' && near(s.work.scale, Math.SQRT2), 'it lands there with that ratio\'s crop - no jump');
	assert.ok(!s.dirty(), 'nothing to apply after sliding');
}
ok('crops mode: the knob drags, the frame follows the interpolation, letting go lands on the nearest ratio');

// --------------------------------------------- the master: the turn back to none, a ratio picked again
s = session(state({manual: true, ratio: '4:3', cx: 0.4, cy: 0.5, scale: 1.2, angle: 12, flip: true}), 'master');
s.turn(0, s.work.flip, null);
assert.ok(s.work.angle === 0 && s.dirty(), 'the turn back to 0 degrees: a change like any other');
s = session(state(), 'master');
s.setRatio(math.ratio('3:2'), false);
assert.ok(!s.dirty() && s.work.auto, 'the ratio it has picked again: the master stays automatic, nothing to apply');
s.setRatio(math.ratio('1:1'), false);
s.setRatio(math.ratio('3:2'), false);
assert.ok(!s.dirty() && s.work.auto, 'and back to it: nothing to apply');
s.setRatio(math.ratio('1:1'), false);
assert.ok(s.dirty() && !s.work.auto && s.waiting() === 1, 'another ratio is a change');
ok('the master: back to no turn; a ratio picked again is no change');

// ------------------------------------------------- the master: reset geometry, reset look
s = session(state(), 'master');
assert.ok(s.geometryUnchanged() && s.lookUnchanged(), 'an untouched master: both resets there, but not to be pressed');
s = session(state({manual: true, ratio: '4:3', cx: 0.4, cy: 0.5, scale: 1.2, angle: 12, flip: true, look: {b: 120, c: 90, s: 100}}), 'master');
assert.ok(!s.geometryUnchanged() && !s.lookUnchanged());
s.resetGeometry();
assert.ok(s.work.auto && s.work.angle === 0 && !s.work.flip && s.work.basis === 'fill' && s.work.look.b === 120 && s.dirty() && s.geometryUnchanged(),
	'geometry: the whole picture, not turned, not mirrored - the look stays');
s.save();
assert.ok(sent.at(-1).action === 'saveMasterCrop' && sent.at(-1).params.auto === 1 && sent.at(-1).params.angle === 0 && sent.at(-1).params.flip === 0
	&& sent.at(-1).params.brightness === 120, 'stored as the whole picture, not turned, not mirrored, with its look');
s = session(state({manual: true, ratio: '4:3', cx: 0.4, cy: 0.5, scale: 1.2, angle: 12, flip: true, look: {b: 120, c: 90, s: 100}}), 'master');
s.hideLevel = () => {};
s.resetLook();
assert.ok(s.lookUnchanged() && s.work.angle === 12 && s.work.flip && s.dirty(), 'look: unchanged - the geometry stays');
s = session(state({}, {}), 'master');
s.turn(10, false, null);
s.resetGeometry();
assert.ok(!s.dirty(), 'reset back to what is stored: nothing to apply');
ok('the master: reset geometry and reset look, each for its own group');

// ------------------------------------------------- zoom: the arrow, pan or zoom
s = session(state());
const props = {};
s.stageEl.style = {setProperty: (k, v) => { props[k] = v; }, removeProperty: k => { delete props[k]; }};
s.setTool('zoom');
s.pivot = {x: 500, y: 400};
const stepOf = () => ['e', 'se', 's', 'sw', 'w', 'nw', 'n', 'ne'].find(d => (props['--ceArrow'] || '').endsWith(' ' + d + '-resize'));
s.arrow({x: 700, y: 410});
assert.equal(stepOf(), 'e', 'right of the fix point: the arrow points right');
s.arrow({x: 380, y: 280});
assert.equal(stepOf(), 'nw', 'up left: up left, in steps of 45 degrees');
s.arrow({x: 503, y: 402});
assert.equal(props['--ceArrow'], undefined, 'on the fix point itself: no arrow');
s.arrow({x: 500, y: 700});
s.setTool(null);
assert.equal(props['--ceArrow'], undefined, 'pan again: the arrow goes');
ok('zoom: the pointer is an arrow away from the fix point, in steps of 45 degrees');

// ------------------------------------------------- crops: reset all, the master's basis
s = session(state());
assert.ok(!s.anyCrop(), 'no crop set by hand: reset all is there, but not to be pressed');
s = session(state({}, {'1:1': {ax: 0.3, ay: 0.5, scale: 2, source: 'manual'}, '16:9': {ax: 0.5, ay: 0.2, scale: 1.5, source: 'manual'}}), 'crops', '3:4');
assert.ok(s.anyCrop());
s.resetCrops();
assert.ok(!s.anyCrop() && s.waiting() === 2 && s.ratios.every(r => r.source !== 'manual'), 'reset all: every crop set by hand automatic again, waiting to be applied');
s.save();
assert.ok(sent.at(-1).action === 'saveRatioCrops' && JSON.parse(sent.at(-1).params.crops).every(c => c.auto === true), 'applied as two crops back to automatic');
s = session(state(), 'crops', '3:4');
s.pan(30, 0);
assert.ok(s.anyCrop(), 'a crop changed on screen counts');
s.resetCrops();
assert.ok(!s.dirty(), 'and goes - nothing was stored, nothing to apply');
s = session(state({manual: true, basis: 'fit'}, {'1:1': {ax: 0.3, ay: 0.5, scale: 2, source: 'manual'}}), 'crops', '1:1');
s.auto();
assert.ok(s.ratios.every(r => r.basis === 'fit'), 'the master "fit in": automatic crops "fit in" too');
ok('crops: reset all, and the basis of the master for what is automatic');

// ------------------------------------------------- what goes to the server
s = session(state(), 'crops', '4:3');
s.work = Object.assign({}, s.work, {ax: 0.8065232844184549, ay: 0.5572280723097853, scale: 0.9999982054059304});
s.save();
assert.equal(JSON.parse(sent.at(-1).params.crops)[0].scale, 1, 'zoomed out to the edge, a hair under fill in: sent as fill in');
s = session(state({manual: true, ratio: '3:2'}), 'master');
s.work = Object.assign({}, s.work, {ax: 0.7281177559154488, ay: 1, scale: 0.9997711516819886, auto: false});
s.save();
assert.ok(sent.at(-1).action === 'saveMasterCrop' && sent.at(-1).params.scale === 1 && sent.at(-1).params.ay === 1, 'the master too: half a pixel under fill in is sent as fill in');
ok('crops and the master go to the server within what their basis allows');

// ------------------------------------------------- fixed formats: each its own crop
{
	const data = state({}, {'1:1': {ax: 0.3, ay: 0.5, scale: 2, source: 'manual'}});
	data.formats = [{name: 'shareImage', label: 'Share image', w: 40, h: 21, width: 1200, height: 630, type: 'jpg', position: 0.03,
		ax: 0.5, ay: 0.5, scale: 1, basis: 'fill', source: 'interpolated', auto: {ax: 0.5, ay: 0.5, scale: 1, basis: 'fill', source: 'interpolated'}}];
	const f = new sys.cropEditor.Session({nodeId: 169, url: '/file/image/', mode: 'formats', ratio: 'shareImage'});
	f.stageEl = {clientWidth: 1000, clientHeight: 800};
	f.render = function () { this.view = this.layout(); };
	f.renderSide = f.status = f.show = f.hideLevel = () => {};
	f.request = (action, params) => { sent.push({action, params}); return {then() {}}; };
	f.take(data);
	assert.ok(f.fixed && f.mode === 'crops' && f.ratioName === 'shareImage' && f.work.ratio.name === '40:21', 'the formats tab: the format asked for, at its ratio');
	f.pan(0, -40);
	f.stash();
	assert.ok(f.ratios[0].source === 'manual' && f.ratios[0].ay !== 0.5, 'its crop changed: set by hand, nothing else computed again');
	assert.ok(near(f.positionOf(math.ratio('2:1')), 0) && near(f.positionOf(math.ratio('9:16')), 1, 0.2), 'the slider spans the declared ratios, not the formats');
	f.save();
	const savedFormats = JSON.parse(sent.at(-1).params.crops);
	assert.ok(sent.at(-1).action === 'saveFormatCrops' && savedFormats[0].format === 'shareImage' && savedFormats[0].ratio === undefined, 'applied as the crop of a fixed format');
	f.take(Object.assign({}, data, {formats: [Object.assign({}, data.formats[0], {ax: 0.2, source: 'manual'})]}));
	f.auto();
	assert.ok(f.ratios[0].source === 'interpolated' && f.ratios[0].ax === 0.5 && f.waiting() === 1, 'back to computed: the crop computed from the ratios, waiting to be applied');
	assert.equal(f.cropAt(0.5).crop.ratio.name, '40:21', 'nothing between fixed formats: the nearest one');
	ok('fixed formats: each its own crop, applied as such, back to the computed one');
}

console.log('media crop editor: all checks passed');
