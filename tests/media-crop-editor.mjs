/** The image editor's handling (public/js/gui/cropEditor.js) without a browser: moving, zooming, snapping, turning, what it sends to store. Drawing is not part of this. */
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const here = new URL('./', import.meta.url);
const sys = {};
const context = {sys, Math, Number, Object, Array, Map, String, JSON, URLSearchParams, setTimeout, clearTimeout, console, confirm: () => true,
	window: {devicePixelRatio: 1}, document: {}};
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
	return Object.assign(rest, {ax: math.alignOf(cx, w), ay: math.alignOf(cy, h)});
};
const state = (master = {}, manual = {}) => {
	const m = Object.assign({manual: false, ratio: '3:2', ax: 0.5, ay: 0.5, scale: 1, angle: 0, flip: false, look: {b: 100, c: 100, s: 100}, width: 3000, height: 2000,
		sources: [{url: '/m-0.5.webp', width: 866, height: 577}, {url: '/m-2.webp', width: 1732, height: 1155}]}, master);
	const g = math.master(3000, 2000, {ratio: math.ratio(m.ratio), ax: 0.5, ay: 0.5, scale: m.scale}, m.angle, m.flip);
	return {
		mediaId: 42, version: 'v1', cut: true,
		original: {width: 3000, height: 2000, url: '/o.webp'},
		master: aligned(m, g.frameWidth / g.referenceWidth, g.frameHeight / g.referenceHeight),
		ratios: names.map(name => {
			const r = math.ratio(name);
			const crop = manual[name] || {ax: 0.5, ay: 0.5, scale: 1, source: 'centered'};
			const [w, h] = math.size(r, crop.scale, m.width, m.height);
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
assert.equal(s.free, false, 'a crop inside the picture starts with "the edge stays outside"');
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
s.free = true;
s.zoom(0.5, 500, 400);
assert.ok(near(s.work.scale, 0.5), 'with "background may show" it goes on');
s.pan(700, 0);
assert.ok(mid(s)[0] < 0, 'and the picture may be pushed out of the frame');
s.free = false;
s.work = s.settled(s.work);
assert.ok(math.inside(s.work, 3000, 2000, 1e-6), 'back to "the edge stays outside": the nearest crop that shows no background');
ok('zooming: around the pointer, limited by the frame unless background may show');

// ------------------------------------------------------- a crop set by hand
s = session(state({}, {'1:1': {cx: 0.1, cy: 0.5, scale: 0.8, source: 'manual'}}));
assert.equal(s.free, true, 'a stored crop that shows background opens with "background may show"');
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
s = session(state({manual: true, ratio: '1:1', cx: 0.5, cy: 0.5, scale: 2, width: 1000, height: 1000}), 'master');
s.free = true;                                              // background may show: nothing holds the picture back
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
s = session(state());
const untouched = sent.length;
s.save();
assert.equal(sent.length, untouched, 'nothing changed, nothing sent');
s.zoom(2, 500, 400);
s.pan(96, 0);
s.save();
assert.equal(sent.at(-1).action, 'saveRatioCrop');
assert.ok(sent.at(-1).params.ratio === '1:1' && near(math.centreOf(sent.at(-1).params.ax, 1 / 3), 0.45) && near(sent.at(-1).params.ay, 0.5) && near(sent.at(-1).params.scale, 2),
	'stored: the middle at 0.45 of the master, as an alignment');
ok('a crop is stored as ratio, alignment and scale');

s = session(state(), 'master');
s.work = Object.assign({}, s.work, {look: {b: 120, c: 100, s: 90}});
s.save();
assert.deepEqual({...sent.at(-1).params, ax: 0, ay: 0, scale: 0, ratio: ''}, {ratio: '', ax: 0, ay: 0, scale: 0, brightness: 120, contrast: 100, saturation: 90, angle: 0, flip: 0, auto: 1},
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

// -------------------------------------------------------- back to the original
s = session(state({manual: true, ratio: '4:3', cx: 0.4, cy: 0.5, scale: 1.2, angle: 12, flip: true, look: {b: 120, c: 90, s: 100}}), 'master');
s.el = {querySelector: () => ({hidden: true}), querySelectorAll: () => []};
s.hideLevel = () => {};
s.work = Object.assign({}, s.work, {ratio: math.ratio(3000, 2000), ax: 0.5, ay: 0.5, scale: 1, angle: 0, flip: false, look: {b: 100, c: 100, s: 100}, auto: true, freeRatio: false});
assert.ok(s.dirty() && s.masterOf(s.work).insideOriginal(), 'the original as the master: a change like any other, applied with the tick');
s.save();
assert.ok(sent.at(-1).action === 'saveMasterCrop' && sent.at(-1).params.auto === 1 && sent.at(-1).params.angle === 0 && sent.at(-1).params.flip === 0
	&& sent.at(-1).params.brightness === 100, 'stored as the whole picture, not turned, not mirrored, no look');
ok('the master goes back to the original');

console.log('media crop editor: all checks passed');
