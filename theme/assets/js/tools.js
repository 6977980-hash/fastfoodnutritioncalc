/**
 * Cross-restaurant tools: Order Ideas (reverse calculator), Day Planner and
 * Same Craving (closest item at other chains).
 *
 * Each mount is <div class="ffnc-tool" data-tool="..."> with the compact
 * datasets of every live brand inlined as <script type="application/json">
 * (see inc/tools.php). All maths comes from the calculator engine
 * (window.FFNCCalc, assets/js/calculator.js), so totals always match the
 * brand calculators.
 */
(function () {
	'use strict';

	var C = window.FFNCCalc;
	if (!C || typeof document === 'undefined') {
		return;
	}

	function esc(str) {
		return String(str === undefined || str === null ? '' : str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}
	function fmt(n) {
		n = Math.round(Number(n || 0) * 10) / 10;
		return n.toLocaleString('en-US', { maximumFractionDigits: 1 });
	}
	function el(tag, cls, html) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (html !== undefined) { e.innerHTML = html; }
		return e;
	}
	function pills(t) {
		return '<span class="ffnc-pill ffnc-pill-cal">' + fmt(t.calories) + ' cal</span>' +
			'<span class="ffnc-pill"><abbr title="Protein">P</abbr> ' + fmt(t.protein) + 'g</span>' +
			'<span class="ffnc-pill"><abbr title="Carbohydrates">C</abbr> ' + fmt(t.carbs) + 'g</span>' +
			'<span class="ffnc-pill"><abbr title="Fat">F</abbr> ' + fmt(t.fat) + 'g</span>' +
			'<span class="ffnc-pill">' + fmt(t.sodium) + 'mg sodium</span>';
	}
	function orderUrl(brand, lines) {
		return brand.url + '?o=' + encodeURIComponent(C.encodeOrder(lines));
	}
	function brandOptions(brands, withAny) {
		return (withAny ? '<option value="">Any restaurant</option>' : '') + brands.map(function (b) {
			return '<option value="' + esc(b.slug) + '">' + esc(b.name) + '</option>';
		}).join('');
	}
	function itemOptions(brand) {
		return brand.categories.map(function (c) {
			return '<optgroup label="' + esc(c.name) + '">' + c.items.map(function (i) {
				return '<option value="' + esc(i.id) + '">' + esc(i.name) + ' (' + fmt(i.calories) + ' cal)</option>';
			}).join('') + '</optgroup>';
		}).join('');
	}
	function findBrand(brands, slug) {
		for (var i = 0; i < brands.length; i++) {
			if (brands[i].slug === slug) { return brands[i]; }
		}
		return null;
	}
	function qs(name) {
		try { return new URLSearchParams(window.location.search).get(name); } catch (e) { return null; }
	}

	/* ------------------------------------------------------------------
	 * Order Ideas: "I have N calories left and want P g protein"
	 * ---------------------------------------------------------------- */
	function orderIdeas(mount, cfg) {
		var brands = cfg.brands.filter(function (b) { return b.slug !== 'chipotle'; });
		var glp1 = mount.getAttribute('data-mode') === 'small-portion';
		var preBrand = mount.getAttribute('data-brand') || qs('brand') || '';
		mount.innerHTML =
			'<form class="ffnc-tool-form">' +
			'  <label>Restaurant <select name="brand">' + brandOptions(brands, true) + '</select></label>' +
			'  <label>Calories to spend <input name="calories" type="number" min="150" max="2000" step="10" value="' + (glp1 ? 450 : 600) + '" inputmode="numeric"></label>' +
			'  <label>At least this much protein (g) <input name="protein" type="number" min="0" max="150" value="' + (glp1 ? 20 : 30) + '" inputmode="numeric"></label>' +
			'  <label>Max carbs (g, optional) <input name="maxCarbs" type="number" min="0" max="300" inputmode="numeric"></label>' +
			'  <label>Max sodium (mg, optional) <input name="maxSodium" type="number" min="0" max="5000" step="50" inputmode="numeric"></label>' +
			'  <label class="ffnc-check"><input name="small" type="checkbox"' + (glp1 ? ' checked' : '') + '> Small appetite: at most 2 items, most protein per calorie first</label>' +
			'  <button type="submit" class="ffnc-btn ffnc-btn-solid">Show me orders</button>' +
			'</form>' +
			'<div class="ffnc-tool-results" aria-live="polite"></div>';
		var form = mount.querySelector('form');
		var out = mount.querySelector('.ffnc-tool-results');
		if (preBrand) { form.brand.value = preBrand; }

		function run(e) {
			if (e) { e.preventDefault(); }
			var small = form.small.checked;
			var opts = {
				calories: Number(form.calories.value) || 600,
				protein: Number(form.protein.value) || 0,
				maxCarbs: form.maxCarbs.value === '' ? null : Number(form.maxCarbs.value),
				maxSodium: form.maxSodium.value === '' ? null : Number(form.maxSodium.value),
				maxItems: small ? 2 : 3,
				sort: small ? 'density' : 'protein'
			};
			var list = [];
			var pick = form.brand.value ? [findBrand(brands, form.brand.value)] : brands;
			pick.forEach(function (b) {
				opts.limit = form.brand.value ? 6 : 1;
				C.bestOrders(b, opts).forEach(function (o) { list.push({ brand: b, order: o }); });
			});
			list.sort(function (x, y) {
				if (small) {
					return (y.order.totals.protein / y.order.totals.calories) - (x.order.totals.protein / x.order.totals.calories);
				}
				return y.order.totals.protein - x.order.totals.protein;
			});
			list = list.slice(0, 6);
			out.innerHTML = '';
			if (!list.length) {
				out.appendChild(el('p', 'ffnc-tool-empty', 'No order fits all of that. Try more calories or less protein.'));
				return;
			}
			list.forEach(function (r, i) {
				var lines = C.groupLines(r.order.items);
				var card = el('div', 'ffnc-item-card ffnc-idea');
				card.innerHTML =
					'<p class="ffnc-idea-rank">Option ' + (i + 1) + ' · ' + esc(r.brand.name) + '</p>' +
					'<p class="ffnc-item-name">' + lines.map(function (l) { return (l.qty > 1 ? l.qty + ' \u00d7 ' : '') + esc(l.item.name); }).join(' + ') + '</p>' +
					'<div class="ffnc-item-pills">' + pills(r.order.totals) + '</div>' +
					'<a class="ffnc-idea-open" href="' + esc(orderUrl(r.brand, lines)) + '">Open in the ' + esc(r.brand.name) + ' calculator</a>';
				out.appendChild(card);
			});
			out.appendChild(el('p', 'ffnc-tool-note', 'Orders use each restaurant’s published values for standard items (no drinks). Change anything in the calculator.'));
		}
		form.addEventListener('submit', run);
		run();
	}

	/* ------------------------------------------------------------------
	 * Day Planner: meals from different chains, day total vs target
	 * ---------------------------------------------------------------- */
	var MEALS = [['b', 'Breakfast'], ['l', 'Lunch'], ['d', 'Dinner'], ['s', 'Snacks']];
	var STORE = 'ffnc-day-plan';

	function encodeDay(day) {
		var parts = [];
		MEALS.forEach(function (m) {
			(day[m[0]] || []).forEach(function (l) {
				parts.push(m[0] + '.' + l.brand.slug + '.' + l.item.id + (l.qty > 1 ? '*' + l.qty : ''));
			});
		});
		return parts.join(',');
	}
	function decodeDay(str, brands) {
		var day = { b: [], l: [], d: [], s: [] };
		String(str || '').split(',').forEach(function (p) {
			var m = /^([blds])\.([a-z0-9-]+)\.([a-z0-9-]+)(?:\*(\d+))?$/.exec(p);
			if (!m) { return; }
			var brand = findBrand(brands, m[2]);
			var item = brand && C.itemById(brand, m[3]);
			if (item) { day[m[1]].push({ brand: brand, item: item, addons: [], qty: C.clampQty(m[4] ? Number(m[4]) : 1) || 1 }); }
		});
		return day;
	}

	function dayPlanner(mount, cfg) {
		var brands = cfg.brands;
		var day = { b: [], l: [], d: [], s: [] };
		var target = 2000;
		var fromUrl = qs('d');
		try {
			if (fromUrl) {
				day = decodeDay(fromUrl, brands);
			} else if (window.localStorage) {
				var saved = JSON.parse(window.localStorage.getItem(STORE) || 'null');
				if (saved) { day = decodeDay(saved.d, brands); target = Number(saved.t) || 2000; }
			}
		} catch (e) { /* storage blocked: start empty */ }
		if (qs('t')) { target = Number(qs('t')) || target; }

		mount.innerHTML =
			'<div class="ffnc-day-target"><label>My daily target <input type="number" min="800" max="5000" step="50" value="' + target + '" inputmode="numeric"> calories</label>' +
			(cfg.calorieGuideUrl ? ' <a href="' + esc(cfg.calorieGuideUrl) + '">Not sure? Work out your number</a>' : '') + '</div>' +
			'<div class="ffnc-day-meals"></div>' +
			'<div class="ffnc-day-total" aria-live="polite"></div>' +
			'<p><button type="button" class="ffnc-btn ffnc-share">Copy link to this day</button> <button type="button" class="ffnc-btn ffnc-share ffnc-day-clear">Clear day</button></p>';
		var mealsEl = mount.querySelector('.ffnc-day-meals');
		var totalEl = mount.querySelector('.ffnc-day-total');
		var targetIn = mount.querySelector('.ffnc-day-target input');

		function save() {
			try { window.localStorage.setItem(STORE, JSON.stringify({ d: encodeDay(day), t: target })); } catch (e) { /* ignore */ }
		}
		function allLines() {
			var out = [];
			MEALS.forEach(function (m) { out = out.concat(day[m[0]]); });
			return out;
		}
		function renderTotal() {
			var t = C.computeTotals(allLines());
			var left = target - t.calories;
			totalEl.innerHTML = '<p class="ffnc-day-sum"><strong>' + fmt(t.calories) + '</strong> of ' + fmt(target) + ' calories · ' +
				(left >= 0 ? fmt(left) + ' left' : fmt(-left) + ' over') + '</p>' +
				'<div class="ffnc-item-pills">' + pills(t) + '</div>' +
				'<div class="ffnc-day-bar" role="img" aria-label="' + fmt(t.calories) + ' of ' + fmt(target) + ' calories"><span style="width:' + Math.min(100, Math.round(t.calories / target * 100)) + '%"' + (left < 0 ? ' class="is-over"' : '') + '></span></div>';
		}
		function renderMeals() {
			mealsEl.innerHTML = '';
			MEALS.forEach(function (m) {
				var box = el('section', 'ffnc-day-meal');
				var lines = day[m[0]];
				var mt = C.computeTotals(lines);
				box.innerHTML = '<h3>' + esc(m[1]) + ' <span>' + fmt(mt.calories) + ' cal</span></h3>';
				var ul = el('ul', 'ffnc-day-lines');
				lines.forEach(function (l, idx) {
					var li = el('li', '', esc(l.brand.name) + ': ' + esc(l.item.name) + (l.qty > 1 ? ' × ' + l.qty : '') + ' <span>' + fmt(l.item.calories * l.qty) + ' cal</span> ');
					var rm = el('button', 'ffnc-order-remove', '×');
					rm.type = 'button';
					rm.setAttribute('aria-label', 'Remove ' + l.item.name);
					rm.addEventListener('click', function () { lines.splice(idx, 1); update(); });
					li.appendChild(rm);
					ul.appendChild(li);
				});
				box.appendChild(ul);
				var add = el('div', 'ffnc-day-add');
				add.innerHTML = '<label class="screen-reader-text">Restaurant for ' + esc(m[1]) + '</label><select class="ffnc-day-brand">' + brandOptions(brands, false) + '</select>' +
					'<label class="screen-reader-text">Item</label><select class="ffnc-day-item"></select>' +
					'<button type="button" class="ffnc-btn ffnc-btn-solid">Add</button>' +
					'<details class="ffnc-day-paste"><summary>Paste an order instead</summary><textarea rows="3" placeholder="One item per line"></textarea><button type="button" class="ffnc-btn ffnc-share">Add pasted items</button><p class="ffnc-paste-miss"></p></details>';
				var bSel = add.querySelector('.ffnc-day-brand');
				var iSel = add.querySelector('.ffnc-day-item');
				var lastBrand = lines.length ? lines[lines.length - 1].brand.slug : brands[0].slug;
				bSel.value = lastBrand;
				var fill = function () { iSel.innerHTML = itemOptions(findBrand(brands, bSel.value)); };
				fill();
				bSel.addEventListener('change', fill);
				add.querySelector('.ffnc-btn-solid').addEventListener('click', function () {
					var brand = findBrand(brands, bSel.value);
					var item = C.itemById(brand, iSel.value);
					if (!item) { return; }
					var same = null;
					lines.forEach(function (l) { if (l.brand === brand && l.item === item) { same = l; } });
					if (same) { same.qty = C.clampQty(same.qty + 1); } else { lines.push({ brand: brand, item: item, addons: [], qty: 1 }); }
					update();
				});
				add.querySelector('.ffnc-day-paste .ffnc-share').addEventListener('click', function () {
					var brand = findBrand(brands, bSel.value);
					var res = C.parseCart(add.querySelector('textarea').value, brand);
					res.matched.forEach(function (r) { lines.push({ brand: brand, item: r.item, addons: [], qty: r.qty }); });
					var miss = add.querySelector('.ffnc-paste-miss');
					if (res.unmatched.length || !res.matched.length) {
						miss.textContent = (res.matched.length ? '' : 'No ' + brand.name + ' items found. ') + (res.unmatched.length ? 'Not found: ' + res.unmatched.join(', ') : '');
					}
					if (res.matched.length) { update(); }
				});
				box.appendChild(add);
				mealsEl.appendChild(box);
			});
		}
		function update() {
			renderMeals();
			renderTotal();
			save();
		}
		targetIn.addEventListener('input', function () {
			target = Math.max(800, Number(targetIn.value) || 2000);
			renderTotal();
			save();
		});
		mount.querySelector('.ffnc-share').addEventListener('click', function () {
			var url = window.location.href.split('?')[0].split('#')[0] + '?d=' + encodeURIComponent(encodeDay(day)) + '&t=' + target;
			var btn = this;
			var done = function () { btn.textContent = 'Link copied'; setTimeout(function () { btn.textContent = 'Copy link to this day'; }, 2500); };
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); });
			} else {
				window.prompt('Copy this link:', url);
			}
		});
		mount.querySelector('.ffnc-day-clear').addEventListener('click', function () {
			day = { b: [], l: [], d: [], s: [] };
			update();
		});
		update();
	}

	/* ------------------------------------------------------------------
	 * Same Craving: the closest item of the same kind at every other chain
	 * ---------------------------------------------------------------- */
	var TYPE_LABEL = {
		burger: 'burgers', 'chicken-sandwich': 'chicken & fish sandwiches', nuggets: 'nuggets & tenders', fries: 'fries & rings',
		pizza: 'pizza', sub: 'subs', mexican: 'tacos & burritos', 'chicken-piece': 'fried & grilled chicken', wings: 'wings',
		breakfast: 'breakfast', shake: 'shakes & frozen treats', dessert: 'desserts & sweets', drink: 'drinks', coffee: 'coffee',
		salad: 'salads', soup: 'soups', entree: 'entrees', side: 'sides', other: 'items'
	};

	function sameCraving(mount, cfg) {
		var brands = cfg.brands.filter(function (b) { return b.slug !== 'chipotle'; });
		mount.innerHTML =
			'<form class="ffnc-tool-form">' +
			'  <label>I’m craving something like <select name="brand">' + brandOptions(brands, false) + '</select></label>' +
			'  <label class="screen-reader-text">Item</label><select name="item"></select>' +
			'</form>' +
			'<div class="ffnc-tool-results" aria-live="polite"></div>';
		var form = mount.querySelector('form');
		var out = mount.querySelector('.ffnc-tool-results');
		var fill = function () { form.item.innerHTML = itemOptions(findBrand(brands, form.brand.value)); };
		var pre = qs('brand');
		if (pre && findBrand(brands, pre)) { form.brand.value = pre; }
		fill();
		if (qs('item')) { form.item.value = qs('item'); }

		function typed(b) {
			return C.allItems(b).map(function (x) { return { item: x.item, type: C.itemType(x.item, x.catName, b.slug) }; });
		}
		function run() {
			var brand = findBrand(brands, form.brand.value);
			var src = null;
			typed(brand).forEach(function (x) { if (x.item.id === form.item.value) { src = x; } });
			if (!src) { return; }
			var rows = [];
			brands.forEach(function (b) {
				if (b === brand) { return; }
				var same = typed(b).filter(function (x) { return x.type === src.type && !/family/i.test(x.item.name); });
				if (!same.length) { return; }
				var closest = same.reduce(function (p, q) {
					return Math.abs(q.item.calories - src.item.calories) < Math.abs(p.item.calories - src.item.calories) ? q : p;
				});
				var lightest = same.reduce(function (p, q) { return q.item.calories < p.item.calories ? q : p; });
				rows.push({ brand: b, closest: closest.item, lightest: lightest.item });
			});
			rows.sort(function (x, y) { return x.closest.calories - y.closest.calories; });
			var diff = function (it) {
				var d = it.calories - src.item.calories;
				return d === 0 ? 'same calories' : (d < 0 ? '−' + fmt(-d) : '+' + fmt(d)) + ' cal';
			};
			var link = function (b, it) {
				return '<a href="' + esc(orderUrl(b, [{ item: it, addons: [], qty: 1 }])) + '">' + esc(it.name) + '</a>';
			};
			out.innerHTML = '<p class="ffnc-tool-note">' + esc(src.item.name) + ' at ' + esc(brand.name) + ': ' + fmt(src.item.calories) + ' cal, ' +
				fmt(src.item.protein) + 'g protein. Other chains’ ' + esc(TYPE_LABEL[src.type] || 'items') + ':</p>' +
				(rows.length ? '<div class="ffnc-table-scroll" tabindex="0"><table class="ffnc-nutrition-table"><thead><tr><th scope="col">Restaurant</th><th scope="col">Closest match</th><th scope="col">Calories</th><th scope="col">Protein</th><th scope="col">Lightest of that kind</th></tr></thead><tbody>' +
				rows.map(function (r) {
					return '<tr><th scope="row">' + esc(r.brand.name) + '</th><td>' + link(r.brand, r.closest) + ' <span class="ffnc-muted">(' + diff(r.closest) + ')</span></td><td>' + fmt(r.closest.calories) + '</td><td>' + fmt(r.closest.protein) + 'g</td><td>' +
						(r.lightest === r.closest ? 'same' : link(r.brand, r.lightest) + ' (' + fmt(r.lightest.calories) + ' cal)') + '</td></tr>';
				}).join('') + '</tbody></table></div>' : '<p class="ffnc-tool-empty">No other chain in our list sells this kind of item.</p>');
		}
		form.brand.addEventListener('change', function () { fill(); run(); });
		form.item.addEventListener('change', run);
		run();
	}

	var TOOLS = { 'order-ideas': orderIdeas, 'day-planner': dayPlanner, 'same-craving': sameCraving };

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.ffnc-tool[data-tool]'), function (mount) {
			var fn = TOOLS[mount.getAttribute('data-tool')];
			var dataEl = mount.querySelector('script.ffnc-tool-data');
			if (!fn || !dataEl) { return; }
			try {
				fn(mount, JSON.parse(dataEl.textContent));
				mount.classList.add('ffnc-tool-ready');
			} catch (e) {
				if (window.console) { console.error(e); }
			}
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.FFNCTools = { encodeDay: encodeDay, decodeDay: decodeDay };
})();
