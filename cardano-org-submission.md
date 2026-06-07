# Submitting Skulliance to cardano.org

Reference for adding Skulliance to the Cardano app showcase.
Instructions source: <https://cardano.org/docs/get-involved/add-app/>

- **Target repo:** `cardano-foundation/cardano-org`
- **Branch to PR into:** `staging`
- **File to edit:** `src/data/apps.js` (append to the end of the `Showcases` array)

---

## 1. The entry

Paste at the **end of the `Showcases` array** in `src/data/apps.js`
(keep the trailing comma; make sure the previous entry also ends with a comma):

```javascript
{
  title: "Skulliance",
  description: "Cardano NFT staking platform: play free Match 3 RPG games, embark on missions, establish a realm and engage in raids, survive gauntlets, and earn rewards for participating.",
  tagline: "Cardano NFT Staking, Missions, Raids & Match 3 RPG Games",
  preview: require("./app-screenshots/skulliance.webp"),
  icon: "/img/app-icons/skulliance.png",
  website: "https://www.skulliance.io/",
  source: "https://github.com/oculusorbus/skulliance",
  category: "game",
  properties: ["nft", "opensource"],
  maintainerPick: false,
  beginnerFriendly: false,
  x: "skulliance",
},
```

### Field length checks (verified against cardano.org limits)

| Field       | Length | Limit     | Status |
|-------------|--------|-----------|--------|
| title       | 10     | ≤ 25      | OK     |
| description | 172    | 120–180   | OK     |
| tagline     | 56     | ≤ 60      | OK     |

Notes:
- `source` is set because the Skulliance repo is **public** → cardano.org requires
  `properties` to include `"opensource"` whenever `source` is a URL.
- `category: "game"` (exactly one allowed). Valid values: analytics, bridge, dex,
  distribution, ecosystem, education, explorer, game, governance, identity, lending,
  marketplace, minting, notary, pooltool, wallet, other.
- `maintainerPick` / `beginnerFriendly` are left `false` — maintainers set these.
- Optional: add `"mobile"` to `properties` (the games run on mobile browsers).

---

## 2. Image assets

### Icon → `static/img/app-icons/skulliance.png`
Use the existing repo asset `pwa/icon-512.png` (512×512, square — meets the ≥128px,
square requirement). Copy and rename it to `skulliance.png`.

### Screenshot → `src/data/app-screenshots/skulliance.webp`
Requirements: WebP, 16:9, real app UI, **no browser chrome**, **≤ 500 KB**
(light theme preferred but not required — the dark UI is fine).

You already host screenshots at `staking/images/screenshots/` (e.g. `dashboard.png`,
`realms.png`, `store.png`). Convert one:

```bash
brew install webp          # once
cwebp -q 80 -resize 2048 1440 dashboard.png -o skulliance.webp
ls -lh skulliance.webp     # confirm under 500KB
```

Dashboard or Realms makes the strongest first impression.

---

## 3. Open the PR

```bash
# 1. Fork cardano-foundation/cardano-org on GitHub (web "Fork" button)

# 2. Clone your fork and branch off staging
git clone https://github.com/<your-username>/cardano-org.git
cd cardano-org
git checkout staging
git checkout -b add-skulliance

# 3. Add the two assets
cp /path/to/skulliance.webp src/data/app-screenshots/skulliance.webp
cp /path/to/skulliance.png  static/img/app-icons/skulliance.png

# 4. Edit src/data/apps.js — paste the entry at the end of the Showcases array

# 5. Validate (required check)
yarn install
yarn build                 # must pass with no errors

# 6. Commit and push
git add -A
git commit -m "Add Skulliance to app showcase"
git push origin add-skulliance
```

Then open a PR from `add-skulliance` **into `cardano-foundation/cardano-org:staging`**,
select the **"Add Your App"** PR template, and complete its checklist
(live on mainnet ✓, functional / real use case ✓, image specs met ✓, one category ✓).

---

## Pre-submit checklist

- [ ] `https://github.com/oculusorbus/skulliance` loads while logged out (truly public)
- [ ] `skulliance.webp` is ≤ 500 KB and 16:9, no browser chrome
- [ ] `skulliance.png` is square, ≥ 128px
- [ ] Entry appended to end of `Showcases` array, valid JS (commas correct)
- [ ] `yarn build` passes locally
- [ ] PR targets the `staging` branch
