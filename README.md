# fastfoodnutritioncalc

The WordPress theme for [fastfoodnutritioncalc.com](https://fastfoodnutritioncalc.com/)
("Fast Food Nutrition Calculators"). The repo root is the theme folder.

- Nutrition data: `assets/data/<brand>.json`
- Calculator engine: `assets/js/calculator.js` (tests: `node --test tests/calculator.test.js`)
- Page prose fixes: `inc/content-updates.json`, applied from WordPress > Tools > FFNC Content Updates

## Where things live

| What | Path |
| --- | --- |
| Repo root | https://github.com/6977980-hash/fastfoodnutritioncalc |
| Site root on Hostinger | `public_html/` |
| This repo on Hostinger | `public_html/wp-content/themes/<theme folder>/` |

## Deploy

Hostinger hPanel > Advanced > Git deploys `main` into the theme folder, with
Auto Deployment turned on (webhook in GitHub > Settings > Webhooks). Every
push to `main` goes live.
