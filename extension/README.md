# Fast Food Nutrition Lookup (browser extension)

Shows official calories and protein from fastfoodnutritioncalc.com next to
menu items on DoorDash, Uber Eats and Grubhub restaurant pages, with a link to
the full calculator for that chain. Matching runs locally; the extension sends
no data anywhere and needs no permissions beyond the three sites.

## Try it (Chrome, Edge, Brave)

1. Open `chrome://extensions` and turn on **Developer mode**.
2. Click **Load unpacked** and pick this `extension` folder.
3. Open a McDonald's, Taco Bell, Subway… page on DoorDash, Uber Eats or Grubhub.

## Update the data

After any change to `theme/assets/data/*.json` (or the calculator engine), run:

    node scripts/build-extension.js

and bump `version` in `manifest.json` before publishing a new build.

## Publish to the Chrome Web Store

Needs the site owner's Google account: register at
https://chrome.google.com/webstore/devconsole (one-time USD 5 fee), zip the
contents of this folder, upload it, and use the store listing text from
`manifest.json`. Review usually takes a few days.
