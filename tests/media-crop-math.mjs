/** The editor's arithmetic (public/js/gui/cropMath.js) against the server's: the shared resolver cases, and masters and crops worked out by both. */
import {readFile} from 'node:fs/promises';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const here = new URL('./', import.meta.url);
const source = await readFile(new URL('../public/js/gui/cropMath.js', here), 'utf8');
const sys = {};
vm.runInNewContext(source, {sys, Math, Number, Object, Array, Map, String});
const math = sys.cropMath;
const ok = label => console.log('ok: ' + label);
const near = (a, b, tolerance = 1e-6) => Math.abs(a - b) <= tolerance;
assert.ok(math, 'sys.cropMath is there');

// ------------------------------------------------------------------ ratio
assert.equal(math.ratio('32:18').name, '16:9');
assert.equal(math.ratio(3012, 5456).name, '753:1364');
assert.equal(math.ratio('nonsense'), null);
assert.equal(math.ratio(0, 5), null);
assert.equal(math.compare(math.ratio('16:9'), math.ratio('3:2')), 1);
assert.ok(near(math.position(math.ratio('1:1'), math.ratio('9:16'), math.ratio('16:9')), 0.5));
ok('ratios are reduced, compared exactly, placed logarithmically');

// --------------------------------------------------------------- resolver
const fixture = JSON.parse(await readFile(new URL('media-crop-resolver.cases.json', here), 'utf8'));
assert.ok(fixture.cases.length >= 14);
for (const c of fixture.cases) {
	const manual = c.manual.map(m => ({ratio: math.ratio(m.ratio), ax: m.ax, ay: m.ay, scale: m.scale, basis: m.basis}));
	const crop = math.resolve(math.ratio(c.ratio), c.master[0], c.master[1], manual, c.pois, (c.declared || []).map(name => math.ratio(name)), c.basis);
	assert.ok(near(crop.ax, c.expect.ax) && near(crop.ay, c.expect.ay) && near(crop.scale, c.expect.scale), c.name + ': ' + JSON.stringify(crop));
	assert.equal(crop.source, c.expect.source, c.name);
	assert.equal(crop.ratio.name, c.ratio, c.name);
	assert.equal(crop.basis, c.expect.basis || 'fill', c.name);
	ok('resolver: ' + c.name);
}

// -------------------------------------------------- the server's own answers
const ratios = ['16:9', '1:1', '9:16', '3:2', '191:100', '753:1364'];
const angles = [0, 90, 180, -90, 270, 10, -25, 33.5, 45, 60, 84, 170, 200];
const cases = [];
let seed = 7;
const random = () => (seed = (seed * 16807) % 2147483647) / 2147483647;   // the same list every run
for (let i = 0; i < 120; i++) {
	cases.push({
		width: 200 + Math.floor(random() * 5800), height: 200 + Math.floor(random() * 5800),
		ratio: ratios[i % ratios.length], angle: angles[i % angles.length], flip: i % 3 === 0,
		ax: -0.3 + random() * 1.6, ay: -0.3 + random() * 1.6, scale: 0.6 + random() * 2.4,
		px: random() * 3000, py: random() * 3000, basis: i % 2 ? 'fit' : 'fill'
	});
}
const php = spawnSync(process.env.FLUXO_PHP || 'php', [fileURLToPath(new URL('media-crop-math.parity.php', here))], {input: JSON.stringify(cases), encoding: 'utf8', maxBuffer: 1 << 26});
assert.equal(php.status, 0, 'the server side answered: ' + (php.stderr || php.stdout).slice(0, 400));
const server = JSON.parse(php.stdout);
assert.equal(server.length, cases.length);
cases.forEach((c, i) => {
	const s = server[i];
	const crop = {ratio: math.ratio(c.ratio), ax: c.ax, ay: c.ay, scale: c.scale, basis: c.basis};
	const g = math.master(c.width, c.height, crop, c.angle, c.flip);
	const where = `case ${i} ${JSON.stringify(c)}`;
	assert.equal(g.width, s.width, where);
	assert.equal(g.height, s.height, where);
	assert.ok(near(g.referenceWidth, s.reference[0]) && near(g.referenceHeight, s.reference[1]), where);
	assert.ok(near(g.toMaster(c.px, c.py)[0], s.toMaster[0], 1e-5) && near(g.toMaster(c.px, c.py)[1], s.toMaster[1], 1e-5), where);
	assert.ok(near(g.toOriginal(c.px, c.py)[0], s.toOriginal[0], 1e-5) && near(g.toOriginal(c.px, c.py)[1], s.toOriginal[1], 1e-5), where);
	assert.equal(g.insideOriginal(), s.inside, where);
	assert.ok(near(g.fitScale(), s.fitScale), where);
	const largest = math.largest(c.width, c.height, c.angle, c.flip);
	assert.deepEqual([largest.width, largest.height, largest.crop.ratio.name], s.largest, where);
	const rect = math.rect(crop, c.width, c.height);
	assert.ok(near(rect.x, s.rect.x) && near(rect.y, s.rect.y) && near(rect.w, s.rect.w) && near(rect.h, s.rect.h), where);
	assert.equal(math.inside(crop, c.width, c.height), s.cropInside, where);
	const clamped = math.clamped(crop, c.width, c.height);
	assert.ok(near(clamped.ax, s.clamped[0]) && near(clamped.ay, s.clamped[1]) && near(clamped.scale, s.clamped[2]), where);
	assert.ok(near(math.fitScale(crop.ratio, c.width, c.height), s.cropFit), where);
	assert.ok(near(g.cx, s.centre[0]) && near(g.cy, s.centre[1]), 'the middle the alignment gives: ' + where);
	const fit = math.asFit(crop, c.width, c.height);
	assert.ok(near(fit.ax, s.asFit[0]) && near(fit.ay, s.asFit[1]) && near(fit.scale, s.asFit[2]) && fit.basis === 'fit', 'the same frame in "fit": ' + where);
	const centre = math.centre(crop, c.width, c.height);
	assert.ok(near(math.alignOf(centre[0], rect.w, undefined, c.basis), Math.min(1, Math.max(0, c.ax))), 'alignment and middle, there and back: ' + where);
});
ok(`${cases.length} masters and crops, "fill" and "fit": size, mapping both ways, inside, fit in, the largest frame, the middle from the alignment - the same as the server's`);

// ---------------------------------------------------------------- furthest
const stop = math.furthest(t => 10 * t, value => value <= 4);
assert.ok(near(stop, 4, 1e-5), 'stops where the rule stops holding');
assert.equal(math.furthest(t => 10 * t, value => value <= 20), 10, 'the whole way when nothing is in the way');
assert.equal(math.furthest(t => 10 * t, value => value <= 0), 0, 'not at all when the first step is too far');
// A master pushed left stops when its frame meets the picture's edge - at any angle.
for (const angle of [0, 10, 90]) {
	const at = ax => math.master(3000, 2000, {ratio: math.ratio('1:1'), ax: ax, ay: 0.5, scale: 1.5}, angle, false);
	const g = math.furthest(t => at(0.5 - 1.5 * t), geometry => geometry.insideOriginal(0.01));
	assert.ok(g.insideOriginal(0.02) && !at(g.crop.ax - 0.004).insideOriginal(0.01), 'angle ' + angle);
}
ok('a move stops where the edge of the picture meets the frame');

console.log('media crop math: all checks passed');
