/**
 * Calculator math tests — run with:  node --test tests/*.test.js
 *
 * Every assertion compares the engine's output with an independent,
 * straightforward re-computation from the raw JSON dataset, for EVERY brand.
 */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');
const calc = require('../assets/js/calculator.js');

const DATA_DIR = path.join(__dirname, '..', 'assets', 'data');
const N = ['calories', 'protein', 'carbs', 'fat', 'sodium'];
const brands = fs.readdirSync(DATA_DIR).filter(f => f.endsWith('.json')).map(f => ({
	slug: f.replace('.json', ''),
	data: JSON.parse(fs.readFileSync(path.join(DATA_DIR, f), 'utf8')),
}));

function items(data) {
	return data.categories.flatMap(c => c.items);
}
// Independent reference implementation (deliberately naive).
function expected(lines) {
	const t = { calories: 0, protein: 0, carbs: 0, fat: 0, sodium: 0 };
	for (const l of lines) {
		const q = Math.max(0, Math.min(20, l.qty));
		for (const k of N) {
			let v = l.item[k];
			for (const a of l.addons) { v += (typeof a[k] === 'number' ? a[k] : 0); }
			t[k] += v * q;
		}
	}
	for (const k of N) { t[k] = Math.round(t[k] * 10) / 10; }
	return t;
}

test('there are 15 datasets and every item has numeric nutrients', () => {
	assert.equal(brands.length, 15);
	for (const b of brands) {
		assert.ok(b.data.meta, b.slug + ' has provenance meta');
		assert.ok(/^https:\/\//.test(b.data.meta.source_url), b.slug + ' source url');
		assert.ok(['pending', 'verified'].includes(b.data.meta.status));
		if (b.data.meta.status === 'verified') { assert.match(String(b.data.meta.last_reviewed), /^\d{4}-\d{2}-\d{2}$/); }
		const ids = new Set();
		for (const it of items(b.data)) {
			assert.ok(!ids.has(it.id), 'duplicate id ' + it.id);
			ids.add(it.id);
			for (const k of N) { assert.equal(typeof it[k], 'number', `${b.slug}/${it.id}.${k}`); assert.ok(it[k] >= 0); }
		}
	}
});

for (const b of brands) {
	const all = items(b.data);
	test(`${b.slug}: single item equals dataset values for every item`, () => {
		for (const it of all) {
			assert.deepEqual(calc.computeTotals([{ item: it, addons: [], qty: 1 }]),
				{ calories: it.calories, protein: it.protein, carbs: it.carbs, fat: it.fat, sodium: it.sodium });
		}
	});
	test(`${b.slug}: two different items add up`, () => {
		const lines = [{ item: all[0], addons: [], qty: 1 }, { item: all[all.length - 1], addons: [], qty: 1 }];
		assert.deepEqual(calc.computeTotals(lines), expected(lines));
	});
	test(`${b.slug}: quantities multiply (x3) and whole menu x2`, () => {
		const l1 = [{ item: all[1] || all[0], addons: [], qty: 3 }];
		assert.deepEqual(calc.computeTotals(l1), expected(l1));
		const whole = all.map(it => ({ item: it, addons: [], qty: 2 }));
		assert.deepEqual(calc.computeTotals(whole), expected(whole));
	});
	test(`${b.slug}: zero quantity contributes nothing; empty/cleared order is zero`, () => {
		assert.deepEqual(calc.computeTotals([{ item: all[0], addons: [], qty: 0 }]), { calories: 0, protein: 0, carbs: 0, fat: 0, sodium: 0 });
		assert.deepEqual(calc.computeTotals([]), { calories: 0, protein: 0, carbs: 0, fat: 0, sodium: 0 });
	});
	test(`${b.slug}: quantity is clamped to 0..${calc.MAX_QTY}`, () => {
		const big = [{ item: all[0], addons: [], qty: 999 }];
		assert.equal(calc.computeTotals(big).calories, all[0].calories * calc.MAX_QTY);
		assert.equal(calc.clampQty(-4), 0);
		assert.equal(calc.clampQty(2.7), 2);
	});
	test(`${b.slug}: every add-on adds exactly its calories (and any macros it defines)`, () => {
		for (const it of all) {
			for (const a of (it.addons || [])) {
				const lines = [{ item: it, addons: [a], qty: 2 }];
				const got = calc.computeTotals(lines);
				assert.deepEqual(got, expected(lines));
				assert.equal(got.calories, (it.calories + a.calories) * 2);
			}
			if ((it.addons || []).length > 1) {
				const lines = [{ item: it, addons: it.addons, qty: 1 }];
				assert.deepEqual(calc.computeTotals(lines), expected(lines));
			}
		}
	});
}

test('spec example: base item + add-on adds every nutrient', () => {
	const item = { id: 'base', name: 'Base', calories: 500, protein: 25, carbs: 40, fat: 25, sodium: 900 };
	const addon = { name: 'Add-on', calories: 50, protein: 2, carbs: 1, fat: 4, sodium: 100 };
	assert.deepEqual(calc.computeTotals([{ item, addons: [addon], qty: 1 }]),
		{ calories: 550, protein: 27, carbs: 41, fat: 29, sodium: 1000 });
	assert.equal(calc.hasUnavailableValues([{ item, addons: [addon], qty: 1 }]), false);
});

test('multiple add-ons, removal and multiple items recalculate from the dataset', () => {
	const a = { id: 'a', name: 'A', calories: 300, protein: 10, carbs: 30, fat: 12, sodium: 500 };
	const b = { id: 'b', name: 'B', calories: 200, protein: 5, carbs: 20, fat: 8, sodium: 300 };
	const s1 = { name: 'S1', calories: 40, protein: 1, carbs: 2, fat: 3, sodium: 50 };
	const s2 = { name: 'S2', calories: 60, protein: 0, carbs: 5, fat: 4, sodium: 150 };
	const both = [{ item: a, addons: [s1, s2], qty: 1 }, { item: b, addons: [], qty: 2 }];
	assert.deepEqual(calc.computeTotals(both), { calories: 800, protein: 21, carbs: 77, fat: 35, sodium: 1300 });
	const removed = [{ item: a, addons: [s1], qty: 1 }, { item: b, addons: [], qty: 2 }];
	assert.deepEqual(calc.computeTotals(removed), { calories: 740, protein: 21, carbs: 72, fat: 31, sodium: 1150 });
	const itemRemoved = [{ item: a, addons: [s1], qty: 1 }, { item: b, addons: [], qty: 0 }];
	assert.deepEqual(calc.computeTotals(itemRemoved), { calories: 340, protein: 11, carbs: 32, fat: 15, sodium: 550 });
});

test('unpublished nutrients are never treated as a real zero', () => {
	const item = { id: 'n', name: 'Nuggets', calories: 250, protein: 27, carbs: 11, fat: 11, sodium: 1210 };
	const sauce = { name: 'Sauce', calories: 140, protein: 0, carbs: 6, fat: 13 }; // sodium not published
	const order = [{ item, addons: [sauce], qty: 1 }];
	const u = calc.unavailableNutrients(order);
	assert.deepEqual(u.sodium, ['Sauce']);
	assert.deepEqual(u.calories, []);
	assert.deepEqual(u.fat, []);
	assert.equal(calc.hasUnavailableValues(order), true);
	// Known nutrients still add up fully.
	assert.deepEqual(calc.computeTotals(order), { calories: 390, protein: 27, carbs: 17, fat: 24, sodium: 1210 });
	// A calories-only add-on flags all four macros.
	const calOnly = [{ item, addons: [{ name: 'Extra Cheese', calories: 20 }], qty: 1 }];
	assert.deepEqual(calc.unavailableNutrients(calOnly).protein, ['Extra Cheese']);
	// Zero-quantity lines and "no change" add-ons (0 cal, nothing listed) flag nothing.
	assert.equal(calc.hasUnavailableValues([{ item, addons: [sauce], qty: 0 }]), false);
	assert.equal(calc.hasUnavailableValues([{ item, addons: [{ name: 'No Onions', calories: 0 }], qty: 1 }]), false);
	// null in a dataset is "not published", not 0.
	assert.equal(calc.isKnown(null), false);
	assert.equal(calc.isKnown(0), true);
});

for (const b of brands) {
	test(`${b.slug}: add-on nutrient fields are numbers when present`, () => {
		for (const it of items(b.data)) {
			for (const a of (it.addons || [])) {
				assert.equal(typeof a.name, 'string');
				assert.equal(typeof a.calories, 'number', `${b.slug}/${it.id}/${a.name}.calories`);
				for (const k of ['protein', 'carbs', 'fat', 'sodium']) {
					if (k in a) { assert.equal(typeof a[k], 'number', `${b.slug}/${it.id}/${a.name}.${k}`); assert.ok(a[k] >= 0); }
				}
			}
		}
	});
	if (b.data.meta.status === 'verified') {
		test(`${b.slug}: verified dataset has a review date and official source`, () => {
			assert.match(String(b.data.meta.last_reviewed), /^\d{4}-\d{2}-\d{2}$/);
			assert.ok(b.data.meta.source_name);
		});
	} else {
		test(`${b.slug}: pending dataset shows no review date`, () => {
			assert.equal(b.data.meta.last_reviewed, null);
		});
	}
}

test('add-on macros are included when the dataset defines them', () => {
	const item = { id: 'x', calories: 100, protein: 5, carbs: 10, fat: 4, sodium: 200 };
	const addon = { name: 'Bacon', calories: 40, protein: 3, carbs: 0, fat: 3, sodium: 150 };
	assert.deepEqual(calc.computeTotals([{ item, addons: [addon], qty: 2 }]),
		{ calories: 280, protein: 16, carbs: 20, fat: 14, sodium: 700 });
	assert.equal(calc.hasCaloriesOnlyAddons([{ item, addons: [addon], qty: 1 }]), false);
	assert.equal(calc.hasCaloriesOnlyAddons([{ item, addons: [{ name: 'Sauce', calories: 50 }], qty: 1 }]), true);
	assert.equal(calc.hasCaloriesOnlyAddons([{ item, addons: [{ name: 'No Onions', calories: 0 }], qty: 1 }]), false);
});

test('decimal values do not produce floating-point noise', () => {
	const item = { id: 'y', calories: 0.1, protein: 0.2, carbs: 0.7, fat: 0.1, sodium: 0 };
	const t = calc.computeTotals([{ item, addons: [], qty: 3 }]);
	assert.equal(t.calories, 0.3);
	assert.equal(t.protein, 0.6);
	assert.equal(t.carbs, 2.1);
});

test('identical item + add-on combinations share one order line key', () => {
	const item = { id: 'z' };
	assert.equal(calc.lineKey(item, [{ name: 'B' }, { name: 'A' }]), calc.lineKey(item, [{ name: 'A' }, { name: 'B' }]));
	assert.notEqual(calc.lineKey(item, []), calc.lineKey(item, [{ name: 'A' }]));
});

test('regression: original v1.1.8 engine ignored add-on macros', () => {
	// Documented bug: add-ons only ever changed calories. The new engine uses
	// add-on macros whenever a dataset provides them.
	const item = { id: 'r', calories: 10, protein: 1, carbs: 1, fat: 1, sodium: 1 };
	const t = calc.computeTotals([{ item, addons: [{ name: 'A', calories: 5, protein: 2, carbs: 0, fat: 0, sodium: 0 }], qty: 1 }]);
	assert.equal(t.protein, 3);
});

for (const b of brands) {
	if (!b.data.deals) { continue; }
	test(`${b.slug}: every deal uses real items and adds up like separate lines`, () => {
		assert.ok(b.data.deals_meta && b.data.deals_meta.checked && /^https:\/\//.test(b.data.deals_meta.source_url), b.slug + ' deals_meta');
		const ids = new Set();
		for (const d of b.data.deals) {
			assert.ok(!ids.has(d.id), 'duplicate deal id ' + d.id);
			ids.add(d.id);
			const lines = calc.dealLines(d, b.data);
			assert.ok(lines, b.slug + ' deal ' + d.id + ' references a missing item');
			for (const l of lines) {
				for (const k of N) { assert.equal(typeof l.item[k], 'number', d.id + ' ' + l.item.id + ' ' + k); }
			}
			assert.deepEqual(calc.computeTotals(lines), expected(lines));
		}
	});
}

test('dealLines rejects a deal with an unknown item', () => {
	const data = brands.find(b => b.slug === 'mcdonalds').data;
	assert.equal(calc.dealLines({ items: [{ id: 'big-mac', qty: 1 }, { id: 'no-such-item', qty: 1 }] }, data), null);
});

/* ---- Order tools ---- */
for (const b of brands) {
	test(`${b.slug}: share link round-trips the order (items, quantities, add-ons)`, () => {
		const its = items(b.data);
		const order = its.slice(0, 4).map((item, i) => ({ item, addons: (item.addons || []).slice(0, 2), qty: i + 1 }));
		const back = calc.decodeOrder(calc.encodeOrder(order), b.data);
		assert.equal(back.length, order.length);
		back.forEach((l, i) => {
			assert.equal(l.item.id, order[i].item.id);
			assert.equal(l.qty, order[i].qty);
			assert.deepEqual(l.addons.map(a => a.name).sort(), order[i].addons.map(a => a.name).sort());
		});
		assert.deepEqual(calc.computeTotals(back), expected(order));
	});
	test(`${b.slug}: every swap is from the same category and lighter`, () => {
		for (const c of b.data.categories) {
			for (const item of c.items) {
				const s = calc.findSwap(item, b.data);
				if (!s) { continue; }
				assert.ok(c.items.includes(s.item), item.id + ' swap leaves its category');
				assert.ok(s.item.calories < item.calories, item.id + ' swap is not lighter');
				assert.equal(s.saving, Math.round((item.calories - s.item.calories) * 10) / 10);
			}
		}
	});
	test(`${b.slug}: pasting an item's own name finds an item with that name`, () => {
		for (const item of items(b.data)) {
			const r = calc.parseCart(item.name, b.data);
			assert.equal(r.matched.length, 1, item.name);
			assert.equal(r.matched[0].item.name, item.name);
		}
	});
	test(`${b.slug}: best orders stay within the budget and protein floor`, () => {
		const orders = calc.bestOrders(b.data, { calories: 700, protein: 20 });
		for (const o of orders) {
			assert.ok(o.totals.calories <= 700);
			assert.ok(o.totals.protein >= 20);
			assert.ok(o.items.length >= 1 && o.items.length <= 3);
		}
	});
}

test('decodeOrder ignores unknown ids and bad parts; quantity is clamped', () => {
	const md = brands.find(b => b.slug === 'mcdonalds').data;
	const lines = calc.decodeOrder('big-mac*99,nope,<script>,fries-small', md);
	assert.deepEqual(lines.map(l => [l.item.id, l.qty]), [['big-mac', 20], ['fries-small', 1]]);
});

test('parseCart reads quantities and skips prices', () => {
	const md = brands.find(b => b.slug === 'mcdonalds').data;
	const r = calc.parseCart('2x Big Mac $11.98\nMedium French Fries x3\n10 pc McNuggets\nHappy Meal toy', md);
	assert.deepEqual(r.matched.map(m => [m.item.id, m.qty]), [['big-mac', 2], ['fries-medium', 3], ['nuggets-10', 1]]);
	assert.deepEqual(r.unmatched, ['Happy Meal toy']);
});

test('parseCart splits comma-separated items and reports every piece it cannot match', () => {
	const md = brands.find(b => b.slug === 'mcdonalds').data;
	let r = calc.parseCart('2 Big Mac, 1 medium fries', md);
	assert.deepEqual(r.matched.map(m => [m.item.id, m.qty]), [['big-mac', 2], ['fries-medium', 1]]);
	assert.deepEqual(r.unmatched, []);
	r = calc.parseCart('2 Big Mac, Happy Meal toy, 1 small fries', md);
	assert.deepEqual(r.matched.map(m => [m.item.id, m.qty]), [['big-mac', 2], ['fries-small', 1]]);
	assert.deepEqual(r.unmatched, ['Happy Meal toy']);
	// A thousands separator is not an item separator.
	r = calc.parseCart('Big Mac $1,299.00, small fries', md);
	assert.deepEqual(r.matched.map(m => m.item.id), ['big-mac', 'fries-small']);
	assert.deepEqual(r.unmatched, []);
});

test('parseCart keeps commas that belong to an item name', () => {
	const du = brands.find(b => b.slug === 'dunkin').data;
	const r = calc.parseCart('Bacon, Egg & Cheese on Croissant, Hash Browns', du);
	assert.deepEqual(r.matched.map(m => m.item.id), ['bacon-egg-cheese-croissant', 'hash-browns']);
	assert.deepEqual(r.unmatched, []);
});

test('splitCount keeps the split between 1 and 20 people', () => {
	assert.equal(calc.splitCount('1000'), 20);
	assert.equal(calc.splitCount('20'), 20);
	assert.equal(calc.splitCount('3.7'), 3);
	assert.equal(calc.splitCount('0'), 1);
	assert.equal(calc.splitCount('-4'), 1);
	assert.equal(calc.splitCount(''), 1);
});

test('exercise minutes follow the Harvard Health 30-minute table', () => {
	assert.deepEqual(calc.exerciseMinutes(133, 155), { walk: 30, run: 14, bike: 14 });
	assert.deepEqual(calc.exerciseMinutes(336, 185), { walk: 63, run: 30, bike: 30 });
});

test('generated item-types.json is up to date (run node scripts/build-item-types.js)', () => {
	const gen = require('../../scripts/build-item-types.js');
	assert.equal(fs.readFileSync(gen.OUT, 'utf8'), gen.build());
});

test('extension data and engine copy are up to date (run node scripts/build-extension.js)', () => {
	const ext = require('../../scripts/build-extension.js');
	for (const [file, build] of Object.entries(ext.FILES)) {
		assert.equal(fs.readFileSync(file, 'utf8'), build(), file);
	}
});

test('page-catalog.json (meal, comparison and vegetarian pages) only uses real items', () => {
	const cat = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'inc', 'page-catalog.json'), 'utf8'));
	const ids = Object.fromEntries(brands.map(b => [b.slug, new Set(items(b.data).map(i => i.id))]));
	const has = (brand, id) => ids[brand] && ids[brand].has(id);
	for (const m of cat.meals) {
		assert.ok(has(m.brand, m.main), 'meal ' + m.brand + ':' + m.main);
		assert.ok((cat.meal_sets[m.brand] || []).length, 'meal sets for ' + m.brand);
	}
	for (const [brand, sets] of Object.entries(cat.meal_sets)) {
		for (const s of sets) {
			for (const id of s.items) { assert.ok(has(brand, id), 'meal set ' + brand + ':' + id); }
		}
	}
	const slugs = new Set();
	for (const c of cat.comparisons) {
		assert.ok(c.title && /^[a-z0-9-]+$/.test(c.slug) && !slugs.has(c.slug), 'comparison ' + c.slug);
		slugs.add(c.slug);
		for (const side of [c.a, c.b]) {
			const [brand, id] = side.split(':');
			assert.ok(has(brand, id), 'comparison ' + c.slug + ' ' + side);
		}
	}
	for (const [brand, list] of Object.entries(cat.vegetarian)) {
		for (const id of list) { assert.ok(has(brand, id), 'vegetarian ' + brand + ':' + id); }
	}
});
