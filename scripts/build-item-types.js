#!/usr/bin/env node
/**
 * Writes theme/assets/generated/item-types.json: { brand: { itemId: type } }
 * using the same itemType() the calculator uses, so the PHP goal lists,
 * cheat sheets and report group items exactly like the JS tools do.
 *
 * Also writes theme/assets/generated/tool-examples.json: each brand's best
 * Order Ideas result for the tool's default inputs, from the same
 * bestOrders() the tool runs, so inc/tools.php can print the example result
 * before (or without) JavaScript.
 * Run after changing any dataset:  node scripts/build-item-types.js
 * (tests fail if the files are out of date).
 */
'use strict';
const fs = require('fs');
const path = require('path');
const calc = require('../theme/assets/js/calculator.js');

const DATA = path.join(__dirname, '..', 'theme', 'assets', 'data');
const OUT = path.join(__dirname, '..', 'theme', 'assets', 'generated', 'item-types.json');
const OUT_EXAMPLES = path.join(__dirname, '..', 'theme', 'assets', 'generated', 'tool-examples.json');

// The Order Ideas defaults in assets/js/tools.js: normal and small-portion mode.
const ORDER_IDEAS = {
	default: { calories: 600, protein: 30, maxItems: 3, sort: 'protein', limit: 1 },
	'small-portion': { calories: 450, protein: 20, maxItems: 2, sort: 'density', limit: 1 },
};

function buildExamples() {
	const out = {};
	for (const [mode, opts] of Object.entries(ORDER_IDEAS)) {
		out[mode] = { inputs: { calories: opts.calories, protein: opts.protein }, brands: {} };
		for (const f of fs.readdirSync(DATA).filter(f => f.endsWith('.json')).sort()) {
			const slug = f.replace('.json', '');
			if (slug === 'chipotle') { continue; } // Build-your-own: left out of the tool too.
			const data = JSON.parse(fs.readFileSync(path.join(DATA, f), 'utf8'));
			const best = calc.bestOrders(data, Object.assign({}, opts))[0];
			if (best) {
				out[mode].brands[slug] = {
					lines: calc.groupLines(best.items).map(l => ({ id: l.item.id, qty: l.qty })),
					totals: best.totals,
				};
			}
		}
	}
	return JSON.stringify(out, null, 1) + '\n';
}

function build() {
	const out = {};
	for (const f of fs.readdirSync(DATA).filter(f => f.endsWith('.json')).sort()) {
		const data = JSON.parse(fs.readFileSync(path.join(DATA, f), 'utf8'));
		const slug = f.replace('.json', '');
		out[slug] = {};
		for (const x of calc.allItems(data)) {
			out[slug][x.item.id] = calc.itemType(x.item, x.catName, slug);
		}
	}
	return JSON.stringify(out, null, 1) + '\n';
}

if (require.main === module) {
	fs.writeFileSync(OUT, build());
	console.log('wrote ' + path.relative(process.cwd(), OUT));
	fs.writeFileSync(OUT_EXAMPLES, buildExamples());
	console.log('wrote ' + path.relative(process.cwd(), OUT_EXAMPLES));
}
module.exports = { build, OUT, buildExamples, OUT_EXAMPLES };
