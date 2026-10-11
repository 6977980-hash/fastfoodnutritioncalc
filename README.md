# fastfoodnutritioncalc

Code for [fastfoodnutritioncalc.com](https://fastfoodnutritioncalc.com/) (WordPress on Hostinger).

The site itself (pages, calculators, menus) lives in WordPress on Hostinger.
This repo holds the `ffnc-site-fixes` plugin, which GitHub deploys to the site
automatically.

## Where things live

| What | Path |
| --- | --- |
| Repo root | https://github.com/6977980-hash/fastfoodnutritioncalc |
| Site root on Hostinger | `public_html/` (full path `/home/<your u-number>/domains/fastfoodnutritioncalc.com/public_html`) |
| Plugin on Hostinger | `public_html/wp-content/plugins/ffnc-site-fixes/` |

## Auto-deploy to Hostinger

Every push to `main` that changes `ffnc-site-fixes/` runs
`.github/workflows/deploy-hostinger.yml`, which uploads that folder over FTP.
Nothing else on the server is touched.

One-time setup, in GitHub: **Settings > Secrets and variables > Actions > New repository secret**

| Secret | Value (from hPanel > Files > FTP Accounts) |
| --- | --- |
| `FTP_SERVER` | FTP host / IP |
| `FTP_USERNAME` | FTP username |
| `FTP_PASSWORD` | FTP password |

Optional repository **variables**: `FTP_SERVER_DIR` (if your FTP login opens
inside `public_html`, set it to `wp-content/plugins/ffnc-site-fixes/`) and
`FTP_PROTOCOL` (`ftp` if FTPS is refused).

After the first deploy, activate **FFNC Site Fixes** once in
WordPress > Plugins. You can re-run a deploy any time from
**Actions > Deploy to Hostinger > Run workflow**.
