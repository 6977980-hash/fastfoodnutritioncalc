/**
 * Site-wide interactions: mobile menu toggle, nav dropdowns, dark/light
 * theme toggle, FAQ accordions, and the header "Choose a brand" search.
 */
(function () {
	'use strict';

	/* ---------------------------------------------------------------
	 * Mobile hamburger menu
	 * ------------------------------------------------------------ */
	function initMenu() {
		var toggle = document.querySelector('.ffnc-menu-toggle');
		var nav = document.getElementById('ffnc-primary-menu');
		if (!toggle || !nav) { return; }
		toggle.addEventListener('click', function () {
			var expanded = toggle.getAttribute('aria-expanded') === 'true';
			toggle.setAttribute('aria-expanded', String(!expanded));
			nav.classList.toggle('is-open');
		});
	}

	/* ---------------------------------------------------------------
	 * "Calculators" / "Brands" dropdowns in the primary nav
	 * ------------------------------------------------------------ */
	function initDropdowns() {
		var toggles = document.querySelectorAll('.ffnc-menu-dropdown-toggle');
		if (!toggles.length) { return; }

		function closeAll(except) {
			toggles.forEach(function (t) {
				if (t === except) { return; }
				t.setAttribute('aria-expanded', 'false');
				t.parentElement.classList.remove('is-open');
			});
		}

		toggles.forEach(function (toggle) {
			toggle.addEventListener('click', function (e) {
				e.stopPropagation();
				var item = toggle.parentElement;
				var willOpen = !item.classList.contains('is-open');
				closeAll();
				item.classList.toggle('is-open', willOpen);
				toggle.setAttribute('aria-expanded', String(willOpen));
			});
		});

		document.addEventListener('click', function () { closeAll(); });
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { closeAll(); }
		});
	}

	/* ---------------------------------------------------------------
	 * Dark / light theme toggle (persisted in localStorage, applied via
	 * a data attribute on <html> — see the inline snippet in header.php
	 * that sets this before first paint to avoid a flash).
	 * ------------------------------------------------------------ */
	function initTheme() {
		function getTheme() {
			return document.documentElement.getAttribute('data-ffnc-theme') === 'dark' ? 'dark' : 'light';
		}
		function syncButtons() {
			var dark = getTheme() === 'dark';
			Array.prototype.forEach.call(document.querySelectorAll('[data-ffnc-theme-toggle]'), function (b) {
				b.setAttribute('aria-pressed', dark ? 'true' : 'false');
			});
		}
		function setTheme(theme) {
			document.documentElement.setAttribute('data-ffnc-theme', theme);
			try { localStorage.setItem('ffncTheme', theme); } catch (e) {}
			syncButtons();
		}
		syncButtons();

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-ffnc-theme-toggle]');
			if (!btn) { return; }
			setTheme(getTheme() === 'dark' ? 'light' : 'dark');
		});
	}

	/* ---------------------------------------------------------------
	 * FAQ accordions
	 * ------------------------------------------------------------ */
	function initFaq() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.ffnc-faq-toggle');
			if (!btn) { return; }
			var expanded = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', String(!expanded));
			var panel = document.getElementById(btn.getAttribute('aria-controls'));
			if (panel) { panel.classList.toggle('is-open', !expanded); }
		});
	}

	/* ---------------------------------------------------------------
	 * Header "Choose a brand" live search
	 * ------------------------------------------------------------ */
	function initBrandSearch() {
		var input = document.querySelector('[data-ffnc-brand-search]');
		var results = document.querySelector('[data-ffnc-search-results]');
		if (!input || !results) { return; }

		var index = { b: [], i: [] };
		try {
			var el = document.getElementById('ffnc-search-index');
			if (el) { index = JSON.parse(el.textContent); }
			else if (input.getAttribute('data-brands')) { index.b = JSON.parse(input.getAttribute('data-brands')); }
		} catch (e) { /* keep empty index */ }

		var active = -1;

		function esc(s) {
			return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
		}
		function badge(b) {
			return '<span class="ffnc-brand-badge ffnc-brand-badge-sm" style="--ffnc-brand-color:' + esc(b.color) + ';--ffnc-brand-color-dark:' + esc(b.colorDark || b.color) + '" aria-hidden="true"><span class="ffnc-brand-badge-text">' + esc(b.abbr || b.name.charAt(0)) + '</span></span>';
		}
		function options() { return results.querySelectorAll('.ffnc-search-result'); }
		function setActive(n) {
			var opts = options();
			if (!opts.length) { return; }
			active = (n + opts.length) % opts.length;
			Array.prototype.forEach.call(opts, function (o, i) { o.setAttribute('aria-selected', i === active ? 'true' : 'false'); });
			input.setAttribute('aria-activedescendant', opts[active].id);
		}
		function close() {
			results.hidden = true;
			results.innerHTML = '';
			input.setAttribute('aria-expanded', 'false');
			input.removeAttribute('aria-activedescendant');
			active = -1;
		}

		function render(q) {
			if (!q) { close(); return; }
			var brandHits = index.b.filter(function (b) { return b.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 5);
			var itemHits = (q.length >= 2 ? index.i : []).filter(function (it) { return it[0].toLowerCase().indexOf(q) !== -1; }).slice(0, 8);
			if (!brandHits.length && !itemHits.length) {
				results.innerHTML = '<p class="ffnc-search-none">No matching restaurant or item.</p>';
				results.hidden = false;
				input.setAttribute('aria-expanded', 'true');
				return;
			}
			var n = 0;
			var html = brandHits.map(function (b) {
				return '<a class="ffnc-search-result" role="option" id="ffnc-sr-' + (n++) + '" href="' + esc(b.url) + '">' + badge(b) + '<span>' + esc(b.name) + ' calculator</span></a>';
			}).join('');
			html += itemHits.map(function (it) {
				var b = index.b[it[1]];
				if (!b) { return ''; }
				return '<a class="ffnc-search-result" role="option" id="ffnc-sr-' + (n++) + '" href="' + esc(b.url) + '#' + esc(it[2]) + '">' + badge(b) + '<span>' + esc(it[0]) + ' <small>' + esc(b.name) + ' · ' + esc(it[3]) + ' cal</small></span></a>';
			}).join('');
			results.innerHTML = html;
			results.hidden = false;
			input.setAttribute('aria-expanded', 'true');
			active = -1;
		}

		input.addEventListener('input', function () { render(input.value.trim().toLowerCase()); });

		input.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
			else if (e.key === 'Enter') {
				var opts = options();
				var target = opts[active >= 0 ? active : 0];
				if (target) {
					e.preventDefault();
					window.location.href = target.getAttribute('href');
				}
			} else if (e.key === 'Escape') {
				close();
			}
		});

		document.addEventListener('click', function (e) {
			if (!e.target.closest('.ffnc-header-search')) { close(); }
		});
	}

	/* ---------------------------------------------------------------
	 * Keep in-page jump targets (e.g. "How It Works") below the sticky
	 * header and the admin bar, which otherwise cover their headings.
	 * ------------------------------------------------------------ */
	function initScrollOffset() {
		var header = document.querySelector('.ffnc-site-header');
		if (!header) { return; }
		function update() {
			var pos = getComputedStyle(header).position;
			var offset = (pos === 'sticky' || pos === 'fixed') ? header.offsetHeight : 0;
			var adminBar = document.getElementById('wpadminbar');
			if (adminBar && getComputedStyle(adminBar).position === 'fixed') {
				offset += adminBar.offsetHeight;
			}
			document.documentElement.style.scrollPaddingTop = offset ? (offset + 16) + 'px' : '';
		}
		update();
		window.addEventListener('resize', update);
	}

	function initAll() {
		initMenu();
		initDropdowns();
		initTheme();
		initFaq();
		initBrandSearch();
		initScrollOffset();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
