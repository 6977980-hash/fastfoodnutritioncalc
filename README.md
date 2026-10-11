# fastfoodnutritioncalc

Code for [fastfoodnutritioncalc.com](https://fastfoodnutritioncalc.com/) (WordPress on Hostinger).

- `theme/` — the "Fast Food Nutrition Calculators" theme
  - nutrition data: `theme/assets/data/<brand>.json`
  - calculator engine: `theme/assets/js/calculator.js` (tests: `node --test theme/tests/calculator.test.js`)
  - page text fixes: `theme/inc/content-updates.json`, applied from WordPress > Tools > FFNC Content Updates
  - deals: optional `deals` + `deals_meta` in a brand JSON add a "Deals & Combos" calculator tab and a deals table on that brand page
  - cross-restaurant tools and data pages: `theme/inc/tools.php` + `theme/assets/js/tools.js` (pages are created once, published, when an editor opens wp-admin)
  - item types for tools/goal lists: run `node scripts/build-item-types.js` after changing any dataset (a test checks it)
- `ffnc-site-fixes.php` — small plugin that copies `theme/` into the live theme folder

## Where things live

| What | Path |
| --- | --- |
| Repo root | https://github.com/6977980-hash/fastfoodnutritioncalc |
| Site root on Hostinger | `public_html/` |
| This repo on Hostinger | `public_html/wp-content/plugins/ffnc-site-fixes/` |
| Live theme | `public_html/wp-content/themes/fastfood-nutrition/` |

## Deploy

Hostinger hPanel > Advanced > Git deploys `main` into the plugin folder (Auto
Deployment webhook in GitHub). On the next page load the plugin copies changed
files from `theme/` into the live theme. Keep **FFNC Site Fixes** active.
