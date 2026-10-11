/**
 * Fast Food Nutrition Calculators — reusable calculator engine (v1.2).
 *
 * Data source: the brand's JSON dataset (assets/data/{brand}.json). The PHP
 * shortcode embeds the same file inline as <script type="application/json">
 * so the calculator renders without a second network request; if that inline
 * copy is missing it falls back to fetching the JSON file. Either way there is
 * exactly one source of truth — the JSON file.
 *
 * Math rules (covered by tests/calculator.test.js):
 *   line total  = (item + every selected add-on) × quantity
 *   add-ons     = contribute EVERY nutrient they define (calories, protein,
 *                 carbs, fat, sodium) to the line and order totals
 *   unavailable = a nutrient the official source does not publish is stored
 *                 as null / left out. It is never treated as a real 0: the
 *                 total for that nutrient is shown as a minimum ("+") and the
 *                 UI names the item or add-on whose value is not published
 *   order total = sum of line totals, rounded to 1 decimal for display
 *
 * No build step, no dependencies.
 */
(function () {
	'use strict';

	var NUTRIENTS = ['calories', 'protein', 'carbs', 'fat', 'sodium'];
	var MAX_QTY = 20;

	/* ------------------------------------------------------------------
	 * Pure math (exported for Node tests at the bottom of this file)
	 * ---------------------------------------------------------------- */
	function num(v) {
		var n = Number(v);
		return isFinite(n) ? n : 0;
	}

	/** Is a nutrient value actually published (not null / missing / blank)? */
	function isKnown(v) {
		return v !== null && v !== undefined && v !== '' && isFinite(Number(v));
	}

	function round1(n) {
		return Math.round(n * 10) / 10;
	}

	function clampQty(q) {
		q = Math.floor(num(q));
		if (q < 0) { return 0; }
		if (q > MAX_QTY) { return MAX_QTY; }
		return q;
	}

	/** Nutrition for ONE unit of a line (item + its add-ons). */
	function unitTotals(entry) {
		var t = {};
		NUTRIENTS.forEach(function (k) {
			t[k] = num(entry.item[k]);
			(entry.addons || []).forEach(function (a) { t[k] += num(a[k]); });
		});
		return t;
	}

	/** Nutrition for a whole line (unit × quantity). */
	function lineTotals(entry) {
		var qty = clampQty(entry.qty === undefined ? 1 : entry.qty);
		var u = unitTotals(entry);
		var t = {};
		NUTRIENTS.forEach(function (k) { t[k] = round1(u[k] * qty); });
		return t;
	}

	function computeTotals(order) {
		var totals = { calories: 0, protein: 0, carbs: 0, fat: 0, sodium: 0 };
		(order || []).forEach(function (entry) {
			var lt = lineTotals(entry);
			NUTRIENTS.forEach(function (k) { totals[k] += lt[k]; });
		});
		NUTRIENTS.forEach(function (k) { totals[k] = round1(totals[k]); });
		return totals;
	}

	/**
	 * An add-on that changes nothing (e.g. "No Onions", 0 cal, no macros
	 * listed) is not "missing" anything — it simply adds nothing.
	 */
	function isNoChangeAddon(a) {
		return isKnown(a.calories) && num(a.calories) === 0 &&
			!isKnown(a.protein) && !isKnown(a.carbs) && !isKnown(a.fat) && !isKnown(a.sodium);
	}

	/**
	 * For each nutrient, the names of the items / add-ons in the order whose
	 * value for that nutrient is not published. Lines with quantity 0 are
	 * ignored. Returns { calories: [], protein: [...], ... } (unique names).
	 */
	function unavailableNutrients(order) {
		var out = {};
		NUTRIENTS.forEach(function (k) { out[k] = []; });
		(order || []).forEach(function (entry) {
			if (clampQty(entry.qty === undefined ? 1 : entry.qty) === 0) { return; }
			NUTRIENTS.forEach(function (k) {
				if (!isKnown(entry.item[k]) && out[k].indexOf(entry.item.name) === -1) {
					out[k].push(entry.item.name);
				}
				(entry.addons || []).forEach(function (a) {
					if (isNoChangeAddon(a)) { return; }
					if (!isKnown(a[k]) && out[k].indexOf(a.name) === -1) {
						out[k].push(a.name);
					}
				});
			});
		});
		return out;
	}

	/** True when any nutrient total in the order is incomplete. */
	function hasUnavailableValues(order) {
		var u = unavailableNutrients(order);
		return NUTRIENTS.some(function (k) { return u[k].length > 0; });
	}

	/** Back-compat alias (v1.2): any add-on whose macros are not published. */
	function hasCaloriesOnlyAddons(order) {
		return (order || []).some(function (entry) {
			return (entry.addons || []).some(function (a) {
				return !isNoChangeAddon(a) &&
					(!isKnown(a.protein) || !isKnown(a.carbs) || !isKnown(a.fat) || !isKnown(a.sodium));
			});
		});
	}

	function lineKey(item, addons) {
		return item.id + '|' + addons.map(function (a) { return a.name; }).sort().join(',');
	}

	/**
	 * Resolve a deal's { id, qty } entries against the dataset. Returns order
	 * lines ({ item, addons: [], qty }) or null if any item id is missing, so
	 * a deal is never shown with part of its food silently dropped.
	 */
	/** Same item listed twice -> one line with qty 2. */
	function groupLines(items) {
		var lines = [];
		items.forEach(function (it) {
			var last = lines[lines.length - 1];
			if (last && last.item === it) { last.qty += 1; } else { lines.push({ key: lineKey(it, []), item: it, addons: [], qty: 1 }); }
		});
		return lines;
	}

	function dealLines(deal, data) {
		var byId = {};
		(data.categories || []).forEach(function (c) {
			(c.items || []).forEach(function (i) { byId[i.id] = i; });
		});
		var lines = [];
		var items = (deal && deal.items) || [];
		for (var i = 0; i < items.length; i++) {
			var item = byId[items[i].id];
			if (!item) { return null; }
			lines.push({ item: item, addons: [], qty: clampQty(items[i].qty || 1) });
		}
		return lines.length ? lines : null;
	}

	/* ------------------------------------------------------------------
	 * Order tools (pure, shared with tools.js, covered by tests)
	 * ---------------------------------------------------------------- */

	/** Every item of a dataset, each tagged with its category id/name. */
	function allItems(data) {
		var out = [];
		(data.categories || []).forEach(function (c) {
			(c.items || []).forEach(function (i) { out.push({ item: i, catId: c.id, catName: c.name }); });
		});
		return out;
	}

	function itemById(data, id) {
		var list = allItems(data);
		for (var i = 0; i < list.length; i++) {
			if (list[i].item.id === id) { return list[i].item; }
		}
		return null;
	}

	/**
	 * Order <-> short URL string. Line = id, optional *qty, optional ~add-on
	 * indexes ("big-mac*2~0.1"); lines joined by commas. Ids are slugs, so no
	 * escaping is needed beyond encodeURIComponent of the whole value.
	 */
	function encodeOrder(order) {
		return (order || []).filter(function (e) { return e.qty > 0; }).map(function (e) {
			var idx = (e.addons || []).map(function (a) { return (e.item.addons || []).indexOf(a); })
				.filter(function (n) { return n >= 0; }).sort(function (a, b) { return a - b; });
			return e.item.id + (e.qty > 1 ? '*' + e.qty : '') + (idx.length ? '~' + idx.join('.') : '');
		}).join(',');
	}

	function decodeOrder(str, data) {
		var lines = [];
		String(str || '').split(',').forEach(function (part) {
			var m = /^([a-z0-9-]+)(?:\*(\d+))?(?:~([\d.]+))?$/.exec(part.trim());
			if (!m) { return; }
			var item = itemById(data, m[1]);
			if (!item) { return; }
			var addons = [];
			if (m[3]) {
				m[3].split('.').forEach(function (n) {
					var a = (item.addons || [])[Number(n)];
					if (a && addons.indexOf(a) < 0) { addons.push(a); }
				});
			}
			var qty = clampQty(m[2] ? Number(m[2]) : 1);
			if (qty > 0) { lines.push({ key: lineKey(item, addons), item: item, addons: addons, qty: qty }); }
		});
		return lines;
	}

	/**
	 * A lighter item from the same menu category: fewer calories and at
	 * least 70% of the protein (so it is a like-for-like swap, not "eat a
	 * side salad instead"). Falls back to the nearest lower-calorie item
	 * when nothing keeps the protein. Null when nothing is lighter.
	 */
	function findSwap(item, data) {
		var cat = null;
		(data.categories || []).forEach(function (c) {
			(c.items || []).forEach(function (i) { if (i.id === item.id) { cat = c; } });
		});
		if (!cat || !isKnown(item.calories)) { return null; }
		var lower = cat.items.filter(function (i) {
			return i.id !== item.id && isKnown(i.calories) && i.calories < item.calories;
		});
		if (!lower.length) { return null; }
		var keepsProtein = lower.filter(function (i) {
			return num(i.protein) >= 0.7 * num(item.protein);
		});
		var pick;
		if (keepsProtein.length) {
			// Prefer the same product in another size (Coke Medium -> Coke Small).
			var base = tokens(item.name).filter(function (t) { return SIZE_WORDS.indexOf(t) < 0; });
			var overlap = function (i) {
				return tokens(i.name).filter(function (t) { return base.indexOf(t) >= 0; }).length;
			};
			pick = keepsProtein.reduce(function (a, b) {
				var d = overlap(b) - overlap(a);
				return (d > 0 || (d === 0 && b.calories < a.calories)) ? b : a;
			});
		} else {
			pick = lower.reduce(function (a, b) { return b.calories > a.calories ? b : a; });
		}
		return { item: pick, saving: round1(item.calories - pick.calories) };
	}

	/*
	 * Calories burned in 30 minutes by body weight (lb), from Harvard Health
	 * Publishing, "Calories burned in 30 minutes for people of three different
	 * weights": walking 3.5 mph, running 5 mph, bicycling 12-13.9 mph.
	 */
	var BURN_30 = {
		125: { walk: 107, run: 240, bike: 240 },
		155: { walk: 133, run: 288, bike: 288 },
		185: { walk: 159, run: 336, bike: 336 }
	};
	function exerciseMinutes(calories, weightLb) {
		var w = Number(weightLb);
		var row = BURN_30[w] || BURN_30[155];
		var out = {};
		Object.keys(row).forEach(function (k) {
			out[k] = Math.round(num(calories) / row[k] * 30);
		});
		return out;
	}

	/** Lower-case words without punctuation, trademark marks or apostrophes. */
	function normalizeName(s) {
		return String(s || '').toLowerCase()
			.replace(/[®™©]/g, '')
			.replace(/['’`]/g, '')
			.replace(/&/g, ' and ')
			.replace(/(\d+)\s*-?\s*(pc|pcs|piece|pieces|ct|count)\b/g, '$1 pc')
			.replace(/\bsm\b/g, 'small').replace(/\bmed\b|\bmd\b/g, 'medium').replace(/\blg\b/g, 'large')
			.replace(/[^a-z0-9 ]+/g, ' ')
			.replace(/\s+/g, ' ').trim();
	}

	var SIZE_WORDS = ['small', 'medium', 'large', 'jr', 'kids', 'regular', 'mini'];
	var MULTI_TYPES = ['mexican', 'pizza', 'chicken-piece', 'wings', 'sub', 'breakfast'];
	var MAIN_TYPES = ['burger', 'chicken-sandwich', 'nuggets', 'pizza', 'sub', 'mexican', 'chicken-piece', 'wings', 'breakfast', 'salad', 'soup', 'entree', 'other'];
	var STOP = { the: 1, a: 1, with: 1, and: 1, of: 1, on: 1, 'in': 1, 'for': 1 };
	function tokens(s) {
		return normalizeName(s).split(' ').filter(function (t) { return t && !STOP[t]; });
	}

	/** 0..1 similarity between a typed line and an item name. */
	function nameScore(line, name) {
		var a = tokens(line);
		var b = tokens(name);
		if (!a.length || !b.length) { return 0; }
		var hit = 0;
		b.forEach(function (t) { if (a.indexOf(t) >= 0) { hit += 1; } });
		var extra = a.filter(function (t) { return b.indexOf(t) < 0; }).length;
		// Numbers (sizes, piece counts) must agree when both sides have them.
		var na = a.filter(function (t) { return /^\d+$/.test(t); });
		var nb = b.filter(function (t) { return /^\d+$/.test(t); });
		if (na.length && nb.length && !na.some(function (n) { return nb.indexOf(n) >= 0; })) { return 0; }
		var sizes = SIZE_WORDS;
		var sa = a.filter(function (t) { return sizes.indexOf(t) >= 0; });
		var sb = b.filter(function (t) { return sizes.indexOf(t) >= 0; });
		if (sa.length && sb.length && !sa.some(function (n) { return sb.indexOf(n) >= 0; })) { return 0; }
		return hit / (b.length + 0.5 * extra);
	}

	/**
	 * Read a pasted order (delivery app cart, receipt, a note) line by line.
	 * Returns { matched: [{ item, qty, line, score }], unmatched: [line] }.
	 */
	function parseCart(text, data) {
		var items = allItems(data).map(function (x) { return x.item; });
		var matched = [];
		var unmatched = [];
		String(text || '').split(/\r?\n|;/).forEach(function (raw) {
			var line = raw.replace(/\$\s*\d+(\.\d+)?/g, ' ').trim();
			if (!line || line.length < 3) { return; }
			var qty = 1;
			var m = /^\s*(\d{1,2})\s*(?:x|×)\s+/i.exec(line) || /^\s*(\d{1,2})\s+(?!pc\b|piece|pieces|ct\b|count\b|-?inch|")/i.exec(line);
			if (m) { qty = Number(m[1]); line = line.slice(m[0].length); }
			var m2 = /\s*(?:x|×)\s*(\d{1,2})\s*$/i.exec(line) || /\bqty:?\s*(\d{1,2})\b/i.exec(line) || /\((\d{1,2})\)\s*$/.exec(line);
			if (m2) { qty = Number(m2[1]); line = line.replace(m2[0], ' '); }
			var best = null;
			var bestScore = 0;
			items.forEach(function (it) {
				var s = nameScore(line, it.name);
				if (s > bestScore) { bestScore = s; best = it; }
			});
			if (best && bestScore >= 0.5) {
				matched.push({ item: best, qty: clampQty(qty) || 1, line: raw.trim(), score: round1(bestScore) });
			} else if (/[a-z]{3}/i.test(line)) {
				unmatched.push(raw.trim());
			}
		});
		return { matched: matched, unmatched: unmatched };
	}

	/* Rough food type from item + category name, for cross-chain matching. */
	var TYPES = [
		['sub', /^6 |footlong|protein pocket|italiano/],
		['pizza', /\b(pizza|slice)\b/],
		['salad', /\bsalad\b/],
		['drink', /\b(coke|coca|sprite|pepsi|tea|lemonade|soda|drink|coolatta|water)\b/],
		['coffee', /\b(coffee|latte|cappuccino|espresso|macchiato)\b/],
		['shake', /\b(shake|frosty|blizzard|mcflurry|sundae|cone|ice cream)\b/],
		['entree', /\b(orange chicken|beijing|kung pao|broccoli beef|shrimp|teriyaki|steak|mushroom chicken|string bean|honey sesame|pot pie|famous bowl)\b/],
		['dessert', /\b(cookie|pie|cake|brownie|cinnamon|churro|twists|turnover|donut|munchkin)\b/],
		['breakfast', /\b(mcmuffin|biscuit|croissant|wake up wrap|hash browns?|scramble|egg and cheese|egg cheese|sausage|breakfast|pancake|avocado toast)\b/],
		['nuggets', /\b(nugget|nuggets|mcnuggets|tenders?|strips?|minis|popcorn chicken|snacker|chicken fries)\b/],
		['fries', /\b(fries|fry|tots|onion rings)\b/],
		['wings', /\bwings?\b/],
		['chicken-piece', /\b(breast|thigh|drumstick)\b/],
		['burger', /\b(burger|whopper|big mac|quarter pounder|mcdouble|baconator|daves|cheeseburger|hamburger|bacon king|double stack)\b/],
		['chicken-sandwich', /\b(sandwich|mcchicken|royal crispy|cool wrap|twister|filet o fish)\b/],
		['mexican', /\b(taco|burrito|quesadilla|crunchwrap|gordita|nachos|chalupa)\b/],
		['soup', /\b(soup|chili)\b/],
		['side', /\b(rice|chow mein|greens|mashed|coleslaw|mac|beans|corn|potato|bread|breadsticks?|spring rolls?|egg roll|rangoon|cornbread|baguette|chips)\b/]
	];
	/* Chipotle is sold by ingredient, so its lines never stand in for a dish. */
	function itemType(item, catName, brandSlug) {
		if (brandSlug === 'chipotle') { return 'ingredient'; }
		var n = normalizeName(item.name);
		var s = normalizeName(item.name + ' ' + (catName || ''));
		for (var i = 0; i < TYPES.length; i++) {
			if (TYPES[i][1].test(n)) { return TYPES[i][0]; }
		}
		for (var j = 0; j < TYPES.length; j++) {
			if (TYPES[j][1].test(s)) { return TYPES[j][0]; }
		}
		return 'other';
	}

	/**
	 * Best orders from one menu for a calorie budget: combinations of up to
	 * `maxItems` different items (no drinks unless allowed) within the
	 * budget that meet the protein floor and any carb / sodium caps, sorted
	 * by protein (then by fewer calories), or by protein per calorie with
	 * sort: 'density'. Returns up to `limit` orders whose main item differs.
	 */
	function bestOrders(data, opts) {
		opts = opts || {};
		var budget = num(opts.calories) || 600;
		var minProtein = num(opts.protein);
		var maxCarbs = isKnown(opts.maxCarbs) && opts.maxCarbs !== '' ? num(opts.maxCarbs) : Infinity;
		var maxSodium = isKnown(opts.maxSodium) && opts.maxSodium !== '' ? num(opts.maxSodium) : Infinity;
		var maxItems = Math.max(1, Math.min(3, num(opts.maxItems) || 3));
		var limit = num(opts.limit) || 3;
		var typeOf = {};
		var pool = allItems(data).filter(function (x) {
			var t = itemType(x.item, x.catName);
			if (!opts.allowDrinks && (t === 'drink' || t === 'coffee')) { return false; }
			if (/family|bucket/i.test(x.item.name)) { return false; }
			return isKnown(x.item.calories) && x.item.calories >= 60 && x.item.calories <= budget;
		}).map(function (x) { var it = x.item; typeOf[it.id] = itemType(it, x.catName); return it; });
		var results = [];
		function consider(combo) {
			// A burger + fries, not two sizes of nuggets: one item of each kind,
			// except kinds people do order several of (tacos, slices, pieces),
			// and never two sizes of the same product.
			var seen = {};
			var bases = {};
			for (var k = 0; k < combo.length; k++) {
				var ty = typeOf[combo[k].id];
				var base = tokens(combo[k].name).filter(function (t) { return SIZE_WORDS.indexOf(t) < 0 && !/^\d+$/.test(t) && t !== 'pc' && t !== 'ct'; }).join(' ');
				var repeat = k > 0 && combo[k] === combo[k - 1];
				if (!repeat && ((seen[ty] && MULTI_TYPES.indexOf(ty) < 0) || bases[base])) { return; }
				seen[ty] = true;
				bases[base] = true;
			}
			// Breakfast is a different menu (morning only): don't mix it with lunch mains.
			if (seen.breakfast && Object.keys(seen).some(function (t) { return t !== 'breakfast' && MAIN_TYPES.indexOf(t) >= 0; })) { return; }
			var t = computeTotals(combo.map(function (i) { return { item: i, addons: [], qty: 1 }; }));
			if (t.calories > budget || t.protein < minProtein || t.carbs > maxCarbs || t.sodium > maxSodium) { return; }
			results.push({ items: combo, totals: t });
		}
		// Kinds people order several of (tacos, slices) may repeat the same item.
		var again = function (i) { return MULTI_TYPES.indexOf(typeOf[pool[i].id]) >= 0 ? i : i + 1; };
		for (var a = 0; a < pool.length; a++) {
			consider([pool[a]]);
			if (maxItems < 2) { continue; }
			for (var b = again(a); b < pool.length; b++) {
				if (pool[a].calories + pool[b].calories > budget) { continue; }
				consider([pool[a], pool[b]]);
				if (maxItems < 3) { continue; }
				for (var c = again(b); c < pool.length; c++) {
					if (pool[a].calories + pool[b].calories + pool[c].calories > budget) { continue; }
					consider([pool[a], pool[b], pool[c]]);
				}
			}
		}
		var density = function (o) { return o.totals.calories > 0 ? o.totals.protein / o.totals.calories : 0; };
		results.sort(function (x, y) {
			if (opts.sort === 'density') {
				return (density(y) - density(x)) || (y.totals.protein - x.totals.protein);
			}
			return (y.totals.protein - x.totals.protein) || (x.totals.calories - y.totals.calories) || (x.items.length - y.items.length);
		});
		var picked = [];
		var mains = {};
		for (var r = 0; r < results.length && picked.length < limit; r++) {
			var main = results[r].items.reduce(function (p, q) { return q.calories > p.calories ? q : p; });
			if (mains[main.id]) { continue; }
			mains[main.id] = true;
			picked.push(results[r]);
		}
		return picked;
	}

	var api = {
		NUTRIENTS: NUTRIENTS,
		MAX_QTY: MAX_QTY,
		unitTotals: unitTotals,
		lineTotals: lineTotals,
		computeTotals: computeTotals,
		clampQty: clampQty,
		hasCaloriesOnlyAddons: hasCaloriesOnlyAddons,
		unavailableNutrients: unavailableNutrients,
		hasUnavailableValues: hasUnavailableValues,
		isKnown: isKnown,
		lineKey: lineKey,
		dealLines: dealLines,
		groupLines: groupLines,
		allItems: allItems,
		itemById: itemById,
		encodeOrder: encodeOrder,
		decodeOrder: decodeOrder,
		findSwap: findSwap,
		exerciseMinutes: exerciseMinutes,
		normalizeName: normalizeName,
		nameScore: nameScore,
		parseCart: parseCart,
		itemType: itemType,
		bestOrders: bestOrders
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	if (typeof window !== 'undefined') {
		window.FFNCCalc = api;
	}
	if (typeof document === 'undefined') {
		return;
	}

	/* ------------------------------------------------------------------
	 * DOM helpers
	 * ---------------------------------------------------------------- */
	function escapeHtml(str) {
		return String(str === undefined || str === null ? '' : str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}
	function slugify(str) {
		return String(str).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
	}
	function fmt(n) {
		n = round1(num(n));
		return n.toLocaleString('en-US', { maximumFractionDigits: 1 });
	}
	/** "2026-09-25" -> "Sep 25, 2026" (falls back to the raw string). */
	function humanDate(ymd) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(ymd || ''));
		if (!m) { return String(ymd || ''); }
		var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		return months[Number(m[2]) - 1] + ' ' + Number(m[3]) + ', ' + m[1];
	}
	/** Formatted value + unit, or an em dash when the value is not published. */
	function fmtU(v, unit) {
		return isKnown(v) ? fmt(v) + unit : '\u2014';
	}
	var LABELS = { calories: 'calories', protein: 'protein', carbs: 'carbohydrates', fat: 'fat', sodium: 'sodium' };
	var UNITS = { calories: '', protein: 'g', carbs: 'g', fat: 'g', sodium: 'mg' };
	/** One plain-language sentence naming what each incomplete total excludes. */
	function describeUnavailable(u) {
		var parts = [];
		NUTRIENTS.forEach(function (k) {
			if (u[k].length) { parts.push(LABELS[k] + ' for ' + u[k].join(', ')); }
		});
		if (!parts.length) { return ''; }
		return 'Totals marked + are minimums: the official source does not publish ' + parts.join('; ') + ', so those values are not counted (they are not assumed to be zero).';
	}

	/* ------------------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------------- */
	function init() {
		var mounts = document.querySelectorAll('.ffnc-calculator[data-brand]');
		Array.prototype.forEach.call(mounts, function (mount) {
			if (mount.getAttribute('data-ffnc-booted')) { return; }
			mount.setAttribute('data-ffnc-booted', '1');
			var brand = mount.getAttribute('data-brand');
			var inline = mount.querySelector('script.ffnc-calc-data');
			if (inline) {
				try {
					renderCalculator(mount, JSON.parse(inline.textContent));
					return;
				} catch (e) {
					if (window.console) { console.error(e); }
				}
			}
			var base = (window.ffncCalcConfig && window.ffncCalcConfig.dataUrl) || '/wp-content/themes/fastfood-nutrition/assets/data/';
			fetch(base + encodeURIComponent(brand) + '.json', { credentials: 'omit' })
				.then(function (res) {
					if (!res.ok) { throw new Error('Nutrition data not found for ' + brand); }
					return res.json();
				})
				.then(function (data) { renderCalculator(mount, data); })
				.catch(function (err) {
					mount.innerHTML = '<p class="ffnc-calc-error" role="alert">Sorry — this calculator could not load its menu data right now. Please refresh the page. The full nutrition table further down this page still works.</p>';
					mount.classList.add('ffnc-calc-ready');
					if (window.console) { console.error(err); }
				});
		});
	}

	function renderCalculator(mount, data) {
		var state = { order: [], activeCategory: data.categories[0] ? data.categories[0].id : null };
		var meta = data.meta || {};
		var DEALS_TAB = '__deals';
		var dealsMeta = data.deals_meta || {};
		var deals = (data.deals || []).map(function (d) {
			return { deal: d, lines: dealLines(d, data) };
		}).filter(function (d) { return d.lines; });

		var brandSlug      = mount.getAttribute('data-brand') || data.slug || 'brand';
		var brandName      = mount.getAttribute('data-brand-name') || data.brand || '';
		var brandColor     = mount.getAttribute('data-brand-color') || '#1d5fd6';
		var brandColorDark = mount.getAttribute('data-brand-color-dark') || brandColor;
		var brandAbbr      = mount.getAttribute('data-brand-abbr') || (brandName ? brandName.charAt(0) : '?');
		var hubUrl         = mount.getAttribute('data-hub-url') || '/calculators/';
		var siteName       = mount.getAttribute('data-site-name') || 'Fast Food Nutrition Calculators';

		mount.innerHTML = '';
		mount.classList.add('ffnc-calc-ready');

		/* ---- Toolbar ---- */
		var toolbar = document.createElement('div');
		toolbar.className = 'ffnc-calc-toolbar';
		toolbar.innerHTML =
			'<a class="ffnc-calc-back" href="' + escapeHtml(hubUrl) + '">' +
			'  <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12.7 4.3a1 1 0 0 1 0 1.4L8.4 10l4.3 4.3a1 1 0 1 1-1.4 1.4l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 0 1 1.4 0z"/></svg>' +
			'  <span>All calculators</span>' +
			'</a>' +
			'<div class="ffnc-calc-toolbar-title">' +
			'  <span class="ffnc-brand-badge ffnc-brand-badge-sm" style="--ffnc-brand-color:' + escapeHtml(brandColor) + ';--ffnc-brand-color-dark:' + escapeHtml(brandColorDark) + '" aria-hidden="true"><span class="ffnc-brand-badge-text">' + escapeHtml(brandAbbr) + '</span></span>' +
			'  <span class="ffnc-calc-toolbar-name">' + escapeHtml(brandName) + ' Calculator</span>' +
			'</div>' +
			'<div class="ffnc-calc-toolbar-actions">' +
			'  <button type="button" class="ffnc-theme-toggle" data-ffnc-theme-toggle aria-label="Toggle dark mode">' +
			'    <svg class="ffnc-icon-sun" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 4.5a1 1 0 0 1-1-1v-1a1 1 0 1 1 2 0v1a1 1 0 0 1-1 1zm0 15a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1zm9-8.5a1 1 0 0 1-1 1h-1a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zM5 12a1 1 0 0 1-1 1H3a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1zm12.36-6.36a1 1 0 0 1 0 1.41l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 0 1 1.41 0zM8.76 17.66a1 1 0 0 1 0 1.41l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 0 1 1.41 0zm9.19 1.41a1 1 0 0 1-1.41 0l-.71-.71a1 1 0 1 1 1.41-1.41l.71.71a1 1 0 0 1 0 1.41zM7.34 6.76a1 1 0 0 1-1.41 0l-.71-.71a1 1 0 0 1 1.41-1.41l.71.71a1 1 0 0 1 0 1.41zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10z"/></svg>' +
			'    <svg class="ffnc-icon-moon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M20.7 14.9A8.8 8.8 0 0 1 9.1 3.3a.9.9 0 0 0-1.1-1.2A10.5 10.5 0 1 0 21.9 16a.9.9 0 0 0-1.2-1.1z"/></svg>' +
			'  </button>' +
			'  <span class="ffnc-calc-total-pill"><strong data-toolbar-total>0</strong> cal</span>' +
			'</div>';
		mount.appendChild(toolbar);

		/* Market / data-status line — visible, honest, links to the source box. */
		if (meta.market_label || meta.status) {
			var metaLine = document.createElement('p');
			metaLine.className = 'ffnc-calc-meta';
			var statusText = (meta.status === 'verified' && meta.last_reviewed)
				? 'Verified against official data \u00b7 ' + escapeHtml(humanDate(meta.last_reviewed))
				: 'Pending \u00b7 reference values, not yet verified against official data';
			metaLine.innerHTML =
				(meta.market_label ? '<span>' + escapeHtml(meta.market_label) + ' menu</span>' : '') +
				'<span>' + statusText + '</span>' +
				'<a href="#ffnc-data-source">Data source &amp; review status</a>';
			mount.appendChild(metaLine);
		}

		var layout = document.createElement('div');
		layout.className = 'ffnc-calc-layout';

		/* ---- Left: category buttons ---- */
		var catSidebar = document.createElement('nav');
		catSidebar.className = 'ffnc-calc-categories';
		catSidebar.setAttribute('aria-label', brandName + ' menu categories');
		var catHeading = document.createElement('p');
		catHeading.className = 'ffnc-calc-categories-heading';
		catHeading.textContent = 'Categories';
		catSidebar.appendChild(catHeading);

		var catList = document.createElement('div');
		catList.className = 'ffnc-calc-category-list';
		var tabs = data.categories.slice();
		if (deals.length) {
			tabs.unshift({ id: DEALS_TAB, name: 'Deals & Combos' });
		}
		tabs.forEach(function (cat) {
			var tab = document.createElement('button');
			tab.type = 'button';
			tab.className = 'ffnc-calc-tab';
			tab.textContent = cat.name;
			tab.dataset.cat = cat.id;
			tab.setAttribute('aria-controls', 'ffnc-items-' + brandSlug);
			tab.addEventListener('click', function () {
				state.activeCategory = cat.id;
				renderItems();
				updateTabs();
			});
			catList.appendChild(tab);
		});
		catSidebar.appendChild(catList);

		/* ---- Middle: items ---- */
		var main = document.createElement('div');
		main.className = 'ffnc-calc-main';
		var itemsWrap = document.createElement('div');
		itemsWrap.className = 'ffnc-calc-items';
		itemsWrap.id = 'ffnc-items-' + brandSlug;
		itemsWrap.setAttribute('aria-live', 'off');
		main.appendChild(itemsWrap);

		/* ---- Right: order summary ---- */
		var sidebar = document.createElement('aside');
		sidebar.className = 'ffnc-calc-sidebar';
		sidebar.id = 'ffnc-order-' + brandSlug;
		sidebar.setAttribute('aria-label', 'Your order');
		sidebar.tabIndex = -1;
		sidebar.innerHTML =
			'<p class="ffnc-calc-sidebar-title"><span>Your Order</span><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M7 4h-2a1 1 0 1 0 0 2h1.22l1.7 8.53A2 2 0 0 0 9.87 16h7.26a2 2 0 0 0 1.95-1.55l1.34-6A1 1 0 0 0 19.46 7H7.42l-.2-1A2 2 0 0 0 7 4zM9 20a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm8 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/></svg></p>' +
			'<div class="ffnc-paste">' +
			'  <button type="button" class="ffnc-paste-toggle" aria-expanded="false">Paste an order from an app or receipt</button>' +
			'  <div class="ffnc-paste-panel" hidden>' +
			'    <label class="screen-reader-text" for="ffnc-paste-' + escapeHtml(brandSlug) + '">Paste your order</label>' +
			'    <textarea id="ffnc-paste-' + escapeHtml(brandSlug) + '" rows="4" placeholder="2x Big Mac&#10;Medium Fries&#10;10 pc McNuggets"></textarea>' +
			'    <button type="button" class="ffnc-btn ffnc-btn-solid ffnc-paste-go">Find items</button>' +
			'    <div class="ffnc-paste-result" aria-live="polite"></div>' +
			'  </div>' +
			'</div>' +
			'<ul class="ffnc-order-list"></ul>' +
			'<p class="ffnc-order-empty">No items selected. Tap <strong>Add</strong> on any menu item.</p>' +
			'<div class="ffnc-order-totals" hidden>' +
			'  <p class="ffnc-order-totals-label">Calculated total for this order</p>' +
			'  <div class="ffnc-total-row ffnc-total-cal"><span>Calories</span><strong data-total="calories">0</strong></div>' +
			'  <div class="ffnc-total-row"><span>Protein</span><strong data-total="protein">0g</strong></div>' +
			'  <div class="ffnc-total-row"><span>Carbs</span><strong data-total="carbs">0g</strong></div>' +
			'  <div class="ffnc-total-row"><span>Fat</span><strong data-total="fat">0g</strong></div>' +
			'  <div class="ffnc-total-row"><span>Sodium</span><strong data-total="sodium">0mg</strong></div>' +
			'  <p class="ffnc-order-addon-note" hidden></p>' +
			'</div>' +
			'<div class="ffnc-order-tools" hidden>' +
			'  <div class="ffnc-tool-row"><label>Sharing? Split between <input type="number" class="ffnc-split" min="1" max="20" value="1" inputmode="numeric"> people</label><p class="ffnc-split-out" hidden></p></div>' +
			'  <div class="ffnc-tool-row"><label>Paid $<input type="number" class="ffnc-price" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></label><p class="ffnc-price-out" hidden></p></div>' +
			'  <div class="ffnc-tool-row"><label>Burn it off at <select class="ffnc-weight"><option value="125">125 lb</option><option value="155" selected>155 lb</option><option value="185">185 lb</option></select></label><p class="ffnc-burn-out"></p></div>' +
			'  <button type="button" class="ffnc-btn ffnc-share">Copy link to this order</button>' +
			'</div>' +
			'<div class="ffnc-order-actions">' +
			'  <button type="button" class="ffnc-btn ffnc-btn-ghost ffnc-order-clear" hidden>Clear order</button>' +
			'  <button type="button" class="ffnc-btn ffnc-btn-ghost ffnc-order-export" hidden>' +
			'    <svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M10 3a1 1 0 0 1 1 1v7.59l2.3-2.3a1 1 0 1 1 1.4 1.42l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.42l2.3 2.3V4a1 1 0 0 1 1-1zM4 15a1 1 0 0 1 1 1v1h10v-1a1 1 0 1 1 2 0v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a1 1 0 0 1 1-1z"/></svg>' +
			'    <span>Export Report</span>' +
			'  </button>' +
			'</div>';

		layout.appendChild(catSidebar);
		layout.appendChild(main);
		layout.appendChild(sidebar);
		mount.appendChild(layout);

		/* Screen-reader status for add/remove/quantity changes. */
		var live = document.createElement('p');
		live.className = 'screen-reader-text';
		live.setAttribute('role', 'status');
		live.setAttribute('aria-live', 'polite');
		mount.appendChild(live);

		/* Mobile bottom bar: on small screens the order summary sits below
		   the menu, so show the running total + a jump link. */
		var mobileBar = document.createElement('div');
		mobileBar.className = 'ffnc-calc-mobilebar';
		mobileBar.hidden = true;
		mobileBar.innerHTML = '<span><strong data-mobile-total>0</strong> cal · <span data-mobile-count>0</span> item(s)</span>' +
			'<button type="button" class="ffnc-btn ffnc-btn-primary ffnc-calc-mobilebar-btn">View order</button>';
		mount.appendChild(mobileBar);
		/* Hide the bar while the order summary itself is on screen, so it
		   never covers the summary's own buttons. */
		var sidebarVisible = false;
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				sidebarVisible = entries[0].isIntersecting;
				mobileBar.classList.toggle('is-covered', sidebarVisible);
			}, { threshold: 0.05 }).observe(sidebar);
		}
		mobileBar.querySelector('button').addEventListener('click', function () {
			sidebar.scrollIntoView({ behavior: 'smooth', block: 'start' });
			sidebar.focus({ preventScroll: true });
		});

		if (data.note) {
			var note = document.createElement('p');
			note.className = 'ffnc-calc-note';
			note.textContent = data.note;
			mount.appendChild(note);
		}

		var toolbarTotalEl = toolbar.querySelector('[data-toolbar-total]');
		var orderListEl = sidebar.querySelector('.ffnc-order-list');
		var orderEmptyEl = sidebar.querySelector('.ffnc-order-empty');
		var orderTotalsEl = sidebar.querySelector('.ffnc-order-totals');
		var addonNoteEl = sidebar.querySelector('.ffnc-order-addon-note');
		var clearBtn = sidebar.querySelector('.ffnc-order-clear');
		var exportBtn = sidebar.querySelector('.ffnc-order-export');

		var toolsEl = sidebar.querySelector('.ffnc-order-tools');
		var splitIn = sidebar.querySelector('.ffnc-split');
		var splitOut = sidebar.querySelector('.ffnc-split-out');
		var priceIn = sidebar.querySelector('.ffnc-price');
		var priceOut = sidebar.querySelector('.ffnc-price-out');
		var weightIn = sidebar.querySelector('.ffnc-weight');
		var burnOut = sidebar.querySelector('.ffnc-burn-out');
		var shareBtn = sidebar.querySelector('.ffnc-share');
		var pasteToggle = sidebar.querySelector('.ffnc-paste-toggle');
		var pastePanel = sidebar.querySelector('.ffnc-paste-panel');
		var pasteResult = sidebar.querySelector('.ffnc-paste-result');
		[splitIn, priceIn, weightIn].forEach(function (el) {
			el.addEventListener('input', renderTools);
			el.addEventListener('change', renderTools);
		});
		shareBtn.addEventListener('click', function () {
			var url = window.location.href.split('#')[0].split('?')[0] + '?o=' + encodeURIComponent(encodeOrder(state.order));
			var done = function () { shareBtn.textContent = 'Link copied'; announce('Link to this order copied.'); setTimeout(function () { shareBtn.textContent = 'Copy link to this order'; }, 2500); };
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); });
			} else {
				window.prompt('Copy this link:', url);
			}
		});
		pasteToggle.addEventListener('click', function () {
			pastePanel.hidden = !pastePanel.hidden;
			pasteToggle.setAttribute('aria-expanded', pastePanel.hidden ? 'false' : 'true');
			if (!pastePanel.hidden) { pastePanel.querySelector('textarea').focus(); }
		});
		sidebar.querySelector('.ffnc-paste-go').addEventListener('click', function () {
			var res = parseCart(pastePanel.querySelector('textarea').value, data);
			pasteResult.innerHTML = '';
			if (!res.matched.length) {
				pasteResult.textContent = 'No ' + brandName + ' items found. Put one item per line.';
				return;
			}
			var ul = document.createElement('ul');
			ul.className = 'ffnc-paste-list';
			res.matched.forEach(function (m) {
				var li = document.createElement('li');
				li.textContent = (m.qty > 1 ? m.qty + ' \u00d7 ' : '') + m.item.name + ' (' + fmt(m.item.calories * m.qty) + ' cal)';
				ul.appendChild(li);
			});
			pasteResult.appendChild(ul);
			if (res.unmatched.length) {
				var miss = document.createElement('p');
				miss.className = 'ffnc-paste-miss';
				miss.textContent = 'Not found: ' + res.unmatched.join(', ') + '. Add these from the menu if they are on it.';
				pasteResult.appendChild(miss);
			}
			var add = document.createElement('button');
			add.type = 'button';
			add.className = 'ffnc-btn ffnc-btn-solid';
			add.textContent = 'Add ' + res.matched.length + ' item(s) to order';
			add.addEventListener('click', function () {
				res.matched.forEach(function (m) { addLine(m.item, [], m.qty); });
				renderOrder();
				pasteResult.innerHTML = '';
				pastePanel.querySelector('textarea').value = '';
				announce('Added pasted items. Order total ' + fmt(computeTotals(state.order).calories) + ' calories.');
			});
			pasteResult.appendChild(add);
		});

		function addLine(item, addons, qty) {
			var key = lineKey(item, addons);
			var existing = null;
			state.order.forEach(function (e) { if (e.key === key) { existing = e; } });
			if (existing) {
				existing.qty = clampQty(existing.qty + qty);
			} else {
				state.order.push({ key: key, item: item, addons: addons, qty: clampQty(qty) });
			}
		}

		function renderTools() {
			var empty = !state.order.length;
			toolsEl.hidden = empty;
			if (empty) { return; }
			var t = computeTotals(state.order);
			var n = Math.max(1, Math.min(20, Math.floor(num(splitIn.value)) || 1));
			splitOut.hidden = n < 2;
			if (n > 1) {
				splitOut.textContent = 'Each person: ' + fmt(t.calories / n) + ' cal, ' + fmt(t.protein / n) + 'g protein, ' +
					fmt(t.carbs / n) + 'g carbs, ' + fmt(t.fat / n) + 'g fat, ' + fmt(t.sodium / n) + 'mg sodium.';
			}
			var price = num(priceIn.value);
			priceOut.hidden = !(price > 0);
			if (price > 0) {
				priceOut.textContent = fmt(t.protein / price) + 'g protein and ' + fmt(t.calories / price) + ' calories per $1.';
			}
			var mins = exerciseMinutes(t.calories, weightIn.value);
			burnOut.textContent = 'About ' + fmt(mins.walk) + ' min walking (3.5 mph), ' + fmt(mins.run) + ' min running (5 mph) or ' +
				fmt(mins.bike) + ' min cycling (12\u201314 mph). Source: Harvard Health.';
		}

		clearBtn.addEventListener('click', function () {
			state.order = [];
			renderOrder();
			announce('Order cleared.');
		});
		exportBtn.addEventListener('click', exportReport);

		function announce(msg) {
			live.textContent = '';
			window.setTimeout(function () { live.textContent = msg; }, 30);
		}

		function updateTabs() {
			Array.prototype.forEach.call(catList.querySelectorAll('.ffnc-calc-tab'), function (t) {
				var active = t.dataset.cat === state.activeCategory;
				t.classList.toggle('is-active', active);
				t.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
		}

		function findCategory(id) {
			for (var i = 0; i < data.categories.length; i++) {
				if (data.categories[i].id === id) { return data.categories[i]; }
			}
			return data.categories[0];
		}

		function renderDeals() {
			itemsWrap.innerHTML = '';
			var heading = document.createElement('p');
			heading.className = 'ffnc-calc-category-heading';
			heading.textContent = brandName + ' Deals & Combos';
			itemsWrap.appendChild(heading);

			var intro = document.createElement('p');
			intro.className = 'ffnc-deals-note';
			intro.innerHTML = escapeHtml(dealsMeta.note || '') +
				(dealsMeta.checked ? ' Deal contents checked ' + escapeHtml(humanDate(dealsMeta.checked)) + '.' : '') +
				(dealsMeta.source_url ? ' <a href="' + escapeHtml(dealsMeta.source_url) + '" target="_blank" rel="noopener nofollow">' + escapeHtml(dealsMeta.source_name || 'Source') + '</a>' : '');
			itemsWrap.appendChild(intro);

			deals.forEach(function (d) {
				var t = computeTotals(d.lines);
				var card = document.createElement('div');
				card.className = 'ffnc-item-card ffnc-deal-card';
				card.id = 'ffnc-deal-' + brandSlug + '-' + slugify(d.deal.id);

				var info = document.createElement('div');
				info.className = 'ffnc-item-info';
				info.innerHTML =
					'<p class="ffnc-item-name">' + escapeHtml(d.deal.name) + '</p>' +
					'<p class="ffnc-deal-includes">' + escapeHtml(d.deal.includes || d.lines.map(function (l) { return (l.qty > 1 ? l.qty + ' \u00d7 ' : '') + l.item.name; }).join(', ')) + '</p>' +
					'<div class="ffnc-item-pills">' +
					'  <span class="ffnc-pill ffnc-pill-cal">' + fmt(t.calories) + ' cal</span>' +
					'  <span class="ffnc-pill"><abbr title="Protein">P</abbr> ' + fmt(t.protein) + 'g</span>' +
					'  <span class="ffnc-pill"><abbr title="Carbohydrates">C</abbr> ' + fmt(t.carbs) + 'g</span>' +
					'  <span class="ffnc-pill"><abbr title="Fat">F</abbr> ' + fmt(t.fat) + 'g</span>' +
					'  <span class="ffnc-pill">' + fmt(t.sodium) + 'mg sodium</span>' +
					'</div>';

				var addBtn = document.createElement('button');
				addBtn.type = 'button';
				addBtn.className = 'ffnc-btn ffnc-btn-add';
				addBtn.setAttribute('aria-label', 'Add ' + d.deal.name + ' to order');
				addBtn.innerHTML = '<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1z"/></svg><span>Add deal</span>';
				addBtn.addEventListener('click', function () { addDeal(d); });

				var top = document.createElement('div');
				top.className = 'ffnc-item-top';
				top.appendChild(info);
				top.appendChild(addBtn);
				card.appendChild(top);
				itemsWrap.appendChild(card);
			});
		}

		/* Adds every item of a deal as normal order lines, so each one can
		   still be removed or changed in the order summary. */
		function addDeal(d) {
			d.lines.forEach(function (l) { addLine(l.item, [], l.qty); });
			renderOrder();
			announce('Added ' + d.deal.name + '. Order total ' + fmt(computeTotals(state.order).calories) + ' calories.');
		}

		function renderItems() {
			if (state.activeCategory === DEALS_TAB) {
				renderDeals();
				return;
			}
			var cat = findCategory(state.activeCategory);
			itemsWrap.innerHTML = '';
			var heading = document.createElement('p');
			heading.className = 'ffnc-calc-category-heading';
			heading.textContent = cat.name;
			itemsWrap.appendChild(heading);

			cat.items.forEach(function (item) {
				var card = document.createElement('div');
				card.className = 'ffnc-item-card';

				var info = document.createElement('div');
				info.className = 'ffnc-item-info';
				info.innerHTML =
					'<p class="ffnc-item-name">' + escapeHtml(item.name) + '</p>' +
					'<div class="ffnc-item-pills">' +
					'  <span class="ffnc-pill ffnc-pill-cal">' + fmtU(item.calories, ' cal') + '</span>' +
					'  <span class="ffnc-pill"><abbr title="Protein">P</abbr> ' + fmtU(item.protein, 'g') + '</span>' +
					'  <span class="ffnc-pill"><abbr title="Carbohydrates">C</abbr> ' + fmtU(item.carbs, 'g') + '</span>' +
					'  <span class="ffnc-pill"><abbr title="Fat">F</abbr> ' + fmtU(item.fat, 'g') + '</span>' +
					'  <span class="ffnc-pill">' + fmtU(item.sodium, 'mg') + ' sodium</span>' +
					'</div>';

				var addBtn = document.createElement('button');
				addBtn.type = 'button';
				addBtn.className = 'ffnc-btn ffnc-btn-add';
				addBtn.setAttribute('aria-label', 'Add ' + item.name + ' to order');
				addBtn.innerHTML = '<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1z"/></svg><span>Add</span>';

				var checkedAddons = [];
				addBtn.addEventListener('click', function () {
					addToOrder(item, checkedAddons.slice());
				});

				var top = document.createElement('div');
				top.className = 'ffnc-item-top';
				top.appendChild(info);
				top.appendChild(addBtn);
				card.appendChild(top);

				if (item.addons && item.addons.length) {
					var addonsWrap = document.createElement('fieldset');
					addonsWrap.className = 'ffnc-item-addons';
					var label = document.createElement('legend');
					label.className = 'ffnc-addons-label';
					label.textContent = 'Optional add-ons:';
					addonsWrap.appendChild(label);

					var chipRow = document.createElement('div');
					chipRow.className = 'ffnc-addon-chip-row';
					item.addons.forEach(function (addon) {
						var chipId = 'ffnc-addon-' + brandSlug + '-' + slugify(item.id) + '-' + slugify(addon.name);
						var chipLabel = document.createElement('label');
						chipLabel.className = 'ffnc-addon-chip';
						chipLabel.setAttribute('for', chipId);
						var chipInput = document.createElement('input');
						chipInput.type = 'checkbox';
						chipInput.id = chipId;
						chipInput.addEventListener('change', function () {
							if (chipInput.checked) {
								checkedAddons.push(addon);
							} else {
								checkedAddons = checkedAddons.filter(function (a) { return a !== addon; });
							}
						});
						chipLabel.appendChild(chipInput);
						var cal = num(addon.calories);
						chipLabel.appendChild(document.createTextNode(' ' + addon.name + (cal ? ' (+' + fmt(cal) + ' cal)' : '')));
						if (!isNoChangeAddon(addon)) {
							chipLabel.title = 'Adds ' + fmtU(addon.calories, ' cal') + ', ' + fmtU(addon.protein, 'g') + ' protein, ' +
								fmtU(addon.carbs, 'g') + ' carbs, ' + fmtU(addon.fat, 'g') + ' fat, ' + fmtU(addon.sodium, 'mg') + ' sodium' +
								(hasCaloriesOnlyAddons([{ addons: [addon] }]) ? ' (\u2014 = not published by the restaurant)' : '');
						}
						chipRow.appendChild(chipLabel);
					});
					addonsWrap.appendChild(chipRow);
					card.appendChild(addonsWrap);
				}

				itemsWrap.appendChild(card);
			});
		}

		function addToOrder(item, addons) {
			var key = lineKey(item, addons);
			var existing = null;
			state.order.forEach(function (e) { if (e.key === key) { existing = e; } });
			if (existing) {
				if (existing.qty >= MAX_QTY) {
					announce('Maximum quantity of ' + MAX_QTY + ' reached for ' + item.name + '.');
					return;
				}
				existing.qty += 1;
			} else {
				state.order.push({ key: key, item: item, addons: addons, qty: 1 });
			}
			renderOrder();
			announce('Added ' + item.name + '. Order total ' + fmt(computeTotals(state.order).calories) + ' calories.');
		}

		function setQty(key, qty) {
			qty = clampQty(qty);
			state.order = state.order.filter(function (e) {
				if (e.key !== key) { return true; }
				e.qty = qty;
				return qty > 0;
			});
			renderOrder();
			announce('Order total ' + fmt(computeTotals(state.order).calories) + ' calories.');
		}

		function renderOrder() {
			orderListEl.innerHTML = '';
			var empty = !state.order.length;
			orderEmptyEl.hidden = !empty;
			orderTotalsEl.hidden = empty;
			clearBtn.hidden = empty;
			exportBtn.hidden = empty;
			mobileBar.hidden = empty;

			var totals = computeTotals(state.order);
			var count = 0;

			state.order.forEach(function (entry) {
				count += entry.qty;
				var lt = lineTotals(entry);
				var addonText = entry.addons.length ? (' + ' + entry.addons.map(function (a) { return a.name; }).join(', ')) : '';

				var li = document.createElement('li');
				li.className = 'ffnc-order-line';
				li.innerHTML =
					'<span class="ffnc-order-line-name">' + escapeHtml(entry.item.name) + escapeHtml(addonText) + '</span>' +
					'<span class="ffnc-order-line-cal">' + fmt(lt.calories) + ' cal</span>';

				var qtyWrap = document.createElement('span');
				qtyWrap.className = 'ffnc-qty';
				var minus = document.createElement('button');
				minus.type = 'button';
				minus.className = 'ffnc-qty-btn';
				minus.textContent = '−';
				minus.setAttribute('aria-label', 'Decrease quantity of ' + entry.item.name);
				minus.addEventListener('click', function () { setQty(entry.key, entry.qty - 1); });
				var qtyVal = document.createElement('span');
				qtyVal.className = 'ffnc-qty-val';
				qtyVal.textContent = entry.qty;
				qtyVal.setAttribute('aria-label', 'Quantity ' + entry.qty);
				var plus = document.createElement('button');
				plus.type = 'button';
				plus.className = 'ffnc-qty-btn';
				plus.textContent = '+';
				plus.disabled = entry.qty >= MAX_QTY;
				plus.setAttribute('aria-label', 'Increase quantity of ' + entry.item.name);
				plus.addEventListener('click', function () { setQty(entry.key, entry.qty + 1); });
				qtyWrap.appendChild(minus);
				qtyWrap.appendChild(qtyVal);
				qtyWrap.appendChild(plus);
				li.appendChild(qtyWrap);

				var removeBtn = document.createElement('button');
				removeBtn.type = 'button';
				removeBtn.className = 'ffnc-order-remove';
				removeBtn.setAttribute('aria-label', 'Remove ' + entry.item.name);
				removeBtn.textContent = '×';
				removeBtn.addEventListener('click', function () { setQty(entry.key, 0); });
				li.appendChild(removeBtn);

				var swap = entry.addons.length ? null : findSwap(entry.item, data);
				if (swap && swap.saving > 0) {
					var sw = document.createElement('p');
					sw.className = 'ffnc-swap';
					sw.appendChild(document.createTextNode('Lighter swap: ' + swap.item.name + ' (\u2212' + fmt(swap.saving) + ' cal) '));
					var swBtn = document.createElement('button');
					swBtn.type = 'button';
					swBtn.className = 'ffnc-swap-btn';
					swBtn.textContent = 'Swap';
					swBtn.setAttribute('aria-label', 'Swap ' + entry.item.name + ' for ' + swap.item.name);
					swBtn.addEventListener('click', function () {
						var at = state.order.indexOf(entry);
						var key = lineKey(swap.item, []);
						var merged = false;
						state.order.forEach(function (e) {
							if (e.key === key) { e.qty = clampQty(e.qty + entry.qty); merged = true; }
						});
						if (merged) {
							state.order.splice(at, 1);
						} else {
							state.order[at] = { key: key, item: swap.item, addons: [], qty: entry.qty };
						}
						renderOrder();
						announce('Swapped to ' + swap.item.name + '. Order total ' + fmt(computeTotals(state.order).calories) + ' calories.');
					});
					sw.appendChild(swBtn);
					li.appendChild(sw);
				}
				orderListEl.appendChild(li);
			});

			var unavailable = unavailableNutrients(state.order);
			NUTRIENTS.forEach(function (k) {
				var el = orderTotalsEl.querySelector('[data-total="' + k + '"]');
				var partial = unavailable[k].length > 0;
				el.textContent = fmt(totals[k]) + UNITS[k] + (partial ? '+' : '');
				if (partial) {
					el.setAttribute('title', 'At least ' + fmt(totals[k]) + UNITS[k] + ' \u2014 excludes ' + unavailable[k].join(', '));
				} else {
					el.removeAttribute('title');
				}
			});
			var unavailableNote = describeUnavailable(unavailable);
			addonNoteEl.textContent = unavailableNote;
			addonNoteEl.hidden = !unavailableNote;
			toolbarTotalEl.textContent = fmt(totals.calories);
			mobileBar.querySelector('[data-mobile-total]').textContent = fmt(totals.calories);
			mobileBar.querySelector('[data-mobile-count]').textContent = count;
			renderTools();
		}

		/**
		 * "Export Report" — opens a standalone printable HTML page with the
		 * order, per-line nutrition and totals.
		 */
		function buildReportHtml() {
			var totals = computeTotals(state.order);
			var unavailable = unavailableNutrients(state.order);
			var rows = state.order.map(function (entry) {
				var lt = lineTotals(entry);
				var addonText = entry.addons.length ? entry.addons.map(function (a) { return a.name; }).join(', ') : '—';
				return '<tr>' +
					'<td>' + escapeHtml(entry.item.name) + '</td>' +
					'<td>' + escapeHtml(addonText) + '</td>' +
					'<td>' + entry.qty + '</td>' +
					'<td>' + fmt(lt.calories) + '</td>' +
					'<td>' + fmt(lt.protein) + 'g</td>' +
					'<td>' + fmt(lt.carbs) + 'g</td>' +
					'<td>' + fmt(lt.fat) + 'g</td>' +
					'<td>' + fmt(lt.sodium) + 'mg</td>' +
					'</tr>';
			}).join('');

			var dateStr = new Date().toLocaleString();
			var title = brandName + ' Order Report';
			var provenance = (meta.market_label ? meta.market_label + ' menu. ' : '') +
				(meta.status === 'verified' && meta.last_reviewed ? 'Verified against official information on ' + humanDate(meta.last_reviewed) + '. ' : 'Pending: reference values not yet verified against official information. ') +
				(meta.source_url ? 'Official source: ' + meta.source_url : '');

			return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">' +
				'<meta name="viewport" content="width=device-width, initial-scale=1">' +
				'<meta name="robots" content="noindex">' +
				'<title>' + escapeHtml(title) + '</title>' +
				'<style>' +
				'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;max-width:720px;margin:24px auto;padding:0 16px 40px;color:#1a1f2b;}' +
				'h1{font-size:1.35rem;margin:0 0 2px;}' +
				'.m{color:#667085;font-size:0.85rem;margin-bottom:18px;}' +
				'.t{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:0.85rem;}' +
				'th,td{text-align:left;padding:8px 6px;border-bottom:1px solid #e2e5ec;}' +
				'th{color:#667085;font-weight:700;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.02em;}' +
				'tfoot td{font-weight:800;border-top:2px solid #1a1f2b;border-bottom:none;}' +
				'.d{margin-top:24px;font-size:0.8rem;color:#667085;line-height:1.5;}' +
				'.p{margin:18px 0;padding:10px 20px;border-radius:8px;border:none;background:#1d5fd6;color:#fff;font-weight:700;font-size:0.9rem;cursor:pointer;}' +
				'@media print{.p{display:none;}}' +
				'</style></head><body>' +
				'<h1>' + escapeHtml(title) + '</h1>' +
				'<p class="m">Generated ' + escapeHtml(dateStr) + '</p>' +
				'<button type="button" class="p" onclick="window.print()">Print / Save as PDF</button>' +
				'<div class="t"><table><thead><tr><th>Item</th><th>Add-ons</th><th>Qty</th><th>Cal</th><th>Protein</th><th>Carbs</th><th>Fat</th><th>Sodium</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
				'<tfoot><tr><td colspan="3">Calculated total</td>' + NUTRIENTS.map(function (k) {
					return '<td>' + fmt(totals[k]) + UNITS[k] + (unavailable[k].length ? '+' : '') + '</td>';
				}).join('') + '</tr></tfoot>' +
				'</table></div>' +
				(hasUnavailableValues(state.order) ? '<p class="d">' + escapeHtml(describeUnavailable(unavailable)) + '</p>' : '') +
				'<p class="d">' + escapeHtml(provenance) + '</p>' +
				'<p class="d">Generated by ' + escapeHtml(siteName) + ', an independent resource not affiliated with, endorsed by, or sponsored by ' + escapeHtml(brandName) + '. Figures are for general information only and are not medical or dietary advice.</p>' +
				'</body></html>';
		}

		function exportReport() {
			if (!state.order.length) { return; }
			var html = buildReportHtml();
			try {
				var blob = new Blob([html], { type: 'text/html' });
				var url = URL.createObjectURL(blob);
				var win = window.open(url, '_blank');
				if (!win) { throw new Error('popup blocked'); }
				setTimeout(function () { URL.revokeObjectURL(url); }, 20000);
			} catch (e) {
				var win2 = window.open('', '_blank');
				if (win2) {
					win2.document.open();
					win2.document.write(html);
					win2.document.close();
				} else {
					announce('Please allow pop-ups to view your order report.');
				}
			}
		}

		/* ?deal=<id> (from the deals table on the page) opens the Deals tab
		   with that deal already in the order. */
		var wantedDeal = null;
		try {
			wantedDeal = new URLSearchParams(window.location.search).get('deal');
		} catch (e) { /* old browser: ignore */ }
		var sharedOrder = null;
		try {
			sharedOrder = new URLSearchParams(window.location.search).get('o');
		} catch (e2) { /* ignore */ }
		if (sharedOrder) {
			state.order = decodeOrder(sharedOrder, data);
		}
		if (wantedDeal) {
			deals.forEach(function (d) {
				if (d.deal.id === wantedDeal) {
					state.activeCategory = DEALS_TAB;
					addDeal(d);
				}
			});
		}

		renderItems();
		updateTabs();
		renderOrder();
	}

	/* The shortcode calls this from an inline script right after the
	   calculator's markup (the engine loads in <head> on calculator pages),
	   so the calculator is built before the first paint instead of popping
	   in after the rest of the page and pushing it down. */
	window.FFNCCalcBoot = init;
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
