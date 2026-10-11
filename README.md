# fastfoodnutritioncalc

Code for [fastfoodnutritioncalc.com](https://fastfoodnutritioncalc.com/) (WordPress on Hostinger).

The site itself (pages, calculators, menus) lives in WordPress. This repo is
the **FFNC Site Fixes** WordPress plugin: the repo root is the plugin folder,
and Hostinger deploys it straight from GitHub.

## Where things live

| What | Path |
| --- | --- |
| Repo root | https://github.com/6977980-hash/fastfoodnutritioncalc |
| Site root on Hostinger | `public_html/` |
| This repo on Hostinger | `public_html/wp-content/plugins/ffnc-site-fixes/` |

## Hostinger Git auto-deploy (one-time setup)

1. hPanel > Websites > fastfoodnutritioncalc.com > Advanced > **Git**.
2. Create a new repository:
   - Repository: `https://github.com/6977980-hash/fastfoodnutritioncalc.git`
   - Branch: `main`
   - Install path: `wp-content/plugins/ffnc-site-fixes`
     (this folder must not exist yet; Hostinger needs an empty target)
3. Click **Deploy** once, then turn on **Auto Deployment** and copy the
   webhook URL it shows.
4. GitHub repo > Settings > Webhooks > Add webhook: paste that URL as the
   Payload URL, content type `application/json`, event "Just the push event".
5. WordPress > Plugins > activate **FFNC Site Fixes**.

From then on every push to `main` deploys to the live site.
