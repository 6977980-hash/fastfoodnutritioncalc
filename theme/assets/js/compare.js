/**
 * Compare Menu Items — reads the datasets inlined by template-compare.php
 * (the same JSON files the calculators use).
 */
(function () {
	'use strict';

	var ROWS = [
		{ key: 'cal', label: 'Calories', unit: '', better: 'low' },
		{ key: 'p', label: 'Protein', unit: 'g', better: 'high' },
		{ key: 'cb', label: 'Carbohydrates', unit: 'g', better: null },
		{ key: 'f', label: 'Fat', unit: 'g', better: null },
		{ key: 's', label: 'Sodium', unit: 'mg', better: 'low' }
	];

	function esc(s) {
		return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}
	function known(v) {
		return v !== null && v !== undefined && v !== '' && isFinite(Number(v));
	}
	function fmt(n) {
		return (Math.round(Number(n) * 10) / 10).toLocaleString('en-US', { maximumFractionDigits: 1 });
	}

	function init(root) {
		var dataEl = root.querySelector('.ffnc-compare-data');
		if (!dataEl) { return; }
		var data;
		try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
		var result = root.querySelector('.ffnc-compare-result');

		function itemSelect(i) { return root.querySelector('[data-cmp-item="' + i + '"]'); }
		function brandSelect(i) { return root.querySelector('[data-cmp-brand="' + i + '"]'); }

		function fillItems(i) {
			var slug = brandSelect(i).value;
			var sel = itemSelect(i);
			sel.innerHTML = '';
			if (!slug || !data[slug]) {
				sel.disabled = true;
				sel.innerHTML = '<option value="">Choose a restaurant first</option>';
				return;
			}
			var html = '<option value="">Choose an item…</option>';
			var lastCat = null;
			data[slug].items.forEach(function (it, idx) {
				if (it.c !== lastCat) {
					if (lastCat !== null) { html += '</optgroup>'; }
					html += '<optgroup label="' + esc(it.c) + '">';
					lastCat = it.c;
				}
				html += '<option value="' + idx + '">' + esc(it.n) + ' (' + fmt(it.cal) + ' cal)</option>';
			});
			html += '</optgroup>';
			sel.innerHTML = html;
			sel.disabled = false;
		}

		function selected() {
			var out = [];
			for (var i = 1; i <= 3; i++) {
				var slug = brandSelect(i).value;
				var idx = itemSelect(i).value;
				if (slug && idx !== '' && data[slug] && data[slug].items[idx]) {
					out.push({ brand: data[slug], item: data[slug].items[idx] });
				}
			}
			return out;
		}

		function render() {
			var picks = selected();
			if (picks.length < 2) {
				result.innerHTML = '<p class="ffnc-compare-empty">Choose at least two items to see the comparison.</p>';
				return;
			}
			var head = '<tr><th scope="col">Per serving</th>' + picks.map(function (p) {
				return '<th scope="col"><a href="' + esc(p.brand.url) + '">' + esc(p.brand.name) + '</a><br><span>' + esc(p.item.n) + '</span></th>';
			}).join('') + '</tr>';

			var body = ROWS.map(function (r) {
				// A value the official source does not publish (null) is shown as a
				// dash and left out of the row's lowest/highest highlight.
				var vals = picks.map(function (p) { return known(p.item[r.key]) ? Number(p.item[r.key]) : null; });
				var nums = vals.filter(function (v) { return v !== null; });
				var best = null;
				if (nums.length > 1 && r.better === 'low') { best = Math.min.apply(null, nums); }
				if (nums.length > 1 && r.better === 'high') { best = Math.max.apply(null, nums); }
				var allSame = nums.every(function (v) { return v === nums[0]; });
				return '<tr><th scope="row">' + r.label + '</th>' + vals.map(function (v) {
					if (v === null) { return '<td>\u2014</td>'; }
					var hit = best !== null && !allSame && v === best;
					return '<td' + (hit ? ' class="ffnc-cmp-best"' : '') + '>' + fmt(v) + r.unit + (hit ? ' <span class="screen-reader-text">(' + (r.better === 'low' ? 'lowest' : 'highest') + ' in row)</span>' : '') + '</td>';
				}).join('') + '</tr>';
			}).join('');

			var ppc = '<tr><th scope="row">Protein per 100 cal</th>' + picks.map(function (p) {
				return '<td>' + (known(p.item.p) && p.item.cal > 0 ? fmt(p.item.p / p.item.cal * 100) + 'g' : '—') + '</td>';
			}).join('') + '</tr>';

			var pending = picks.some(function (p) { return !p.brand.verified; });
			var cals = picks.map(function (p) { return p.item.cal; });
			var diff = Math.max.apply(null, cals) - Math.min.apply(null, cals);

			result.innerHTML =
				'<p class="ffnc-compare-summary">Calorie difference between the highest and lowest item: <strong>' + fmt(diff) + ' calories</strong>.</p>' +
				'<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="Comparison table (scrollable)"><table class="ffnc-nutrition-table ffnc-compare-table"><caption class="screen-reader-text">Nutrition comparison</caption><thead>' + head + '</thead><tbody>' + body + ppc + '</tbody></table></div>' +
				'<p class="ffnc-table-note">United States menus, one standard serving each.' + (pending ? ' At least one of these restaurants’ datasets is not yet verified against official data — treat values as estimates.' : '') + '</p>';
		}

		for (var i = 1; i <= 3; i++) {
			(function (n) {
				brandSelect(n).addEventListener('change', function () { fillItems(n); render(); });
				itemSelect(n).addEventListener('change', render);
			})(i);
		}

		// Optional preselect from ?a=brand (links from calculator pages).
		try {
			var pre = new URLSearchParams(window.location.search).get('a');
			if (pre && data[pre]) {
				brandSelect(1).value = pre;
				fillItems(1);
			}
		} catch (e) { /* old browsers: no preselect */ }
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ffnc-compare]'), init);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
