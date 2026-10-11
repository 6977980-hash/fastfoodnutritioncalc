=== FFNC Site Fixes ===
Requires at least: 6.3
Stable tag: 2.0.0
License: GPLv2 or later

Keeps the Fast Food Nutrition Calculators theme in sync with GitHub.

Hostinger's Git deployment puts this repo in wp-content/plugins/ffnc-site-fixes/.
On the next page load after a deploy, the plugin copies every changed file
from theme/ into the active theme (only if it is the Fast Food Nutrition
Calculators theme). It never deletes theme files. Errors show as an admin
notice. Keep the plugin active.
