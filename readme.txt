=== FFNC Site Fixes ===
Requires at least: 6.3
Stable tag: 2.0.1
License: GPLv2 or later

Keeps the Fast Food Nutrition Calculators theme in sync with GitHub.

Hostinger's Git deployment puts this repo in wp-content/plugins/ffnc-site-fixes/.
Within 5 minutes of a deploy (right away on any wp-admin page), the plugin
copies every changed file from theme/ into the active theme (only if it is
the Fast Food Nutrition Calculators theme). It never deletes theme files. Errors show as an admin
notice. Keep the plugin active.

The .htaccess in this folder blocks direct browser requests to its files.
