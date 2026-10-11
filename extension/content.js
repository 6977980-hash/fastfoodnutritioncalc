/**
 * Fast Food Nutrition Lookup: on a delivery app's restaurant page, finds menu
 * item names that match our data for that chain and adds a small
 * "580 cal · 25g protein" badge linking to the full calculator.
 *
 * Nothing is sent anywhere: matching runs locally on bundled data (data.js)
 * with the site's own name matcher (calculator.js -> window.FFNCCalc).
 */
(function () {
	'use strict';

	var C = window.FFNCCalc;
	var DATA = window.FFNC_EXT_DATA;
	if (!C || !DATA) { return; }

	var MARK = 'data-ffnc-ext';
	var brand = null;

	function detectBrand() {
		var heading = document.querySelector('h1');
		var text = ((heading && heading.textContent) || '') + ' ' + document.title;
		text = text.toLowerCase().replace(/’/g, "'");
		for (var i = 0; i < DATA.brands.length; i++) {
			var b = DATA.brands[i];
			for (var j = 0; j < b.aliases.length; j++) {
				if (text.indexOf(b.aliases[j]) >= 0) { return b; }
			}
		}
		return null;
	}

	function bestItem(text) {
		var best = null;
		var bestScore = 0;
		C.allItems(brand).forEach(function (x) {
			var s = C.nameScore(text, x.item.name);
			if (s > bestScore) { bestScore = s; best = x.item; }
		});
		return bestScore >= 0.75 ? best : null;
	}

	function badge(item) {
		var a = document.createElement('a');
		a.className = 'ffnc-ext-badge';
		a.href = brand.url + '?o=' + encodeURIComponent(item.id);
		a.target = '_blank';
		a.rel = 'noopener';
		a.title = item.name + ': ' + item.calories + ' cal, ' + item.protein + 'g protein, ' + item.carbs + 'g carbs, ' +
			item.fat + 'g fat, ' + item.sodium + 'mg sodium (official U.S. values). Open the full calculator.';
		a.textContent = item.calories + ' cal · ' + item.protein + 'g protein';
		a.addEventListener('click', function (e) { e.stopPropagation(); });
		return a;
	}

	function scan() {
		if (!brand) { brand = detectBrand(); }
		if (!brand) { return; }
		// Menu item names are short text-only elements (no child elements).
		var nodes = document.querySelectorAll('h3, h4, span, div, p');
		for (var i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			if (n.hasAttribute(MARK) || n.children.length) { continue; }
			var text = (n.textContent || '').trim();
			if (text.length < 3 || text.length > 70 || /\$\d/.test(text)) { continue; }
			n.setAttribute(MARK, '1');
			var item = bestItem(text);
			if (item) { n.appendChild(badge(item)); }
		}
	}

	var timer = null;
	new MutationObserver(function () {
		clearTimeout(timer);
		timer = setTimeout(scan, 600);
	}).observe(document.body, { childList: true, subtree: true });
	scan();
})();
