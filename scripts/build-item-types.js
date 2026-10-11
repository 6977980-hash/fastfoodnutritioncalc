#!/usr/bin/env node
/**
 * Writes theme/assets/generated/item-types.json: { brand: { itemId: type } }
 * using the same itemType() the calculator uses, so the PHP goal lists,
 * cheat sheets and report group items exactly like the JS tools do.
 * Run after changing any dataset:  node scripts/build-item-types.js
 * (tests fail if the file is out of date).
 */
'use strict';
const fs = require('fs');
const path = require('path');
const calc = require('../theme/assets/js/calculator.js');

const DATA = path.join(__dirname, '..', 'theme', 'assets', 'data');
const OUT = path.join(__dirname, '..', 'theme', 'assets', 'generated', 'item-types.json');

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
}
module.exports = { build, OUT };
